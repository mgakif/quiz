<?php

declare(strict_types=1);

use App\Domain\Questions\Actions\PublishQuestionVersion;
use App\Models\Question;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('blocks normal publish when reviewer status is fail', function (): void {
    $teacher = User::factory()->teacher()->create();
    $question = Question::factory()->create([
        'status' => Question::STATUS_DRAFT,
        'created_by' => $teacher->id,
    ]);

    $version = $question->createVersion([
        'type' => 'mcq',
        'payload' => [
            'stem' => '2 + 2 = ?',
            'choices' => [
                ['id' => 'A', 'text' => '4'],
                ['id' => 'B', 'text' => '5'],
            ],
        ],
        'answer_key' => ['correct_choice_id' => 'A'],
        'rubric' => null,
        'reviewer_status' => 'fail',
    ]);

    expect(fn () => app(PublishQuestionVersion::class)->execute(
        questionVersion: $version,
        teacher: $teacher,
    ))->toThrow(ValidationException::class);

    expect($question->fresh()->status)->toBe(Question::STATUS_DRAFT);
});

it('publishes question when reviewer status is pass and writes audit event', function (): void {
    $teacher = User::factory()->teacher()->create();
    $question = Question::factory()->create([
        'status' => Question::STATUS_DRAFT,
        'created_by' => $teacher->id,
    ]);

    $version = $question->createVersion([
        'type' => 'mcq',
        'payload' => [
            'stem' => '5 + 5 = ?',
            'choices' => [
                ['id' => 'A', 'text' => '10'],
                ['id' => 'B', 'text' => '11'],
            ],
        ],
        'answer_key' => ['correct_choice_id' => 'A'],
        'rubric' => null,
        'reviewer_status' => 'pass',
    ]);

    app(PublishQuestionVersion::class)->execute(
        questionVersion: $version,
        teacher: $teacher,
        override: false,
    );

    expect($question->fresh()->status)->toBe(Question::STATUS_ACTIVE);

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'question_published',
        'entity_type' => 'question_version',
        'entity_id' => $version->uuid,
        'actor_id' => $teacher->id,
    ]);
});

it('requires note for override publish and writes audit event when provided', function (): void {
    $teacher = User::factory()->teacher()->create();
    $question = Question::factory()->create([
        'status' => Question::STATUS_DRAFT,
        'created_by' => $teacher->id,
    ]);

    $version = $question->createVersion([
        'type' => 'essay',
        'payload' => ['text' => 'Explain the proof.'],
        'answer_key' => ['guide' => 'Key steps'],
        'rubric' => [
            'criteria' => [
                ['id' => 'accuracy', 'max_points' => 5],
            ],
        ],
        'reviewer_status' => 'fail',
    ]);

    $publishAction = app(PublishQuestionVersion::class);

    expect(fn () => $publishAction->execute(
        questionVersion: $version,
        teacher: $teacher,
        override: true,
        overrideNote: '',
    ))->toThrow(ValidationException::class);

    $publishAction->execute(
        questionVersion: $version,
        teacher: $teacher,
        override: true,
        overrideNote: 'Teacher verified manually in sandbox.',
    );

    expect($question->fresh()->status)->toBe(Question::STATUS_ACTIVE)
        ->and($version->fresh()->reviewer_status)->toBe('pass')
        ->and($version->fresh()->reviewer_override_note)->toBe('Teacher verified manually in sandbox.');

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'question_published_override',
        'entity_type' => 'question_version',
        'entity_id' => $version->uuid,
        'actor_id' => $teacher->id,
    ]);
});
