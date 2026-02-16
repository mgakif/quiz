<?php

declare(strict_types=1);

namespace App\Domain\Questions\Actions;

use App\Domain\Questions\Data\BlueprintInputData;
use App\Models\Question;
use App\Models\QuestionGeneration;
use App\Services\AI\AIClient;
use App\Services\AI\Prompts\QuestionGeneratePrompt;
use App\Services\AI\SchemaValidator;
use Illuminate\Support\Facades\DB;
use JsonException;

class GenerateQuestionsFromBlueprint
{
    public function __construct(
        public AIClient $aiClient,
        public QuestionGeneratePrompt $prompt,
        public SchemaValidator $schemaValidator,
    ) {
    }

    /**
     * @return array{status:string, generated_count:int}
     */
    public function execute(BlueprintInputData $blueprint, QuestionGeneration $generation): array
    {
        $firstAttempt = $this->parseAndValidate($this->aiClient->complete($this->prompt->build($blueprint)));

        if (! $firstAttempt['valid']) {
            $secondAttempt = $this->parseAndValidate(
                $this->aiClient->complete($this->prompt->buildFixJson($firstAttempt['raw'], $firstAttempt['errors'])),
            );

            if (! $secondAttempt['valid']) {
                $generation->update([
                    'status' => 'needs_review',
                    'generated_count' => 0,
                    'raw_output' => $secondAttempt['raw'],
                    'validation_errors' => $secondAttempt['errors'],
                    'summary' => 'Generation output failed schema validation after retry.',
                ]);

                return ['status' => 'needs_review', 'generated_count' => 0];
            }

            return $this->persistQuestions($generation, $secondAttempt['data']);
        }

        return $this->persistQuestions($generation, $firstAttempt['data']);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{status:string, generated_count:int}
     */
    private function persistQuestions(QuestionGeneration $generation, array $validated): array
    {
        $questions = $validated['questions'] ?? [];

        if (! is_array($questions)) {
            $questions = [];
        }

        DB::transaction(function () use ($questions, $generation, $validated): void {
            foreach ($questions as $generatedQuestion) {
                if (! is_array($generatedQuestion)) {
                    continue;
                }

                $type = (string) ($generatedQuestion['type'] ?? 'mcq');

                $question = Question::query()->create([
                    'status' => Question::STATUS_DRAFT,
                    'difficulty' => $this->mapDifficulty((string) ($generatedQuestion['difficulty'] ?? 'medium')),
                    'tags' => is_array($generatedQuestion['tags'] ?? null) ? $generatedQuestion['tags'] : [],
                    'created_by' => $generation->created_by,
                ]);

                $question->createVersion([
                    'type' => $type,
                    'payload' => $this->buildPayload($generatedQuestion, $type),
                    'answer_key' => $this->buildAnswerKey($generatedQuestion, $type),
                    'rubric' => $this->buildRubric($generatedQuestion, $type),
                ]);
            }

            $generation->update([
                'status' => 'success',
                'generated_count' => count($questions),
                'raw_output' => json_encode($validated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: null,
                'validation_errors' => null,
                'summary' => 'Questions generated successfully.',
            ]);
        });

        return ['status' => 'success', 'generated_count' => count($questions)];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function buildPayload(array $item, string $type): array
    {
        $payload = [
            'learning_objective' => $item['learning_objective'] ?? null,
            'stem' => $item['stem'] ?? null,
            'tags' => $item['tags'] ?? [],
            'rationale' => $item['rationale'] ?? null,
        ];

        if (isset($item[$type]) && is_array($item[$type])) {
            $payload[$type] = $item[$type];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function buildAnswerKey(array $item, string $type): array
    {
        return match ($type) {
            'mcq' => [
                'correct_choice_id' => data_get($item, 'mcq.correct_choice_id'),
                'feedback' => data_get($item, 'mcq.feedback'),
            ],
            'matching' => [
                'answer_key' => data_get($item, 'matching.answer_key'),
                'feedback' => data_get($item, 'matching.feedback'),
            ],
            'short', 'essay' => [
                'expected_points' => data_get($item, "{$type}.expected_points"),
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function buildRubric(array $item, string $type): ?array
    {
        if (! in_array($type, ['short', 'essay'], true)) {
            return null;
        }

        $rubric = data_get($item, "{$type}.rubric");

        return is_array($rubric) ? $rubric : null;
    }

    private function mapDifficulty(string $difficulty): int
    {
        return match ($difficulty) {
            'easy' => 1,
            'medium' => 3,
            'hard' => 5,
            default => 3,
        };
    }

    /**
     * @return array{valid:bool,data:array<string,mixed>,errors:array<int,string>,raw:string}
     */
    private function parseAndValidate(string $raw): array
    {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return ['valid' => false, 'data' => [], 'errors' => [$exception->getMessage()], 'raw' => $raw];
        }

        if (! is_array($decoded)) {
            return ['valid' => false, 'data' => [], 'errors' => ['Root JSON must be an object.'], 'raw' => $raw];
        }

        $errors = $this->schemaValidator->validate($decoded, base_path('schemas/question_generate.schema.json'));

        return ['valid' => $errors === [], 'data' => $decoded, 'errors' => $errors, 'raw' => $raw];
    }
}
