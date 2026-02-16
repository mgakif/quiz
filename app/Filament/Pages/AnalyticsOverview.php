<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Analytics\Reports\ClassWeaknessReport;
use App\Models\Attempt;
use Carbon\Carbon;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AnalyticsOverview extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Class Analytics';

    protected static ?string $title = 'Class Analytics';

    protected string $view = 'filament.pages.analytics-overview';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array<int, array{key:string,attempts:int,avg_score:float,avg_percent:float|null}>
     */
    public array $weakestRows = [];

    /**
     * @var array<int, array{key:string,attempts:int,avg_score:float,avg_percent:float|null}>
     */
    public array $strongestRows = [];

    public function mount(ClassWeaknessReport $classWeaknessReport): void
    {
        $this->form->fill([
            'class_id' => null,
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $this->loadReport($classWeaknessReport);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Filters')
                    ->schema([
                        Select::make('class_id')
                            ->label('Class')
                            ->options($this->getClassOptions())
                            ->placeholder('All students'),
                        DatePicker::make('start_date')
                            ->label('Start Date'),
                        DatePicker::make('end_date')
                            ->label('End Date'),
                    ])
                    ->columns(3),
            ]);
    }

    public function applyFilters(ClassWeaknessReport $classWeaknessReport): void
    {
        $this->loadReport($classWeaknessReport);
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

        return ['' => 'All students'] + $options;
    }

    private function loadReport(ClassWeaknessReport $classWeaknessReport): void
    {
        $classId = filled($this->data['class_id'] ?? null) ? (int) $this->data['class_id'] : null;

        $startDate = filled($this->data['start_date'] ?? null) ? Carbon::parse((string) $this->data['start_date']) : null;
        $endDate = filled($this->data['end_date'] ?? null) ? Carbon::parse((string) $this->data['end_date']) : null;

        $rows = $classWeaknessReport->execute(
            classId: $classId,
            startDate: $startDate,
            endDate: $endDate,
            mode: 'tag',
        );

        $sortedRows = collect($rows)
            ->filter(fn (array $row): bool => $row['avg_percent'] !== null)
            ->values();

        $this->weakestRows = $sortedRows
            ->sortBy('avg_percent')
            ->take(10)
            ->values()
            ->all();

        $this->strongestRows = $sortedRows
            ->sortByDesc('avg_percent')
            ->take(10)
            ->values()
            ->all();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isTeacher() ?? false;
    }
}
