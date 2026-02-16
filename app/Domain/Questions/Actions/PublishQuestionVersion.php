<?php

declare(strict_types=1);

namespace App\Domain\Questions\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PublishQuestionVersion
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
    }

    /**
     * @throws ValidationException
     */
    public function execute(QuestionVersion $questionVersion, User $teacher, bool $override = false, ?string $overrideNote = null): Question
    {
        if (! $teacher->isTeacher()) {
            throw ValidationException::withMessages([
                'teacher' => 'Only teachers can publish question versions.',
            ]);
        }

        if (! $override && $questionVersion->reviewer_status !== 'pass') {
            throw ValidationException::withMessages([
                'reviewer_status' => 'Reviewer status must be pass before publish.',
            ]);
        }

        $trimmedNote = trim((string) $overrideNote);

        if ($override && $trimmedNote === '') {
            throw ValidationException::withMessages([
                'override_note' => 'Override publish note is required.',
            ]);
        }

        if ($override) {
            $questionVersion->update([
                'reviewer_status' => 'pass',
                'reviewer_override_by' => $teacher->id,
                'reviewer_overridden_at' => now(),
                'reviewer_override_note' => $trimmedNote,
            ]);

            $this->recordAuditEvent->execute(
                actor: $teacher,
                actorType: 'teacher',
                eventType: 'question_published_override',
                entityType: 'question_version',
                entityId: $questionVersion->uuid,
                meta: [
                    'question_id' => $questionVersion->question_id,
                    'question_version_id' => $questionVersion->id,
                    'note' => $trimmedNote,
                    'issues' => is_array($questionVersion->reviewer_issues) ? $questionVersion->reviewer_issues : [],
                ],
            );
        }

        $question = $questionVersion->question;

        if ($question->status !== Question::STATUS_ACTIVE) {
            $question->update(['status' => Question::STATUS_ACTIVE]);
        }

        if (! $override) {
            $this->recordAuditEvent->execute(
                actor: $teacher,
                actorType: 'teacher',
                eventType: 'question_published',
                entityType: 'question_version',
                entityId: $questionVersion->uuid,
                meta: [
                    'question_id' => $questionVersion->question_id,
                    'question_version_id' => $questionVersion->id,
                ],
            );
        }

        return $question->fresh();
    }
}
