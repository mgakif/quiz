<?php

declare(strict_types=1);

use App\Domain\Questions\Actions\MarkQuestionVersionReviewedOverride;
use App\Domain\Questions\Actions\ReviewGeneratedQuestion;
use App\Models\Question;
use App\Models\User;
use App\Services\AI\AIClient;
use App\Services\AI\Prompts\ReviewerPrompt;
use App\Services\AI\SchemaValidator;
use Illuminate\Validation\ValidationException;

it('marks reviewer_status as pass when reviewer output passes schema', function (): void {
    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'mcq',
        'payload' => ['text' => '2+2?'],
        'answer_key' => ['correct' => '4'],
        'rubric' => null,
    ]);

    $aiClient = new class extends AIClient {
        public function complete(string $prompt): string
        {
            return json_encode([
                'version' => '1.0',
                'result' => 'pass',
                'issues' => [],
                'summary' => 'Question quality is acceptable.',
            ], JSON_THROW_ON_ERROR);
        }
    };

    $action = new ReviewGeneratedQuestion($aiClient, new ReviewerPrompt(), new SchemaValidator());

    $result = $action->execute($version);

    expect($result['reviewer_status'])->toBe('pass')
        ->and($version->fresh()->reviewer_status)->toBe('pass');
});

it('marks reviewer_status as fail and keeps issues when reviewer fails', function (): void {
    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'essay',
        'payload' => ['text' => 'Explain entropy'],
        'answer_key' => ['guideline' => 'disorder/statistical interpretation'],
        'rubric' => ['criteria' => ['accuracy']],
    ]);

    $aiClient = new class extends AIClient {
        public function complete(string $prompt): string
        {
            return json_encode([
                'version' => '1.0',
                'result' => 'fail',
                'issues' => [
                    [
                        'code' => 'rubric_unclear',
                        'severity' => 'warn',
                        'message' => 'Rubric criterion is too broad.',
                        'suggested_fix' => 'Split rubric into multiple clear criteria.',
                    ],
                ],
                'summary' => 'Needs rubric improvement.',
            ], JSON_THROW_ON_ERROR);
        }
    };

    $action = new ReviewGeneratedQuestion($aiClient, new ReviewerPrompt(), new SchemaValidator());

    $action->execute($version);

    $fresh = $version->fresh();

    expect($fresh->reviewer_status)->toBe('fail')
        ->and($fresh->reviewer_issues)->not->toBeEmpty();
});

it('retries once on invalid schema then fails with minimal language_error issue', function (): void {
    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'short',
        'payload' => ['text' => 'What is inertia?'],
        'answer_key' => ['guideline' => 'resistance to change in motion'],
        'rubric' => ['criteria' => ['concept']],
    ]);

    $aiClient = new class extends AIClient {
        private int $count = 0;

        public function complete(string $prompt): string
        {
            $this->count++;

            return $this->count === 1
                ? '{invalid_json'
                : json_encode(['bad' => 'shape'], JSON_THROW_ON_ERROR);
        }
    };

    $action = new ReviewGeneratedQuestion($aiClient, new ReviewerPrompt(), new SchemaValidator());

    $action->execute($version);

    $fresh = $version->fresh();

    expect($fresh->reviewer_status)->toBe('fail')
        ->and($fresh->reviewer_issues)->toHaveCount(1)
        ->and($fresh->reviewer_issues[0]['code'])->toBe('language_error');
});

it('requires teacher override note and stores audit fields', function (): void {
    $teacher = User::factory()->teacher()->create();

    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'essay',
        'payload' => ['text' => 'Why is the sky blue?'],
        'answer_key' => ['guideline' => 'Rayleigh scattering'],
        'rubric' => ['criteria' => ['concept']],
    ]);

    $version->update([
        'reviewer_status' => 'fail',
        'reviewer_issues' => [[
            'code' => 'off_topic',
            'severity' => 'error',
            'message' => 'Question drifts from target topic.',
            'suggested_fix' => 'Tighten question scope.',
        ]],
        'reviewer_summary' => 'Needs correction.',
    ]);

    $overrideAction = new MarkQuestionVersionReviewedOverride();

    expect(fn () => $overrideAction->execute($version, $teacher, ''))->toThrow(ValidationException::class);

    $updated = $overrideAction->execute($version, $teacher, 'Reviewed manually by teacher.');

    expect($updated->reviewer_status)->toBe('pass')
        ->and($updated->reviewer_override_by)->toBe($teacher->id)
        ->and($updated->reviewer_overridden_at)->not->toBeNull()
        ->and($updated->reviewer_override_note)->toBe('Reviewed manually by teacher.');
});
