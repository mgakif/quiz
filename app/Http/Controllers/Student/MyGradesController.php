<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Gradebook\ComputeStudentTermGrade;
use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use App\Models\StudentTermGrade;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyGradesController extends Controller
{
    public function __invoke(Request $request, ComputeStudentTermGrade $computeStudentTermGrade): View
    {
        /** @var User $student */
        $student = $request->user();
        abort_unless($student->isStudent(), 403);

        $terms = Term::query()
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->get();

        $selectedTermId = (string) ($request->query('term_id') ?? ($terms->first()?->id ?? ''));
        $term = $selectedTermId !== '' ? $terms->firstWhere('id', $selectedTermId) : null;

        $classOptions = StudentProfile::query()
            ->where('student_id', $student->id)
            ->orderBy('class_id')
            ->pluck('class_id')
            ->map(fn (mixed $value): int => (int) $value)
            ->values()
            ->all();

        $selectedClassId = $request->query('class_id');
        $classId = $selectedClassId !== null ? (int) $selectedClassId : ($classOptions[0] ?? null);

        $result = null;
        $finalGrade = null;

        if ($term instanceof Term) {
            $result = $computeStudentTermGrade->execute(
                term: $term,
                student: $student,
                classId: $classId,
                persist: false,
            );

            $gradeSnapshot = StudentTermGrade::query()
                ->where('term_id', $term->id)
                ->where('student_id', $student->id)
                ->first();

            $finalGrade = $gradeSnapshot?->finalGrade() ?? $result['computed_grade'];
        }

        return view('student.my-grades', [
            'terms' => $terms,
            'selectedTermId' => $selectedTermId,
            'classOptions' => $classOptions,
            'selectedClassId' => $classId,
            'result' => $result,
            'finalGrade' => $finalGrade,
        ]);
    }
}
