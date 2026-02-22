<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Gradebook\ComputeStudentTermGrade;
use App\Domain\Gradebook\GradeScheme;
use App\Jobs\ComputeTermGradesJob;
use App\Models\Assessment;
use App\Models\StudentProfile;
use App\Models\StudentTermGrade;
use App\Models\Term;
use App\Models\TermGradeScheme;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class GradebookOverview extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|UnitEnum|null $navigationGroup = 'Gradebook';

    protected static ?string $navigationLabel = 'Gradebook Overview';

    protected static ?string $title = 'Gradebook Overview';

    protected string $view = 'filament.pages.gradebook-overview';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array<int, array{
     *   student_id:int,
     *   student_name:string,
     *   computed_grade:float|null,
     *   overridden_grade:float|null,
     *   final_grade:float|null,
     *   missing_assessments_count:int
     * }>
     */
    public array $rows = [];

    public function mount(ComputeStudentTermGrade $computeStudentTermGrade): void
    {
        $defaultTerm = Term::query()->where('is_active', true)->orderByDesc('start_date')->first()
            ?? Term::query()->orderByDesc('start_date')->first();

        $defaultScheme = app(GradeScheme::class)->getSchemeForTerm((string) $defaultTerm?->id);

        $this->form->fill([
            'term_id' => $defaultTerm?->id,
            'class_id' => null,
            'scheme_strategy' => $defaultScheme['normalize_strategy'],
            'scheme_quiz' => $defaultScheme['weights']['quiz'],
            'scheme_exam' => $defaultScheme['weights']['exam'],
            'scheme_assignment' => $defaultScheme['weights']['assignment'],
            'scheme_participation' => $defaultScheme['weights']['participation'],
        ]);

        $this->loadRows($computeStudentTermGrade);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Filters')
                    ->schema([
                        Select::make('term_id')
                            ->label('Term')
                            ->options($this->termOptions())
                            ->searchable()
                            ->required(),
                        Select::make('class_id')
                            ->label('Class')
                            ->options($this->classOptions())
                            ->placeholder('All classes'),
                    ])
                    ->columns(2),
                Section::make('Grade Scheme')
                    ->schema([
                        Select::make('scheme_strategy')
                            ->label('Strategy')
                            ->options([
                                GradeScheme::STRATEGY_SCHEME_TIMES_ASSESSMENT_WEIGHT => 'Scheme x Assessment Weight',
                                GradeScheme::STRATEGY_USE_SCHEME_ONLY => 'Use Scheme Only',
                            ])
                            ->required(),
                        TextInput::make('scheme_quiz')
                            ->label('Quiz')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(1)
                            ->required(),
                        TextInput::make('scheme_exam')
                            ->label('Exam')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(1)
                            ->required(),
                        TextInput::make('scheme_assignment')
                            ->label('Assignment')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(1)
                            ->required(),
                        TextInput::make('scheme_participation')
                            ->label('Participation')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(1)
                            ->required(),
                    ])
                    ->columns(3),
            ]);
    }

    public function applyFilters(ComputeStudentTermGrade $computeStudentTermGrade, GradeScheme $gradeScheme): void
    {
        $this->loadGradeSchemeState((string) ($this->data['term_id'] ?? ''), $gradeScheme);
        $this->loadRows($computeStudentTermGrade);
    }

    public function recompute(): void
    {
        $termId = (string) ($this->data['term_id'] ?? '');

        if ($termId === '') {
            return;
        }

        $classId = filled($this->data['class_id'] ?? null) ? (int) $this->data['class_id'] : null;

        dispatch_sync(new ComputeTermGradesJob($termId, $classId));

        Notification::make()
            ->success()
            ->title('Term grades recomputed.')
            ->send();
    }

    public function saveGradeScheme(ComputeStudentTermGrade $computeStudentTermGrade): void
    {
        $termId = (string) ($this->data['term_id'] ?? '');
        $term = Term::query()->find($termId);

        if (! $term instanceof Term) {
            return;
        }

        $strategy = (string) ($this->data['scheme_strategy'] ?? GradeScheme::STRATEGY_SCHEME_TIMES_ASSESSMENT_WEIGHT);

        if (! in_array($strategy, [
            GradeScheme::STRATEGY_SCHEME_TIMES_ASSESSMENT_WEIGHT,
            GradeScheme::STRATEGY_USE_SCHEME_ONLY,
        ], true)) {
            $strategy = GradeScheme::STRATEGY_SCHEME_TIMES_ASSESSMENT_WEIGHT;
        }

        TermGradeScheme::query()->updateOrCreate(
            ['term_id' => $term->id],
            [
                'weights' => [
                    'quiz' => max(0.0, (float) ($this->data['scheme_quiz'] ?? 1.0)),
                    'exam' => max(0.0, (float) ($this->data['scheme_exam'] ?? 1.0)),
                    'assignment' => max(0.0, (float) ($this->data['scheme_assignment'] ?? 1.0)),
                    'participation' => max(0.0, (float) ($this->data['scheme_participation'] ?? 1.0)),
                ],
                'normalize_strategy' => $strategy,
            ],
        );

        $this->loadRows($computeStudentTermGrade);

        Notification::make()
            ->success()
            ->title('Grade scheme saved.')
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isTeacher() ?? false;
    }

    /**
     * @return array<string, string>
     */
    private function termOptions(): array
    {
        return Term::query()
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->get()
            ->mapWithKeys(fn (Term $term): array => [
                $term->id => sprintf('%s (%s - %s)', $term->name, $term->start_date?->toDateString(), $term->end_date?->toDateString()),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function classOptions(): array
    {
        return Assessment::query()
            ->whereNotNull('class_id')
            ->select('class_id')
            ->distinct()
            ->orderBy('class_id')
            ->pluck('class_id', 'class_id')
            ->mapWithKeys(fn (mixed $value): array => [(string) $value => 'Class '.$value])
            ->all();
    }

    private function loadRows(ComputeStudentTermGrade $computeStudentTermGrade): void
    {
        $termId = (string) ($this->data['term_id'] ?? '');

        if ($termId === '') {
            $this->rows = [];

            return;
        }

        $term = Term::query()->find($termId);

        if (! $term instanceof Term) {
            $this->rows = [];

            return;
        }

        $classId = filled($this->data['class_id'] ?? null) ? (int) $this->data['class_id'] : null;

        $studentsQuery = User::query()
            ->where('role', User::ROLE_STUDENT)
            ->orderBy('name');

        if ($classId !== null) {
            $studentsQuery->whereIn('id', StudentProfile::query()
                ->where('class_id', $classId)
                ->select('student_id'));
        }

        $students = $studentsQuery->get(['id', 'name']);

        $grades = StudentTermGrade::query()
            ->where('term_id', $term->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        $this->rows = $students
            ->map(function (User $student) use ($computeStudentTermGrade, $term, $classId, $grades): array {
                $preview = $computeStudentTermGrade->execute($term, $student, $classId, false);
                $grade = $grades->get($student->id);
                $computed = $grade?->computed_grade !== null ? round((float) $grade->computed_grade, 2) : $preview['computed_grade'];
                $overridden = $grade?->overridden_grade !== null ? round((float) $grade->overridden_grade, 2) : null;

                return [
                    'student_id' => (int) $student->id,
                    'student_name' => (string) $student->name,
                    'computed_grade' => $computed,
                    'overridden_grade' => $overridden,
                    'final_grade' => $overridden ?? $computed,
                    'missing_assessments_count' => (int) $preview['missing_assessments_count'],
                ];
            })
            ->values()
            ->all();
    }

    private function loadGradeSchemeState(string $termId, GradeScheme $gradeScheme): void
    {
        if ($termId === '') {
            return;
        }

        $scheme = $gradeScheme->getSchemeForTerm($termId);

        $this->data['scheme_strategy'] = $scheme['normalize_strategy'];
        $this->data['scheme_quiz'] = $scheme['weights']['quiz'];
        $this->data['scheme_exam'] = $scheme['weights']['exam'];
        $this->data['scheme_assignment'] = $scheme['weights']['assignment'];
        $this->data['scheme_participation'] = $scheme['weights']['participation'];
    }

    public function getGradeSchemeTotalProperty(): float
    {
        return round(
            max(0.0, (float) ($this->data['scheme_quiz'] ?? 0))
            + max(0.0, (float) ($this->data['scheme_exam'] ?? 0))
            + max(0.0, (float) ($this->data['scheme_assignment'] ?? 0))
            + max(0.0, (float) ($this->data['scheme_participation'] ?? 0)),
            2,
        );
    }
}
