<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Analytics\Reports\StudentWeaknessReport;
use App\Models\Attempt;
use App\Models\User;
use BackedEnum;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StudentProfileAnalytics extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Student Analytics';

    protected static ?string $title = 'Student Analytics';

    protected string $view = 'filament.pages.student-profile-analytics';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array<int, array{key:string,attempts:int,avg_score:float,avg_percent:float|null}>
     */
    public array $rows = [];

    /**
     * @var array<int, array{key:string,attempts:int,avg_score:float,avg_percent:float|null}>
     */
    public array $weakestRows = [];

    /**
     * @var array<int, array{key:string,attempts:int,avg_score:float,avg_percent:float|null}>
     */
    public array $strongestRows = [];

    public function mount(StudentWeaknessReport $studentWeaknessReport): void
    {
        $defaultStudentId = array_key_first($this->getStudentOptions());

        $this->form->fill([
            'student_id' => $defaultStudentId !== null ? (int) $defaultStudentId : null,
            'class_id' => null,
            'mode' => 'tag',
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $this->loadReport($studentWeaknessReport);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Filters')
                    ->schema([
                        Select::make('student_id')
                            ->label('Student')
                            ->options($this->getStudentOptions())
                            ->searchable()
                            ->required(),
                        Select::make('class_id')
                            ->label('Class')
                            ->options($this->getClassOptions())
                            ->placeholder('All classes'),
                        Select::make('mode')
                            ->label('Dimension')
                            ->options([
                                'tag' => 'Tag',
                                'learning_objective' => 'Learning Objective',
                            ])
                            ->required(),
                        DatePicker::make('start_date')
                            ->label('Start Date'),
                        DatePicker::make('end_date')
                            ->label('End Date'),
                    ])
                    ->columns(5),
            ]);
    }

    public function applyFilters(StudentWeaknessReport $studentWeaknessReport): void
    {
        $this->loadReport($studentWeaknessReport);
    }

    /**
     * @return array<string, string>
     */
    private function getStudentOptions(): array
    {
        return User::query()
            ->where('role', User::ROLE_STUDENT)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->map(fn (string $name, int|string $id): string => "{$name} (#{$id})")
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function getClassOptions(): array
    {
        $options = Attempt::query()
            ->select('exam_id')
            ->distinct()
            ->orderBy('exam_id')
            ->pluck('exam_id', 'exam_id')
            ->mapWithKeys(fn (int|string $value): array => [(string) $value => "Class {$value}"])
            ->all();

        return ['' => 'All classes'] + $options;
    }

    private function loadReport(StudentWeaknessReport $studentWeaknessReport): void
    {
        $studentId = isset($this->data['student_id']) ? (int) $this->data['student_id'] : null;
        $classId = filled($this->data['class_id'] ?? null) ? (int) $this->data['class_id'] : null;
        $mode = (string) ($this->data['mode'] ?? 'tag');

        if ($studentId === null || $studentId === 0) {
            $this->rows = [];
            $this->weakestRows = [];
            $this->strongestRows = [];

            return;
        }

        $startDate = filled($this->data['start_date'] ?? null) ? Carbon::parse((string) $this->data['start_date']) : null;
        $endDate = filled($this->data['end_date'] ?? null) ? Carbon::parse((string) $this->data['end_date']) : null;

        $this->rows = $studentWeaknessReport->execute(
            studentId: $studentId,
            classId: $classId,
            startDate: $startDate,
            endDate: $endDate,
            mode: $mode,
        );

        $sortableRows = collect($this->rows)
            ->filter(fn (array $row): bool => $row['avg_percent'] !== null)
            ->values();

        $this->weakestRows = $sortableRows
            ->sortBy('avg_percent')
            ->take(5)
            ->values()
            ->all();

        $this->strongestRows = $sortableRows
            ->sortByDesc('avg_percent')
            ->take(5)
            ->values()
            ->all();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isTeacher() ?? false;
    }
}
