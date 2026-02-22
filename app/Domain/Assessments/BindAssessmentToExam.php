<?php

declare(strict_types=1);

namespace App\Domain\Assessments;

use App\Models\Assessment;
use App\Models\Exam;
use App\Models\Term;
use RuntimeException;

class BindAssessmentToExam
{
    public function ensureBound(Exam $exam): Assessment
    {
        $existingAssessment = $exam->assessment()->first();

        if ($existingAssessment !== null) {
            $exam->setRelation('assessment', $existingAssessment);

            return $existingAssessment;
        }

        $defaultTerm = Term::query()
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->first()
            ?? Term::query()->orderByDesc('start_date')->first();

        if ($defaultTerm === null) {
            throw new RuntimeException('No term available for exam-assessment binding.');
        }

        $assessment = Assessment::query()->create([
            'term_id' => $defaultTerm->id,
            'legacy_exam_id' => (int) $exam->id,
            'class_id' => $exam->class_id,
            'title' => $exam->title,
            'category' => 'quiz',
            'weight' => 1.00,
            'published' => true,
            'scheduled_at' => $exam->scheduled_at,
        ]);

        $exam->setRelation('assessment', $assessment);

        return $assessment;
    }

    public function syncAfterExamUpdate(Exam $exam): void
    {
        $assessment = $this->ensureBound($exam);
        $updates = [];

        if ((bool) config('assessments.sync_title_on_exam_update', true) && $exam->wasChanged('title')) {
            $updates['title'] = $exam->title;
        }

        if ($exam->wasChanged('class_id')) {
            $updates['class_id'] = $exam->class_id;
        }

        if ($exam->wasChanged('scheduled_at')) {
            $updates['scheduled_at'] = $exam->scheduled_at;
        }

        if ($updates !== []) {
            $assessment->update($updates);
        }
    }

    public function markUnpublishedOnExamDelete(Exam $exam): void
    {
        $exam->assessment()->update([
            'published' => false,
        ]);
    }
}
