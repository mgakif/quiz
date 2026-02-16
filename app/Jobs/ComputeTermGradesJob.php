<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Gradebook\ComputeStudentTermGrade;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\StudentProfile;
use App\Models\Term;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ComputeTermGradesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $termId, public ?int $classId = null)
    {
    }

    public function handle(ComputeStudentTermGrade $computeStudentTermGrade): void
    {
        $term = Term::query()->find($this->termId);

        if (! $term instanceof Term) {
            return;
        }

        $studentsQuery = User::query()
            ->where('role', User::ROLE_STUDENT)
            ->orderBy('id');

        if ($this->classId !== null) {
            $assessmentExamIds = Assessment::query()
                ->where('term_id', $term->id)
                ->where('class_id', $this->classId)
                ->pluck('legacy_exam_id');

            $studentsQuery->where(function ($query) use ($assessmentExamIds): void {
                $query
                    ->whereIn('id', StudentProfile::query()
                        ->where('class_id', $this->classId)
                        ->select('student_id'))
                    ->orWhereIn('id', Attempt::query()
                        ->whereIn('exam_id', $assessmentExamIds)
                        ->select('student_id')
                        ->distinct());
            });
        }

        $chunkSize = max(1, (int) config('gradebook.chunk_size', 100));

        $studentsQuery->chunkById($chunkSize, function ($students) use ($computeStudentTermGrade, $term): void {
            foreach ($students as $student) {
                $computeStudentTermGrade->execute(
                    term: $term,
                    student: $student,
                    classId: $this->classId,
                    persist: true,
                );
            }
        });
    }
}
