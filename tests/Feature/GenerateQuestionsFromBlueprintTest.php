<?php

declare(strict_types=1);

use App\Domain\Questions\Actions\GenerateQuestionsFromBlueprint;
use App\Domain\Questions\Data\BlueprintInputData;
use App\Models\Question;
use App\Models\QuestionGeneration;
use App\Services\AI\AIClient;
use App\Services\AI\Prompts\QuestionGeneratePrompt;
use App\Services\AI\SchemaValidator;

it('creates question and version records from schema-valid fixture output', function (): void {
    $fixture = file_get_contents(base_path('tests/Fixtures/question_generate.valid.json'));
    expect($fixture)->not->toBeFalse();

    $aiClient = new class((string) $fixture) extends AIClient {
        public function __construct(private string $output)
        {
        }

        public function complete(string $prompt): string
        {
            return $this->output;
        }
    };

    $generation = QuestionGeneration::factory()->create([
        'status' => 'pending',
        'blueprint' => BlueprintInputData::fromArray([
            'topics' => ['matematik'],
            'learning_objectives' => ['Temel toplama'],
            'type_counts' => ['mcq' => 1, 'matching' => 0, 'short' => 1, 'essay' => 0],
            'difficulty_distribution' => ['easy' => 1, 'medium' => 1, 'hard' => 0],
        ])->toArray(),
    ]);

    $action = new GenerateQuestionsFromBlueprint($aiClient, new QuestionGeneratePrompt(), new SchemaValidator());

    $result = $action->execute(BlueprintInputData::fromArray($generation->blueprint), $generation);

    expect($result['status'])->toBe('success')
        ->and($result['generated_count'])->toBe(2)
        ->and($generation->fresh()->status)->toBe('success')
        ->and(Question::query()->count())->toBe(2);
});

it('marks generation as needs_review when schema remains invalid after retry', function (): void {
    $aiClient = new class extends AIClient {
        private int $calls = 0;

        public function complete(string $prompt): string
        {
            $this->calls++;

            return $this->calls === 1 ? '{bad_json' : json_encode(['invalid' => true], JSON_THROW_ON_ERROR);
        }
    };

    $generation = QuestionGeneration::factory()->create([
        'status' => 'pending',
        'blueprint' => BlueprintInputData::fromArray([
            'topics' => ['fen'],
            'learning_objectives' => ['Kuvvet'],
            'type_counts' => ['mcq' => 1, 'matching' => 0, 'short' => 0, 'essay' => 0],
            'difficulty_distribution' => ['easy' => 1, 'medium' => 0, 'hard' => 0],
        ])->toArray(),
    ]);

    $action = new GenerateQuestionsFromBlueprint($aiClient, new QuestionGeneratePrompt(), new SchemaValidator());

    $result = $action->execute(BlueprintInputData::fromArray($generation->blueprint), $generation);

    expect($result['status'])->toBe('needs_review')
        ->and($generation->fresh()->status)->toBe('needs_review')
        ->and($generation->fresh()->validation_errors)->not->toBeEmpty();
});

it('saves generated questions with draft status', function (): void {
    $fixture = file_get_contents(base_path('tests/Fixtures/question_generate.valid.json'));
    expect($fixture)->not->toBeFalse();

    $aiClient = new class((string) $fixture) extends AIClient {
        public function __construct(private string $output)
        {
        }

        public function complete(string $prompt): string
        {
            return $this->output;
        }
    };

    $generation = QuestionGeneration::factory()->create([
        'status' => 'pending',
        'blueprint' => BlueprintInputData::fromArray([
            'topics' => ['fizik'],
            'learning_objectives' => ['Kuvvet'],
            'type_counts' => ['mcq' => 1, 'matching' => 0, 'short' => 1, 'essay' => 0],
            'difficulty_distribution' => ['easy' => 0, 'medium' => 2, 'hard' => 0],
        ])->toArray(),
    ]);

    $action = new GenerateQuestionsFromBlueprint($aiClient, new QuestionGeneratePrompt(), new SchemaValidator());
    $action->execute(BlueprintInputData::fromArray($generation->blueprint), $generation);

    expect(Question::query()->where('status', Question::STATUS_DRAFT)->count())->toBe(2);
});
