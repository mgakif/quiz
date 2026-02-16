<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\AttemptItem;
use App\Services\Grading\ManualGradingService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class GradeAttemptItem extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'grading-queue/{attemptItem}/grade';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Grade Attempt Item';

    protected string $view = 'filament.pages.grade-attempt-item';

    public AttemptItem $attemptItemRecord;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(AttemptItem $attemptItem): void
    {
        $this->attemptItemRecord = $attemptItem->load(['questionVersion', 'response', 'rubricScore']);

        abort_unless(in_array($this->attemptItemRecord->questionVersion->type, ['short', 'essay'], true), 404);

        $scores = $this->attemptItemRecord->rubricScore?->scores;

        $this->form->fill([
            'scores' => $this->normalizeScoresForForm(is_array($scores) ? $scores : []),
            'override_reason' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Placeholder::make('question')
                    ->label('Question')
                    ->content(fn (): string => (string) data_get($this->attemptItemRecord->questionVersion->payload, 'text', '-')),
                Placeholder::make('response')
                    ->label('Student Response')
                    ->content(fn (): string => json_encode($this->attemptItemRecord->response?->response_payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '-'),
                Repeater::make('scores')
                    ->label('Rubric Criteria')
                    ->schema([
                        TextInput::make('criterion')
                            ->required(),
                        TextInput::make('points')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue((float) $this->attemptItemRecord->max_points)
                            ->default(0)
                            ->required(),
                    ])
                    ->defaultItems(1)
                    ->columnSpanFull()
                    ->required(),
                Textarea::make('override_reason')
                    ->label('Override Reason')
                    ->helperText('Required if total points are changed after an existing grade.')
                    ->columnSpanFull(),
            ]);
    }

    public function save(ManualGradingService $manualGradingService): void
    {
        $scores = collect($this->data['scores'] ?? [])
            ->filter(fn (array $row): bool => filled($row['criterion'] ?? null))
            ->map(fn (array $row): array => [
                'criterion' => (string) ($row['criterion'] ?? ''),
                'points' => round((float) ($row['points'] ?? 0), 2),
            ])
            ->values()
            ->all();

        $totalPoints = (float) collect($scores)->sum('points');

        $manualGradingService->gradeAttemptItem(
            attemptItem: $this->attemptItemRecord,
            scores: $scores,
            totalPoints: $totalPoints,
            gradedBy: (int) auth()->id(),
            overrideReason: $this->data['override_reason'] ?? null,
        );

        Notification::make()
            ->success()
            ->title('Rubric score saved.')
            ->send();

        $this->redirect(GradingQueue::getUrl());
    }

    /**
     * @param  array<int, mixed>  $scores
     * @return array<int, array{criterion:string,points:float}>
     */
    private function normalizeScoresForForm(array $scores): array
    {
        $normalized = collect($scores)
            ->map(function (mixed $score): ?array {
                if (! is_array($score)) {
                    return null;
                }

                $criterion = (string) ($score['criterion'] ?? '');

                if ($criterion === '') {
                    return null;
                }

                return [
                    'criterion' => $criterion,
                    'points' => round((float) ($score['points'] ?? 0), 2),
                ];
            })
            ->filter()
            ->values()
            ->all();

        return $normalized === []
            ? [['criterion' => 'Default', 'points' => 0.0]]
            : $normalized;
    }
}
