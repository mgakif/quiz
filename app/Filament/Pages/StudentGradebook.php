<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Gradebook\ComputeStudentTermGrade;
use App\Domain\Gradebook\OverrideStudentTermGrade;
use App\Models\Assessment;
use App\Models\StudentProfile;
use App\Models\StudentTermGrade;
use App\Models\Term;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;
use Illuminate\Validation\ValidationException;

class StudentGradebook extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user';

    protected static string | UnitEnum | null $navigationGroup = 'Gradebook';

    protected static ?string $navigationLabel = 'Student Gradebook';

    protected static ?string $title = 'Student Gradebook';

    protected string $view = 'filament.pages.student-gradebook';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $result = null;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $assessmentRows = [];

    public function mount(ComputeStudentTermGrade $computeStudentTermGrade): void
    {
        $defaultTerm = Term::query()->where('is_active', true)->orderByDesc('start_date')->first()
            ?? Term::query()->orderByDesc('start_date')->first();
        $defaultStudent = User::query()->where('role', User::ROLE_STUDENT)->orderBy('name')->first();

        $this->form->fill([
            'term_id' => $defaultTerm?->id,
            'class_id' => null,
            'student_id' => $defaultStudent?->id,
            'overridden_grade' => null,
            'override_reason' => null,
        ]);

        $this->loadState($computeStudentTermGrade);
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
                            ->required()
                            ->searchable(),
                        Select::make('class_id')
                            ->label('Class')
                            ->options($this->classOptions())
                            ->placeholder('All classes'),
                        Select::make('student_id')
                            ->label('Student')
                            ->options($this->studentOptions())
                            ->required()
                            ->searchable(),
                    ])
                    ->columns(3),
                Section::make('Override')
                    ->schema([
                        TextInput::make('overridden_grade')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100),
                        Textarea::make('override_reason')
                            ->rows(3),
                    ])
                    ->columns(2),
            ]);
    }

    public function applyFilters(ComputeStudentTermGrade $computeStudentTermGrade): void
    {
        $this->loadState($computeStudentTermGrade);
    }

    public function saveOverride(OverrideStudentTermGrade $overrideStudentTermGrade): void
    {
        $term = Term::query()->find((string) ($this->data['term_id'] ?? ''));
        $student = User::query()->find((int) ($this->data['student_id'] ?? 0));

        if (! $term instanceof Term || ! $student instanceof User) {
            return;
        }

        $grade = StudentTermGrade::query()->firstOrCreate(
            [
                'term_id' => $term->id,
                'student_id' => $student->id,
            ],
            [
                'computed_grade' => null,
                'computed_at' => null,
            ],
        );

        $rawOverride = $this->data['overridden_grade'] ?? null;
        $overriddenGrade = $rawOverride === null || $rawOverride === '' ? null : (float) $rawOverride;

        /** @var User|null $teacher */
        $teacher = auth()->user();

        if (! $teacher instanceof User) {
            return;
        }

        try {
            $overrideStudentTermGrade->execute(
                studentTermGrade: $grade,
                teacher: $teacher,
                overriddenGrade: $overriddenGrade,
                reason: $this->data['override_reason'] ?? null,
            );
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('Override failed')
                ->body(collect($exception->errors())->flatten()->implode("\n"))
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Override saved.')
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

    /**
     * @return array<string, string>
     */
    private function studentOptions(): array
    {
        return User::query()
            ->where('role', User::ROLE_STUDENT)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (User $student): array => [(string) $student->id => $student->name.' (#'.$student->id.')'])
            ->all();
    }

    private function loadState(ComputeStudentTermGrade $computeStudentTermGrade): void
    {
        $term = Term::query()->find((string) ($this->data['term_id'] ?? ''));
        $student = User::query()->find((int) ($this->data['student_id'] ?? 0));

        if (! $term instanceof Term || ! $student instanceof User) {
            $this->result = null;
            $this->assessmentRows = [];

            return;
        }

        $classId = filled($this->data['class_id'] ?? null) ? (int) $this->data['class_id'] : null;

        if ($classId !== null) {
            $isInClass = StudentProfile::query()
                ->where('student_id', $student->id)
                ->where('class_id', $classId)
                ->exists();

            if (! $isInClass) {
                $this->result = null;
                $this->assessmentRows = [];

                return;
            }
        }

        $this->result = $computeStudentTermGrade->execute($term, $student, $classId, false);
        $this->assessmentRows = is_array($this->result['assessments'] ?? null) ? $this->result['assessments'] : [];

        $grade = StudentTermGrade::query()
            ->where('term_id', $term->id)
            ->where('student_id', $student->id)
            ->first();

        $this->data['overridden_grade'] = $grade?->overridden_grade !== null ? (float) $grade->overridden_grade : null;
        $this->data['override_reason'] = $grade?->override_reason;
    }
}
