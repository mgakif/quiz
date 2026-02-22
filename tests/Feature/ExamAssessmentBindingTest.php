<?php

declare(strict_types=1);

use App\Models\Assessment;
use App\Models\Exam;
use App\Models\Term;

it('auto creates and binds an assessment when exam is created', function (): void {
    $activeTerm = Term::query()->create([
        'name' => '2025-2026 Spring',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ]);

    Exam::query()->create([
        'id' => 4101,
        'title' => 'Quiz Alpha',
        'class_id' => 9,
        'scheduled_at' => now()->addDay(),
    ]);

    $assessment = Assessment::query()->where('legacy_exam_id', 4101)->first();

    expect($assessment)->not->toBeNull()
        ->and($assessment?->term_id)->toBe($activeTerm->id)
        ->and($assessment?->title)->toBe('Quiz Alpha')
        ->and($assessment?->category)->toBe('quiz')
        ->and((float) $assessment?->weight)->toBe(1.0)
        ->and($assessment?->published)->toBeTrue();
});

it('keeps assessment editable fields while syncing title on exam update', function (): void {
    config(['assessments.sync_title_on_exam_update' => true]);

    Term::query()->create([
        'name' => '2025-2026 Fall',
        'start_date' => now()->subMonths(6)->toDateString(),
        'end_date' => now()->subMonths(3)->toDateString(),
        'is_active' => false,
    ]);

    $activeTerm = Term::query()->create([
        'name' => '2025-2026 Spring',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ]);

    $nextTerm = Term::query()->create([
        'name' => '2025-2026 Summer',
        'start_date' => now()->addMonth()->toDateString(),
        'end_date' => now()->addMonths(3)->toDateString(),
        'is_active' => false,
    ]);

    $exam = Exam::query()->create([
        'id' => 4102,
        'title' => 'Quiz Beta',
        'class_id' => 9,
    ]);

    $assessment = $exam->assessment;

    expect($assessment)->not->toBeNull()
        ->and($assessment?->term_id)->toBe($activeTerm->id);

    $assessment?->update([
        'term_id' => $nextTerm->id,
        'category' => 'exam',
        'weight' => 2.5,
        'published' => false,
    ]);

    $exam->update([
        'title' => 'Quiz Beta Updated',
    ]);

    $updatedAssessment = $exam->assessment()->first();

    expect($updatedAssessment)->not->toBeNull()
        ->and($updatedAssessment?->term_id)->toBe($nextTerm->id)
        ->and($updatedAssessment?->category)->toBe('exam')
        ->and((float) $updatedAssessment?->weight)->toBe(2.5)
        ->and($updatedAssessment?->published)->toBeFalse()
        ->and($updatedAssessment?->title)->toBe('Quiz Beta Updated');
});

it('marks bound assessment as unpublished when exam is deleted', function (): void {
    Term::query()->create([
        'name' => '2025-2026 Spring',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ]);

    $exam = Exam::query()->create([
        'id' => 4103,
        'title' => 'Quiz Gamma',
        'class_id' => 9,
    ]);

    expect($exam->assessment)->not->toBeNull();

    $exam->delete();

    $this->assertDatabaseHas('assessments', [
        'legacy_exam_id' => 4103,
        'published' => false,
    ]);
});
