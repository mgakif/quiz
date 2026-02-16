<?php

declare(strict_types=1);

use App\Models\Question;
use App\Models\User;

it('allows teacher to manage questions via policy', function (): void {
    $teacher = User::factory()->teacher()->create();
    $question = Question::factory()->create();

    expect($teacher->can('viewAny', Question::class))->toBeTrue()
        ->and($teacher->can('create', Question::class))->toBeTrue()
        ->and($teacher->can('update', $question))->toBeTrue()
        ->and($teacher->can('archive', $question))->toBeTrue()
        ->and($teacher->can('deprecate', $question))->toBeTrue()
        ->and($teacher->can('createVersion', $question))->toBeTrue();
});

it('denies student from question management via policy', function (): void {
    $student = User::factory()->student()->create();
    $question = Question::factory()->create();

    expect($student->can('viewAny', Question::class))->toBeFalse()
        ->and($student->can('create', Question::class))->toBeFalse()
        ->and($student->can('update', $question))->toBeFalse()
        ->and($student->can('archive', $question))->toBeFalse()
        ->and($student->can('deprecate', $question))->toBeFalse()
        ->and($student->can('createVersion', $question))->toBeFalse();
});
