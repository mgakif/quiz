<?php

declare(strict_types=1);

namespace App\Domain\Questions\Actions;

use App\Models\QuestionVersion;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MarkQuestionVersionReviewedOverride
{
    /**
     * @throws ValidationException
     */
    public function execute(QuestionVersion $questionVersion, User $teacher, string $note): QuestionVersion
    {
        if (blank($note)) {
            throw ValidationException::withMessages([
                'note' => 'Reviewer note is required for override.',
            ]);
        }

        $questionVersion->update([
            'reviewer_status' => 'pass',
            'reviewer_override_by' => $teacher->id,
            'reviewer_overridden_at' => now(),
            'reviewer_override_note' => $note,
        ]);

        return $questionVersion->fresh();
    }
}
