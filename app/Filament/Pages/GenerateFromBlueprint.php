<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Questions\Data\BlueprintInputData;
use App\Jobs\GenerateQuestionsFromBlueprintJob;
use App\Models\QuestionGeneration;
use BackedEnum;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class GenerateFromBlueprint extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Generate From Blueprint';

    protected static ?string $title = 'Generate From Blueprint';

    protected string $view = 'filament.pages.generate-from-blueprint';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'topics' => [],
            'learning_objectives_text' => '',
            'model' => 'gpt-4.1-mini',
            'mcq_count' => 2,
            'matching_count' => 0,
            'short_count' => 1,
            'essay_count' => 0,
            'easy_count' => 1,
            'medium_count' => 1,
            'hard_count' => 1,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TagsInput::make('topics')
                    ->required(),
                Textarea::make('learning_objectives_text')
                    ->label('Learning Objectives (one per line)')
                    ->required(),
                TextInput::make('model')
                    ->default('gpt-4.1-mini')
                    ->required(),
                TextInput::make('mcq_count')->numeric()->minValue(0)->default(0)->required(),
                TextInput::make('matching_count')->numeric()->minValue(0)->default(0)->required(),
                TextInput::make('short_count')->numeric()->minValue(0)->default(0)->required(),
                TextInput::make('essay_count')->numeric()->minValue(0)->default(0)->required(),
                TextInput::make('easy_count')->numeric()->minValue(0)->default(0)->required(),
                TextInput::make('medium_count')->numeric()->minValue(0)->default(0)->required(),
                TextInput::make('hard_count')->numeric()->minValue(0)->default(0)->required(),
            ]);
    }

    public function submit(): void
    {
        $learningObjectives = collect(explode("\n", (string) ($this->data['learning_objectives_text'] ?? '')))
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();

        $blueprint = new BlueprintInputData(
            topics: array_values(array_filter(array_map('strval', $this->data['topics'] ?? []))),
            learningObjectives: $learningObjectives,
            typeCounts: [
                'mcq' => (int) ($this->data['mcq_count'] ?? 0),
                'matching' => (int) ($this->data['matching_count'] ?? 0),
                'short' => (int) ($this->data['short_count'] ?? 0),
                'essay' => (int) ($this->data['essay_count'] ?? 0),
            ],
            difficultyDistribution: [
                'easy' => (int) ($this->data['easy_count'] ?? 0),
                'medium' => (int) ($this->data['medium_count'] ?? 0),
                'hard' => (int) ($this->data['hard_count'] ?? 0),
            ],
        );

        $generation = QuestionGeneration::query()->create([
            'status' => 'pending',
            'model' => (string) ($this->data['model'] ?? 'gpt-4.1-mini'),
            'blueprint' => $blueprint->toArray(),
            'created_by' => auth()->id(),
        ]);

        GenerateQuestionsFromBlueprintJob::dispatch($generation->id);

        Notification::make()
            ->success()
            ->title('Generation started.')
            ->send();

        $this->redirect(Generations::getUrl());
    }
}
