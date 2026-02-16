<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Questions\Actions\EvaluateQuestionPreviewAnswer;
use App\Domain\Questions\Actions\PublishQuestionVersion;
use App\Models\AiGrading;
use App\Models\QuestionVersion;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;
use Illuminate\Validation\ValidationException;

class QuestionSandbox extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-beaker';

    protected static string | UnitEnum | null $navigationGroup = 'Question Bank';

    protected static ?string $navigationLabel = 'Sandbox / Preview';

    protected static ?string $title = 'Question Sandbox';

    protected string $view = 'filament.pages.question-sandbox';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $previewResult = null;

    public ?string $aiSuggestion = null;

    public bool $showRubricForm = false;

    public bool $showAiSuggestion = false;

    public function mount(): void
    {
        $this->form->fill([
            'question_version_id' => null,
            'mcq_choice_id' => null,
            'matching_pairs' => [],
            'open_response_text' => null,
            'rubric_scores' => [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('question_version_id')
                    ->label('Question Version')
                    ->options(fn (): array => $this->latestVersionOptions())
                    ->searchable()
                    ->required()
                    ->live()
                    ->getSearchResultsUsing(fn (string $search): array => $this->searchVersionOptions($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => $this->resolveVersionLabel($value)),
                Placeholder::make('selected_question_summary')
                    ->label('Selected Question')
                    ->content(fn (): string => $this->selectedQuestionSummary()),
                Radio::make('mcq_choice_id')
                    ->label('MCQ Choice')
                    ->options(fn (): array => $this->mcqOptions())
                    ->visible(fn (): bool => $this->selectedQuestionType() === 'mcq'),
                Repeater::make('matching_pairs')
                    ->label('Matching')
                    ->schema([
                        TextInput::make('left')
                            ->label('Left')
                            ->disabled()
                            ->dehydrated(true),
                        Select::make('right')
                            ->label('Right')
                            ->options(fn (): array => $this->matchingRightOptions())
                            ->required(),
                    ])
                    ->columnSpanFull()
                    ->visible(fn (): bool => $this->selectedQuestionType() === 'matching'),
                Textarea::make('open_response_text')
                    ->label('Student Response')
                    ->rows(6)
                    ->visible(fn (): bool => in_array($this->selectedQuestionType(), ['short', 'essay'], true)),
                Placeholder::make('ai_suggestion')
                    ->label('AI Suggestion')
                    ->content(fn (): string => $this->showAiSuggestion ? ($this->aiSuggestion ?? 'No AI suggestion found.') : 'Hidden')
                    ->visible(fn (): bool => in_array($this->selectedQuestionType(), ['short', 'essay'], true)),
                Repeater::make('rubric_scores')
                    ->label('Rubric Preview')
                    ->schema([
                        TextInput::make('criterion')->disabled()->dehydrated(true),
                        TextInput::make('max_points')->numeric()->disabled()->dehydrated(true),
                        TextInput::make('points')->numeric()->minValue(0)->default(0)->required(),
                    ])
                    ->columnSpanFull()
                    ->visible(fn (): bool => in_array($this->selectedQuestionType(), ['short', 'essay'], true) && $this->showRubricForm),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label('Publish')
                ->color('success')
                ->disabled(fn (): bool => ! $this->canPublishNormally())
                ->requiresConfirmation()
                ->action(function (PublishQuestionVersion $publishQuestionVersion): void {
                    $version = $this->selectedQuestionVersion();
                    $teacher = auth()->user();

                    if (! $version instanceof QuestionVersion || ! $teacher instanceof User) {
                        return;
                    }

                    try {
                        $publishQuestionVersion->execute($version, $teacher, false);
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Publish failed')
                            ->body(collect($exception->errors())->flatten()->implode("\n"))
                            ->send();

                        return;
                    }

                    Notification::make()->success()->title('Question published.')->send();
                }),
            Action::make('overridePublish')
                ->label('Override Publish')
                ->color('warning')
                ->disabled(fn (): bool => ! $this->canPublishOverride())
                ->schema([
                    Textarea::make('override_note')
                        ->label('Override Note')
                        ->required(),
                ])
                ->action(function (array $data, PublishQuestionVersion $publishQuestionVersion): void {
                    $version = $this->selectedQuestionVersion();
                    $teacher = auth()->user();

                    if (! $version instanceof QuestionVersion || ! $teacher instanceof User) {
                        return;
                    }

                    try {
                        $publishQuestionVersion->execute(
                            questionVersion: $version,
                            teacher: $teacher,
                            override: true,
                            overrideNote: (string) ($data['override_note'] ?? ''),
                        );
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Override publish failed')
                            ->body(collect($exception->errors())->flatten()->implode("\n"))
                            ->send();

                        return;
                    }

                    Notification::make()->success()->title('Question published with override.')->send();
                }),
        ];
    }

    public function submitPreview(EvaluateQuestionPreviewAnswer $evaluator): void
    {
        $version = $this->selectedQuestionVersion();

        if (! $version instanceof QuestionVersion) {
            Notification::make()->danger()->title('Select a question version first.')->send();

            return;
        }

        if ($version->type === 'mcq') {
            $choice = (string) ($this->data['mcq_choice_id'] ?? '');

            if ($choice === '') {
                Notification::make()->danger()->title('Select one MCQ choice.')->send();

                return;
            }

            $this->previewResult = $evaluator->execute($version, ['choice_id' => $choice]);
            $this->showRubricForm = false;

            return;
        }

        if ($version->type === 'matching') {
            $pairs = $this->normalizeMatchingPairs($this->data['matching_pairs'] ?? []);

            if ($pairs === []) {
                Notification::make()->danger()->title('Complete matching pairs first.')->send();

                return;
            }

            $this->previewResult = $evaluator->execute($version, ['answer_key' => $pairs]);
            $this->showRubricForm = false;

            return;
        }

        $responseText = trim((string) ($this->data['open_response_text'] ?? ''));

        if ($responseText === '') {
            Notification::make()->danger()->title('Enter a student response first.')->send();

            return;
        }

        $this->showRubricForm = true;

        if (($this->data['rubric_scores'] ?? []) === []) {
            $this->data['rubric_scores'] = $this->defaultRubricRows($version);
        }

        $this->previewResult = [
            'feedback' => 'Open-ended response captured. Use rubric rows to score.',
            'earned_points' => 0.0,
            'max_points' => 0.0,
            'is_correct' => false,
        ];
    }

    public function evaluateRubricPreview(): void
    {
        $version = $this->selectedQuestionVersion();

        if (! $version instanceof QuestionVersion || ! in_array($version->type, ['short', 'essay'], true)) {
            return;
        }

        $rows = collect($this->data['rubric_scores'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): array => [
                'criterion' => (string) ($row['criterion'] ?? ''),
                'max_points' => round((float) ($row['max_points'] ?? 0), 2),
                'points' => round((float) ($row['points'] ?? 0), 2),
            ])
            ->filter(fn (array $row): bool => $row['criterion'] !== '')
            ->values();

        if ($rows->isEmpty()) {
            Notification::make()->danger()->title('Rubric rows are empty.')->send();

            return;
        }

        $maxTotal = round((float) $rows->sum('max_points'), 2);
        $pointsTotal = round((float) $rows->sum(fn (array $row): float => min($row['max_points'], max(0, $row['points']))), 2);

        $this->previewResult = [
            'feedback' => 'Rubric scored successfully.',
            'earned_points' => $pointsTotal,
            'max_points' => $maxTotal,
            'is_correct' => false,
        ];
    }

    public function toggleAiSuggestion(): void
    {
        if (! in_array($this->selectedQuestionType(), ['short', 'essay'], true)) {
            return;
        }

        $this->showAiSuggestion = ! $this->showAiSuggestion;
    }

    public function canPublishNormally(): bool
    {
        $version = $this->selectedQuestionVersion();

        return $version instanceof QuestionVersion
            && $version->reviewer_status === 'pass'
            && $version->question?->status !== 'active';
    }

    public function canPublishOverride(): bool
    {
        $version = $this->selectedQuestionVersion();

        return $version instanceof QuestionVersion
            && $version->reviewer_status !== 'pass'
            && $version->question?->status !== 'active';
    }

    public function canShowAiSuggestionButton(): bool
    {
        return in_array($this->selectedQuestionType(), ['short', 'essay'], true)
            && filled($this->aiSuggestion);
    }

    public function updatedDataQuestionVersionId(): void
    {
        $this->previewResult = null;
        $this->showRubricForm = false;
        $this->showAiSuggestion = false;
        $this->aiSuggestion = null;

        $version = $this->selectedQuestionVersion();

        if (! $version instanceof QuestionVersion) {
            return;
        }

        if ($version->type === 'matching') {
            $this->data['matching_pairs'] = collect(data_get($version->payload, 'left', []))
                ->filter(fn (mixed $left): bool => is_string($left) && $left !== '')
                ->map(fn (string $left): array => ['left' => $left, 'right' => ''])
                ->values()
                ->all();
        }

        if (in_array($version->type, ['short', 'essay'], true)) {
            $this->data['rubric_scores'] = $this->defaultRubricRows($version);
            $this->aiSuggestion = $this->resolveAiSuggestion($version);
        }
    }

    /**
     * @return array<string, string>
     */
    private function latestVersionOptions(): array
    {
        $rows = QuestionVersion::query()
            ->select('question_versions.id')
            ->joinSub(
                QuestionVersion::query()
                    ->selectRaw('question_id, MAX(version) as latest_version')
                    ->groupBy('question_id'),
                'latest_versions',
                fn ($join) => $join
                    ->on('latest_versions.question_id', '=', 'question_versions.question_id')
                    ->on('latest_versions.latest_version', '=', 'question_versions.version')
            )
            ->orderByDesc('question_versions.id')
            ->limit(25)
            ->pluck('question_versions.id')
            ->all();

        return QuestionVersion::query()
            ->with('question:id,uuid')
            ->whereIn('id', $rows)
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (QuestionVersion $version): array => [(string) $version->id => $this->versionLabel($version)])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function searchVersionOptions(string $search): array
    {
        return QuestionVersion::query()
            ->with('question:id,uuid')
            ->where(function ($query) use ($search): void {
                $query
                    ->where('uuid', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhereHas('question', fn ($questionQuery) => $questionQuery->where('uuid', 'like', "%{$search}%"));
            })
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->mapWithKeys(fn (QuestionVersion $version): array => [(string) $version->id => $this->versionLabel($version)])
            ->all();
    }

    private function resolveVersionLabel(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $version = QuestionVersion::query()->with('question:id,uuid')->find((int) $value);

        return $version instanceof QuestionVersion ? $this->versionLabel($version) : null;
    }

    private function versionLabel(QuestionVersion $version): string
    {
        return sprintf(
            '#%d | %s | q:%s | reviewer:%s',
            $version->id,
            $version->type,
            (string) ($version->question?->uuid ?? '-'),
            $version->reviewer_status,
        );
    }

    /**
     * @return array<string, string>
     */
    private function mcqOptions(): array
    {
        $version = $this->selectedQuestionVersion();

        if (! $version instanceof QuestionVersion || $version->type !== 'mcq') {
            return [];
        }

        $choices = data_get($version->payload, 'choices', data_get($version->payload, 'options', []));

        if (! is_array($choices)) {
            return [];
        }

        $normalized = [];

        foreach ($choices as $key => $choice) {
            if (is_array($choice)) {
                $id = (string) ($choice['id'] ?? $key);
                $normalized[$id] = (string) ($choice['text'] ?? $choice['label'] ?? $id);

                continue;
            }

            $normalized[(string) $key] = (string) $choice;
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function matchingRightOptions(): array
    {
        $version = $this->selectedQuestionVersion();

        if (! $version instanceof QuestionVersion || $version->type !== 'matching') {
            return [];
        }

        $right = data_get($version->payload, 'right', []);

        if (! is_array($right)) {
            return [];
        }

        return collect($right)
            ->filter(fn (mixed $item): bool => is_scalar($item))
            ->mapWithKeys(fn (mixed $item): array => [(string) $item => (string) $item])
            ->all();
    }

    private function selectedQuestionType(): ?string
    {
        return $this->selectedQuestionVersion()?->type;
    }

    private function selectedQuestionVersion(): ?QuestionVersion
    {
        $id = $this->data['question_version_id'] ?? null;

        if (! is_numeric($id)) {
            return null;
        }

        return QuestionVersion::query()->with('question')->find((int) $id);
    }

    private function selectedQuestionSummary(): string
    {
        $version = $this->selectedQuestionVersion();

        if (! $version instanceof QuestionVersion) {
            return '-';
        }

        return sprintf(
            'Version #%d | type: %s | reviewer: %s | question status: %s',
            $version->id,
            $version->type,
            $version->reviewer_status,
            (string) ($version->question?->status ?? '-'),
        );
    }

    /**
     * @param  mixed  $pairs
     * @return array<string, string>
     */
    private function normalizeMatchingPairs(mixed $pairs): array
    {
        if (! is_array($pairs)) {
            return [];
        }

        return collect($pairs)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->mapWithKeys(fn (array $row): array => [
                trim((string) ($row['left'] ?? '')) => trim((string) ($row['right'] ?? '')),
            ])
            ->filter(fn (string $right, string $left): bool => $left !== '' && $right !== '')
            ->all();
    }

    /**
     * @return array<int, array{criterion:string,max_points:float,points:float}>
     */
    private function defaultRubricRows(QuestionVersion $version): array
    {
        $criteria = data_get($version->rubric, 'criteria', []);

        if (! is_array($criteria) || $criteria === []) {
            return [[
                'criterion' => 'default',
                'max_points' => 10.0,
                'points' => 0.0,
            ]];
        }

        return collect($criteria)
            ->filter(fn (mixed $criterion): bool => is_array($criterion))
            ->map(fn (array $criterion): array => [
                'criterion' => (string) ($criterion['id'] ?? $criterion['label'] ?? 'criterion'),
                'max_points' => round((float) ($criterion['max_points'] ?? 0), 2),
                'points' => 0.0,
            ])
            ->values()
            ->all();
    }

    private function resolveAiSuggestion(QuestionVersion $version): ?string
    {
        $ai = AiGrading::query()
            ->whereHas('attemptItem', fn ($query) => $query->where('question_version_id', $version->id))
            ->latest('id')
            ->first();

        if (! $ai instanceof AiGrading || ! is_array($ai->response_json)) {
            return null;
        }

        $criteriaRows = data_get($ai->response_json, 'criteria_breakdown', data_get($ai->response_json, 'criteria', []));

        if (is_array($criteriaRows) && $criteriaRows !== []) {
            $lines = collect($criteriaRows)
                ->filter(fn (mixed $row): bool => is_array($row))
                ->map(function (array $row): string {
                    $name = (string) ($row['criterion'] ?? $row['name'] ?? 'criterion');
                    $points = $row['points'] ?? $row['score'] ?? '-';
                    $max = $row['max_points'] ?? $row['out_of'] ?? '-';

                    return sprintf('- %s: %s/%s', $name, (string) $points, (string) $max);
                })
                ->values()
                ->all();

            if ($lines !== []) {
                return implode("\n", $lines);
            }
        }

        return json_encode($ai->response_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: null;
    }
}
