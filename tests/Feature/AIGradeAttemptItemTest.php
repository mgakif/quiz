<?php

declare(strict_types=1);

use App\Domain\Grading\Actions\ApplyAiSuggestionToRubricScore;
use App\Jobs\AIGradeAttemptItemJob;
use App\Models\AiGrading;
use App\Models\Attempt;
use App\Models\AttemptItem;
use App\Models\AttemptResponse;
use App\Models\Question;
use App\Models\User;
use App\Services\AI\AIClient;
use App\Services\AI\Prompts\OpenEndedGradePrompt;
use App\Services\AI\SchemaValidator;
use Illuminate\Support\Facades\App;

it('saves ai_gradings draft when schema valid', function (): void {
    $student = User::factory()->student()->create();

    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'short',
        'payload' => ['text' => 'Define inertia'],
        'answer_key' => ['expected' => 'resistance to change'],
        'rubric' => ['criteria' => [[
            'id' => 'K1',
            'name' => 'Concept',
            'levels' => [
                ['score' => 0, 'description' => 'No concept'],
                ['score' => 5, 'description' => 'Basic concept'],
            ],
        ]]],
    ]);

    $attempt = Attempt::query()->create([
        'exam_id' => 1,
        'student_id' => $student->id,
        'grade_state' => 'in_review',
        'submitted_at' => now(),
    ]);

    $item = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);

    AttemptResponse::query()->create([
        'attempt_item_id' => $item->id,
        'response_payload' => ['text' => 'Inertia is resistance to change in motion.'],
        'submitted_at' => now(),
    ]);

    App::bind(AIClient::class, fn () => new class extends AIClient {
        public function complete(string $prompt): string
        {
            return json_encode([
                'version' => '1.0',
                'total_points' => 8,
                'max_points' => 10,
                'confidence' => 0.90,
                'criteria_scores' => [[
                    'criterion_id' => 'K1',
                    'score' => 8,
                    'max_score' => 10,
                    'reasoning' => 'Response is accurate enough.',
                    'evidence' => [],
                ]],
                'overall_feedback' => 'Good answer.',
                'flags' => [],
            ], JSON_THROW_ON_ERROR);
        }
    });

    $job = new AIGradeAttemptItemJob($item->id);
    $job->handle(app(AIClient::class), new OpenEndedGradePrompt(), new SchemaValidator());

    $ai = AiGrading::query()->where('attempt_item_id', $item->id)->first();

    expect($ai)->not->toBeNull()
        ->and($ai?->status)->toBe('draft');
});

it('sets needs_review and flag for low confidence ai grading', function (): void {
    $student = User::factory()->student()->create();

    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'essay',
        'payload' => ['text' => 'Explain gravity'],
        'answer_key' => ['expected' => 'attractive force'],
        'rubric' => ['criteria' => [[
            'id' => 'K1',
            'name' => 'Concept',
            'levels' => [
                ['score' => 0, 'description' => 'No concept'],
                ['score' => 10, 'description' => 'Clear concept'],
            ],
        ]]],
    ]);

    $attempt = Attempt::query()->create([
        'exam_id' => 1,
        'student_id' => $student->id,
        'grade_state' => 'in_review',
        'submitted_at' => now(),
    ]);

    $item = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 20,
    ]);

    AttemptResponse::query()->create([
        'attempt_item_id' => $item->id,
        'response_payload' => ['text' => 'Gravity pulls objects.'],
        'submitted_at' => now(),
    ]);

    App::bind(AIClient::class, fn () => new class extends AIClient {
        public function complete(string $prompt): string
        {
            return json_encode([
                'version' => '1.0',
                'total_points' => 6,
                'max_points' => 20,
                'confidence' => 0.30,
                'criteria_scores' => [[
                    'criterion_id' => 'K1',
                    'score' => 6,
                    'max_score' => 20,
                    'reasoning' => 'Limited detail found.',
                    'evidence' => [],
                ]],
                'overall_feedback' => 'Needs more detail.',
                'flags' => ['low_evidence'],
            ], JSON_THROW_ON_ERROR);
        }
    });

    $job = new AIGradeAttemptItemJob($item->id);
    $job->handle(app(AIClient::class), new OpenEndedGradePrompt(), new SchemaValidator());

    $ai = AiGrading::query()->where('attempt_item_id', $item->id)->first();

    expect($ai?->status)->toBe('needs_review')
        ->and($ai?->flags)->toContain('needs_teacher_review');
});

it('applies ai suggestion into draft rubric score without publishing final', function (): void {
    $student = User::factory()->student()->create();

    $question = Question::factory()->create();
    $version = $question->createVersion([
        'type' => 'short',
        'payload' => ['text' => 'Define velocity'],
        'answer_key' => ['expected' => 'displacement over time'],
        'rubric' => ['criteria' => [[
            'id' => 'K1',
            'name' => 'Definition',
            'levels' => [
                ['score' => 0, 'description' => 'No definition'],
                ['score' => 10, 'description' => 'Correct definition'],
            ],
        ]]],
    ]);

    $attempt = Attempt::query()->create([
        'exam_id' => 1,
        'student_id' => $student->id,
        'grade_state' => 'in_review',
        'submitted_at' => now(),
    ]);

    $item = AttemptItem::query()->create([
        'attempt_id' => $attempt->id,
        'question_version_id' => $version->id,
        'order' => 1,
        'max_points' => 10,
    ]);

    AttemptResponse::query()->create([
        'attempt_item_id' => $item->id,
        'response_payload' => ['text' => 'Velocity is displacement over time.'],
        'submitted_at' => now(),
    ]);

    AiGrading::query()->create([
        'attempt_item_id' => $item->id,
        'response_json' => [
            'version' => '1.0',
            'total_points' => 9,
            'max_points' => 10,
            'confidence' => 0.88,
            'criteria_scores' => [[
                'criterion_id' => 'K1',
                'score' => 9,
                'max_score' => 10,
                'reasoning' => 'Mostly correct definition.',
                'evidence' => [],
            ]],
            'overall_feedback' => 'Strong answer.',
            'flags' => [],
        ],
        'confidence' => 0.88,
        'flags' => [],
        'status' => 'draft',
    ]);

    $applied = (new ApplyAiSuggestionToRubricScore())->execute($item->fresh('aiGrading'));

    expect($applied)->not->toBeNull()
        ->and($applied?->is_draft)->toBeTrue()
        ->and($item->attempt->fresh()->grade_state)->toBe('in_review');
});
