<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Appeals\Actions\CreateAppeal;
use App\Domain\Appeals\Actions\ResolveAppeal;
use App\Jobs\ComputeLeaderboardJob;
use App\Jobs\RegradeAttemptItemJob;
use App\Jobs\UpdateQuestionStatsJob;
use App\Models\AiGrading;
use App\Models\Appeal;
use App\Models\Attempt;
use App\Models\AttemptItem;
use App\Models\AttemptResponse;
use App\Models\Question;
use App\Models\QuestionVersion;
use App\Models\RegradeDecision;
use App\Models\RubricScore;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class DemoSeed extends Command
{
    private const CLASS_ID = 901;

    protected $signature = 'demo:seed {--fresh : Run migrate:fresh before seeding demo data}';

    protected $description = 'Seed deterministic demo data for development and demos';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->components->info('Running migrate:fresh ...');
            $this->call('migrate:fresh', ['--force' => true]);
        }

        $summary = $this->seedDemoData();

        $this->components->info('Demo dataset generated.');
        $this->line('Class: 9A (#'.self::CLASS_ID.')');
        $this->line('Teacher email: demo.teacher@quiz.local');
        $this->line('Student count: '.$summary['student_count']);
        $this->line('Attempt count: '.$summary['attempt_count']);
        $this->line('Appeal count: '.$summary['appeal_count']);
        $this->line('Resolved appeals: '.$summary['resolved_appeal_count']);

        return self::SUCCESS;
    }

    /**
     * @return array{student_count:int,attempt_count:int,appeal_count:int,resolved_appeal_count:int}
     */
    private function seedDemoData(): array
    {
        $teacher = $this->upsertTeacher();
        $students = $this->upsertStudents();
        $quizSchedule = $this->buildQuizSchedule();
        $quizQuestionSets = $this->createQuizQuestionSets($teacher, $quizSchedule);

        $this->createAttemptsAndResponses($teacher, $students, $quizSchedule, $quizQuestionSets);
        $this->seedAppealsAndRegrade($teacher, $students);

        dispatch_sync(new UpdateQuestionStatsJob(classId: self::CLASS_ID));
        dispatch_sync(new ComputeLeaderboardJob(self::CLASS_ID, 'weekly'));
        dispatch_sync(new ComputeLeaderboardJob(self::CLASS_ID, 'all_time'));

        return [
            'student_count' => count($students),
            'attempt_count' => Attempt::query()->where('exam_id', self::CLASS_ID)->count(),
            'appeal_count' => Appeal::query()->count(),
            'resolved_appeal_count' => Appeal::query()->where('status', Appeal::STATUS_RESOLVED)->count(),
        ];
    }

    private function upsertTeacher(): User
    {
        return User::query()->updateOrCreate(
            ['email' => 'demo.teacher@quiz.local'],
            [
                'name' => 'Teacher Demo',
                'password' => 'password',
                'role' => User::ROLE_TEACHER,
            ],
        );
    }

    /**
     * @return array<int, User>
     */
    private function upsertStudents(): array
    {
        $students = [];

        foreach ($this->studentRoster() as $index => $row) {
            $student = User::query()->updateOrCreate(
                ['email' => sprintf('demo.student%02d@quiz.local', $index + 1)],
                [
                    'name' => $row['name'],
                    'password' => 'password',
                    'role' => User::ROLE_STUDENT,
                ],
            );

            StudentProfile::query()->updateOrCreate(
                ['student_id' => $student->id],
                [
                    'class_id' => self::CLASS_ID,
                    'nickname' => $row['nickname'],
                    'show_on_leaderboard' => true,
                ],
            );

            $students[] = $student;
        }

        return $students;
    }

    /**
     * @return array<int, array{name:string,nickname:string}>
     */
    private function studentRoster(): array
    {
        return [
            ['name' => 'Ayse Yilmaz', 'nickname' => 'Aster'],
            ['name' => 'Can Demir', 'nickname' => 'Comet'],
            ['name' => 'Deniz Kaya', 'nickname' => 'Drift'],
            ['name' => 'Ece Arslan', 'nickname' => 'Echo'],
            ['name' => 'Emir Kurt', 'nickname' => 'Falcon'],
            ['name' => 'Ipek Cakir', 'nickname' => 'Glint'],
            ['name' => 'Mert Aydin', 'nickname' => 'Helix'],
            ['name' => 'Nehir Aksoy', 'nickname' => 'Iris'],
            ['name' => 'Ozan Koc', 'nickname' => 'Jade'],
            ['name' => 'Selin Er', 'nickname' => 'Kite'],
            ['name' => 'Sude Tan', 'nickname' => 'Lumen'],
            ['name' => 'Yigit Sari', 'nickname' => 'Nova'],
        ];
    }

    /**
     * @return array<int, array{quiz_code:string,title:string,scheduled_at:CarbonImmutable}>
     */
    private function buildQuizSchedule(): array
    {
        $windowEnd = CarbonImmutable::now()->subDay()->setTime(10, 0);
        $windowStart = $windowEnd->subDays(13);

        return [
            ['quiz_code' => 'Q1', 'title' => '9A Quiz 1', 'scheduled_at' => $windowStart->addDay()],
            ['quiz_code' => 'Q2', 'title' => '9A Quiz 2', 'scheduled_at' => $windowStart->addDays(4)],
            ['quiz_code' => 'Q3', 'title' => '9A Quiz 3', 'scheduled_at' => $windowStart->addDays(8)],
            ['quiz_code' => 'Q4', 'title' => '9A Quiz 4', 'scheduled_at' => $windowStart->addDays(12)],
        ];
    }

    /**
     * @param  array<int, array{quiz_code:string,title:string,scheduled_at:CarbonImmutable}>  $quizSchedule
     * @return array<int, array<int, array{version:QuestionVersion,type:string,max_points:float}>>
     */
    private function createQuizQuestionSets(User $teacher, array $quizSchedule): array
    {
        $questionSets = [];

        foreach ($quizSchedule as $quizIndex => $quiz) {
            $set = [];

            for ($i = 1; $i <= 6; $i++) {
                $version = $this->createMcqVersion($teacher, $quizIndex, $i, $quiz['title']);
                $set[] = ['version' => $version, 'type' => 'mcq', 'max_points' => 2.0];
            }

            for ($i = 1; $i <= 2; $i++) {
                $version = $this->createMatchingVersion($teacher, $quizIndex, $i, $quiz['title']);
                $set[] = ['version' => $version, 'type' => 'matching', 'max_points' => 3.0];
            }

            $set[] = ['version' => $this->createShortVersion($teacher, $quizIndex, $quiz['title']), 'type' => 'short', 'max_points' => 4.0];
            $set[] = ['version' => $this->createEssayVersion($teacher, $quizIndex, $quiz['title']), 'type' => 'essay', 'max_points' => 8.0];

            $questionSets[$quizIndex] = $set;
        }

        return $questionSets;
    }

    private function createMcqVersion(User $teacher, int $quizIndex, int $ordinal, string $quizTitle): QuestionVersion
    {
        $question = Question::query()->create([
            'status' => Question::STATUS_ACTIVE,
            'difficulty' => 2 + ($ordinal % 3),
            'tags' => ['demo_seed', 'class_9a', 'mcq', strtolower(str_replace(' ', '_', $quizTitle))],
            'created_by' => $teacher->id,
        ]);

        $correctChoice = ['A', 'B', 'C', 'D'][($quizIndex + $ordinal) % 4];

        return $question->createVersion([
            'type' => 'mcq',
            'payload' => [
                'stem' => sprintf('%s - MCQ %d', $quizTitle, $ordinal),
                'choices' => [
                    ['id' => 'A', 'text' => 'Option A'],
                    ['id' => 'B', 'text' => 'Option B'],
                    ['id' => 'C', 'text' => 'Option C'],
                    ['id' => 'D', 'text' => 'Option D'],
                ],
            ],
            'answer_key' => ['correct_choice_id' => $correctChoice],
            'rubric' => null,
        ]);
    }

    private function createMatchingVersion(User $teacher, int $quizIndex, int $ordinal, string $quizTitle): QuestionVersion
    {
        $question = Question::query()->create([
            'status' => Question::STATUS_ACTIVE,
            'difficulty' => 3,
            'tags' => ['demo_seed', 'class_9a', 'matching', strtolower(str_replace(' ', '_', $quizTitle))],
            'created_by' => $teacher->id,
        ]);

        $pairs = [
            'A' => (string) (1 + (($quizIndex + $ordinal) % 4)),
            'B' => (string) (1 + (($quizIndex + $ordinal + 1) % 4)),
            'C' => (string) (1 + (($quizIndex + $ordinal + 2) % 4)),
        ];

        return $question->createVersion([
            'type' => 'matching',
            'payload' => [
                'stem' => sprintf('%s - Matching %d', $quizTitle, $ordinal),
                'left' => ['A', 'B', 'C'],
                'right' => ['1', '2', '3', '4'],
            ],
            'answer_key' => ['answer_key' => $pairs],
            'rubric' => null,
        ]);
    }

    private function createShortVersion(User $teacher, int $quizIndex, string $quizTitle): QuestionVersion
    {
        $question = Question::query()->create([
            'status' => Question::STATUS_ACTIVE,
            'difficulty' => 4,
            'tags' => ['demo_seed', 'class_9a', 'short', strtolower(str_replace(' ', '_', $quizTitle))],
            'created_by' => $teacher->id,
        ]);

        return $question->createVersion([
            'type' => 'short',
            'payload' => [
                'text' => sprintf('%s - Kisa cevap: adim aciklayin (Q%d)', $quizTitle, $quizIndex + 1),
            ],
            'answer_key' => [
                'keywords' => ['adim', 'gerekce', 'sonuc'],
            ],
            'rubric' => [
                'criteria' => [
                    ['id' => 'clarity', 'label' => 'Aciklik', 'max_points' => 2],
                    ['id' => 'accuracy', 'label' => 'Dogruluk', 'max_points' => 2],
                ],
            ],
        ]);
    }

    private function createEssayVersion(User $teacher, int $quizIndex, string $quizTitle): QuestionVersion
    {
        $question = Question::query()->create([
            'status' => Question::STATUS_ACTIVE,
            'difficulty' => 5,
            'tags' => ['demo_seed', 'class_9a', 'essay', strtolower(str_replace(' ', '_', $quizTitle))],
            'created_by' => $teacher->id,
        ]);

        return $question->createVersion([
            'type' => 'essay',
            'payload' => [
                'text' => sprintf('%s - Deneme sorusu: cozumunuzu savunun (Q%d)', $quizTitle, $quizIndex + 1),
            ],
            'answer_key' => [
                'guide' => 'Mantikli arguman, dogru terminoloji, ornek kullanimi.',
            ],
            'rubric' => [
                'criteria' => [
                    ['id' => 'argument', 'label' => 'Arguman', 'max_points' => 3],
                    ['id' => 'evidence', 'label' => 'Kanit', 'max_points' => 3],
                    ['id' => 'communication', 'label' => 'Ifade', 'max_points' => 2],
                ],
            ],
        ]);
    }

    /**
     * @param  array<int, User>  $students
     * @param  array<int, array{quiz_code:string,title:string,scheduled_at:CarbonImmutable}>  $quizSchedule
     * @param  array<int, array<int, array{version:QuestionVersion,type:string,max_points:float}>>  $quizQuestionSets
     */
    private function createAttemptsAndResponses(User $teacher, array $students, array $quizSchedule, array $quizQuestionSets): void
    {
        foreach ($students as $studentIndex => $student) {
            $quizIndexes = [0, 1];

            if ($studentIndex % 2 === 0) {
                $quizIndexes[] = 2;
            }

            if ($studentIndex % 3 === 0) {
                $quizIndexes[] = 3;
            }

            foreach (array_values($quizIndexes) as $attemptSequence => $quizIndex) {
                $quiz = $quizSchedule[$quizIndex];
                $startedAt = $quiz['scheduled_at']->addMinutes(($studentIndex % 4) * 7);
                $submittedAt = $startedAt->addMinutes(28 + ($attemptSequence * 4));
                $isUnreleased = ($studentIndex + $quizIndex) % 5 === 0;
                $releaseAt = $isUnreleased
                    ? CarbonImmutable::now()->addDays(2 + (($studentIndex + $quizIndex) % 2))->setTime(9, 0)
                    : $submittedAt->addHours(8);

                $attempt = Attempt::query()->create([
                    'exam_id' => self::CLASS_ID,
                    'student_id' => $student->id,
                    'started_at' => $startedAt,
                    'submitted_at' => $submittedAt,
                    'grade_state' => $isUnreleased ? 'graded' : 'released',
                    'release_at' => $releaseAt,
                ]);

                $questions = $quizQuestionSets[$quizIndex];

                foreach ($questions as $itemOrder => $questionData) {
                    $order = $itemOrder + 1;
                    $attemptItem = AttemptItem::query()->create([
                        'attempt_id' => $attempt->id,
                        'question_version_id' => $questionData['version']->id,
                        'order' => $order,
                        'max_points' => $questionData['max_points'],
                    ]);

                    AttemptResponse::query()->create([
                        'attempt_item_id' => $attemptItem->id,
                        'response_payload' => $this->buildResponsePayload(
                            $questionData['type'],
                            $questionData['version'],
                            $studentIndex,
                            $quizIndex,
                        ),
                        'submitted_at' => $submittedAt,
                    ]);

                    if (! in_array($questionData['type'], ['short', 'essay'], true)) {
                        continue;
                    }

                    $needsTeacherReview = ($studentIndex + $quizIndex + $order) % 3 === 0;

                    if ($needsTeacherReview) {
                        AiGrading::query()->updateOrCreate(
                            ['attempt_item_id' => $attemptItem->id],
                            [
                                'response_json' => [
                                    'suggested_total_points' => 0,
                                    'feedback' => 'Teacher review required for open-ended response.',
                                ],
                                'confidence' => 0.42,
                                'flags' => ['needs_teacher_review'],
                                'status' => 'needs_review',
                            ],
                        );

                        continue;
                    }

                    $totalPoints = $this->calculateOpenEndedPoints(
                        $questionData['type'],
                        $questionData['max_points'],
                        $studentIndex,
                        $quizIndex,
                    );

                    RubricScore::query()->create([
                        'attempt_item_id' => $attemptItem->id,
                        'scores' => [
                            [
                                'criterion' => 'structure',
                                'points' => round($totalPoints * 0.5, 2),
                                'max_points' => round($questionData['max_points'] * 0.5, 2),
                            ],
                            [
                                'criterion' => 'accuracy',
                                'points' => round($totalPoints * 0.5, 2),
                                'max_points' => round($questionData['max_points'] * 0.5, 2),
                            ],
                        ],
                        'total_points' => $totalPoints,
                        'graded_by' => $teacher->id,
                        'graded_at' => $submittedAt->addHours(4),
                        'override_reason' => null,
                        'is_draft' => false,
                    ]);
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResponsePayload(string $type, QuestionVersion $version, int $studentIndex, int $quizIndex): array
    {
        $answerKey = is_array($version->answer_key) ? $version->answer_key : [];

        if ($type === 'mcq') {
            $choices = ['A', 'B', 'C', 'D'];
            $correct = (string) ($answerKey['correct_choice_id'] ?? 'A');
            $selected = ($studentIndex + $quizIndex) % 4 < 2
                ? $correct
                : $choices[($studentIndex + $quizIndex + 1) % 4];

            return ['choice_id' => $selected];
        }

        if ($type === 'matching') {
            $expected = $answerKey['answer_key'] ?? [];
            $response = is_array($expected) ? $expected : [];

            if (($studentIndex + $quizIndex) % 2 === 1 && is_array($expected) && $expected !== []) {
                $firstKey = array_key_first($expected);

                if (is_string($firstKey)) {
                    $response[$firstKey] = '4';
                }
            }

            return ['answer_key' => $response];
        }

        if ($type === 'short') {
            return [
                'text' => sprintf('Kisa cevap %d-%d: adimlar ve sonuc verildi.', $studentIndex + 1, $quizIndex + 1),
            ];
        }

        return [
            'text' => sprintf('Deneme %d-%d: kanit, arguman ve sonuc birlikte sunuldu.', $studentIndex + 1, $quizIndex + 1),
        ];
    }

    private function calculateOpenEndedPoints(string $type, float $maxPoints, int $studentIndex, int $quizIndex): float
    {
        $multiplier = $type === 'essay' ? 0.55 : 0.65;

        return round($maxPoints * ($multiplier + (($studentIndex + $quizIndex) % 3) * 0.1), 2);
    }

    /**
     * @param  array<int, User>  $students
     */
    private function seedAppealsAndRegrade(User $teacher, array $students): void
    {
        $createAppeal = app(CreateAppeal::class);
        $resolveAppeal = app(ResolveAppeal::class);
        $originalQueue = (string) config('queue.default');

        config(['queue.default' => 'database']);

        $firstStudent = $students[0];
        $secondStudent = $students[3];

        $partialCreditItem = $this->findAppealItem((int) $firstStudent->id, 'essay');
        $voidQuestionItem = $this->findAppealItem((int) $secondStudent->id, 'matching');

        try {
            $firstAppeal = $createAppeal->execute(
                attemptItem: $partialCreditItem,
                student: $firstStudent,
                reasonText: 'Puanlamada alternatif cozum yolu dikkate alinmadi.',
            );

            $secondAppeal = $createAppeal->execute(
                attemptItem: $voidQuestionItem,
                student: $secondStudent,
                reasonText: 'Soru ifadesi muhem, tum sinif etkilendi.',
            );

            $resolveAppeal->execute(
                appeal: $firstAppeal,
                teacher: $teacher,
                status: Appeal::STATUS_RESOLVED,
                teacherNote: 'Kismi kredi verildi.',
                decision: [
                    'scope' => 'attempt_item',
                    'decision_type' => 'partial_credit',
                    'payload' => [
                        'new_points' => 6.5,
                        'reason' => 'Cozum yontemi dogru, ara adim eksik.',
                    ],
                ],
            );

            $resolveAppeal->execute(
                appeal: $secondAppeal,
                teacher: $teacher,
                status: Appeal::STATUS_RESOLVED,
                teacherNote: 'Soru toplamdan dusuldu.',
                decision: [
                    'scope' => 'attempt_item',
                    'decision_type' => 'void_question',
                    'payload' => [
                        'mode' => 'drop_from_total',
                    ],
                ],
            );

            $this->runRegradeForAttemptItem((int) $partialCreditItem->id);
            $this->runRegradeForAttemptItem((int) $voidQuestionItem->id);
        } finally {
            config(['queue.default' => $originalQueue]);
        }
    }

    private function findAppealItem(int $studentId, string $questionType): AttemptItem
    {
        $item = AttemptItem::query()
            ->whereHas('attempt', function ($query) use ($studentId): void {
                $query
                    ->where('student_id', $studentId)
                    ->where('exam_id', self::CLASS_ID)
                    ->where('grade_state', 'released');
            })
            ->whereHas('questionVersion', fn ($query) => $query->where('type', $questionType))
            ->orderByDesc('id')
            ->with(['attempt', 'questionVersion'])
            ->first();

        if (! $item instanceof AttemptItem) {
            throw new \RuntimeException('Demo appeal attempt item not found.');
        }

        return $item;
    }

    private function runRegradeForAttemptItem(int $attemptItemId): void
    {
        $decision = RegradeDecision::query()
            ->where('attempt_item_id', $attemptItemId)
            ->whereIn('decision_type', ['partial_credit', 'void_question'])
            ->orderByDesc('id')
            ->first();

        if (! $decision instanceof RegradeDecision) {
            return;
        }

        dispatch_sync(new RegradeAttemptItemJob(
            regradeDecisionId: (int) $decision->id,
            attemptItemId: $attemptItemId,
        ));
    }
}
