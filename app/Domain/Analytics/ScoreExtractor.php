<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use App\Domain\Attempts\Policies\GradeReleasePolicy;
use App\Models\Attempt;
use App\Models\AttemptItem;
use App\Models\RegradeDecision;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class ScoreExtractor
{
    public function __construct(private GradeReleasePolicy $gradeReleasePolicy) {}

    /**
     * @return array{
     *     earned_points:float,
     *     max_points:float,
     *     avg_percent:float|null,
     *     is_auto_gradable:bool,
     *     is_correct:bool
     * }
     */
    public function extractForAttemptItem(AttemptItem $attemptItem): array
    {
        $attemptItem->loadMissing(['questionVersion', 'response', 'rubricScore']);

        $maxPoints = round((float) $attemptItem->max_points, 2);
        $baseEarned = $this->resolveBaseEarnedPoints($attemptItem, $maxPoints);
        $decision = $this->resolveLatestOverrideDecision($attemptItem);

        if ($decision !== null && $decision->decision_type === 'void_question') {
            $mode = (string) data_get($decision->payload, 'mode', 'drop_from_total');

            if ($mode === 'drop_from_total') {
                return [
                    'earned_points' => 0.0,
                    'max_points' => 0.0,
                    'avg_percent' => null,
                    'is_auto_gradable' => $this->isAutoGradableType((string) $attemptItem->questionVersion?->type),
                    'is_correct' => $this->isCorrectAutoGradable($attemptItem),
                ];
            }

            return [
                'earned_points' => $maxPoints,
                'max_points' => $maxPoints,
                'avg_percent' => $maxPoints > 0 ? 100.0 : null,
                'is_auto_gradable' => $this->isAutoGradableType((string) $attemptItem->questionVersion?->type),
                'is_correct' => $this->isCorrectAutoGradable($attemptItem),
            ];
        }

        if ($decision !== null && $decision->decision_type === 'partial_credit') {
            $newPoints = (float) data_get($decision->payload, 'new_points', 0);
            $earned = $this->clamp($newPoints, 0, $maxPoints);

            return [
                'earned_points' => $earned,
                'max_points' => $maxPoints,
                'avg_percent' => $maxPoints > 0 ? round(($earned / $maxPoints) * 100, 2) : null,
                'is_auto_gradable' => $this->isAutoGradableType((string) $attemptItem->questionVersion?->type),
                'is_correct' => $this->isCorrectAutoGradable($attemptItem),
            ];
        }

        return [
            'earned_points' => $baseEarned,
            'max_points' => $maxPoints,
            'avg_percent' => $maxPoints > 0 ? round(($baseEarned / $maxPoints) * 100, 2) : null,
            'is_auto_gradable' => $this->isAutoGradableType((string) $attemptItem->questionVersion?->type),
            'is_correct' => $this->isCorrectAutoGradable($attemptItem),
        ];
    }

    public function releasedScoredItemsQuery(
        ?CarbonInterface $startDate = null,
        ?CarbonInterface $endDate = null,
        ?int $classId = null,
        ?int $studentId = null,
        bool $includeGuests = false,
    ): QueryBuilder {
        $releasedAttemptIds = Attempt::query()->select('id');
        $this->gradeReleasePolicy->applyVisibilityScope($releasedAttemptIds);

        $latestAttemptItemDecisionIds = DB::table('regrade_decisions')
            ->selectRaw('attempt_item_id, MAX(id) AS latest_id')
            ->where('scope', 'attempt_item')
            ->whereNotNull('attempt_item_id')
            ->whereIn('decision_type', ['partial_credit', 'void_question'])
            ->groupBy('attempt_item_id');

        $latestQuestionVersionDecisionIds = DB::table('regrade_decisions')
            ->selectRaw('question_version_id, MAX(id) AS latest_id')
            ->where('scope', 'question_version')
            ->whereNotNull('question_version_id')
            ->whereIn('decision_type', ['partial_credit', 'void_question'])
            ->groupBy('question_version_id');

        $questionTypeExpr = 'qv.type';
        $mcqExpectedExpr = $this->jsonText('qv.answer_key', '$.correct_choice_id');
        $mcqActualExpr = "COALESCE({$this->jsonText('r.response_payload', '$.choice_id')}, {$this->jsonText('r.response_payload', '$.selected_choice_id')}, {$this->jsonText('r.response_payload', '$.answer')})";
        $matchingExpectedExpr = $this->jsonRaw('qv.answer_key', '$.answer_key');
        $matchingActualExpr = "COALESCE({$this->jsonRaw('r.response_payload', '$.answer_key')}, {$this->jsonRaw('r.response_payload', '$.matching')})";

        $isAutoGradableExpr = "CASE WHEN {$questionTypeExpr} IN ('mcq', 'matching') THEN 1 ELSE 0 END";
        $isAutoCorrectExpr = "
            CASE
                WHEN {$questionTypeExpr} = 'mcq' THEN CASE WHEN {$mcqExpectedExpr} IS NOT NULL AND {$mcqExpectedExpr} <> '' AND {$mcqExpectedExpr} = {$mcqActualExpr} THEN 1 ELSE 0 END
                WHEN {$questionTypeExpr} = 'matching' THEN CASE WHEN {$matchingExpectedExpr} IS NOT NULL AND {$matchingExpectedExpr} = {$matchingActualExpr} THEN 1 ELSE 0 END
                ELSE 0
            END
        ";
        $baseEarnedExpr = "
            CASE
                WHEN {$questionTypeExpr} IN ('mcq', 'matching') THEN
                    CASE WHEN {$isAutoCorrectExpr} = 1 THEN ai.max_points ELSE 0 END
                ELSE
                    COALESCE(CASE WHEN rs.is_draft = 0 THEN rs.total_points END, 0)
            END
        ";

        $itemModeExpr = $this->jsonText('rid.payload', '$.mode');
        $versionModeExpr = $this->jsonText('rvd.payload', '$.mode');
        $itemPointsExpr = $this->jsonNumber('rid.payload', '$.new_points');
        $versionPointsExpr = $this->jsonNumber('rvd.payload', '$.new_points');

        $effectiveDecisionExpr = 'COALESCE(rid.decision_type, rvd.decision_type)';
        $effectiveModeExpr = "COALESCE({$itemModeExpr}, {$versionModeExpr}, 'drop_from_total')";
        $effectivePartialPointsExpr = "COALESCE({$itemPointsExpr}, {$versionPointsExpr})";

        $effectiveMaxExpr = "
            CASE
                WHEN {$effectiveDecisionExpr} = 'void_question' AND {$effectiveModeExpr} = 'drop_from_total' THEN 0
                ELSE ai.max_points
            END
        ";

        $effectiveEarnedExpr = "
            CASE
                WHEN {$effectiveDecisionExpr} = 'void_question' AND {$effectiveModeExpr} = 'drop_from_total' THEN 0
                WHEN {$effectiveDecisionExpr} = 'void_question' AND {$effectiveModeExpr} = 'give_full' THEN ai.max_points
                WHEN {$effectiveDecisionExpr} = 'partial_credit' AND {$effectivePartialPointsExpr} IS NOT NULL THEN
                    CASE
                        WHEN {$effectivePartialPointsExpr} < 0 THEN 0
                        WHEN {$effectivePartialPointsExpr} > ai.max_points THEN ai.max_points
                        ELSE {$effectivePartialPointsExpr}
                    END
                ELSE {$baseEarnedExpr}
            END
        ";

        $usedAtExpr = 'COALESCE(a.submitted_at, a.started_at)';

        $query = DB::table('attempt_items as ai')
            ->join('attempts as a', 'a.id', '=', 'ai.attempt_id')
            ->join('question_versions as qv', 'qv.id', '=', 'ai.question_version_id')
            ->join('questions as q', 'q.id', '=', 'qv.question_id')
            ->leftJoin('responses as r', 'r.attempt_item_id', '=', 'ai.id')
            ->leftJoin('rubric_scores as rs', 'rs.attempt_item_id', '=', 'ai.id')
            ->leftJoinSub($latestAttemptItemDecisionIds, 'rid_latest', 'rid_latest.attempt_item_id', '=', 'ai.id')
            ->leftJoin('regrade_decisions as rid', 'rid.id', '=', 'rid_latest.latest_id')
            ->leftJoinSub($latestQuestionVersionDecisionIds, 'rvd_latest', 'rvd_latest.question_version_id', '=', 'ai.question_version_id')
            ->leftJoin('regrade_decisions as rvd', 'rvd.id', '=', 'rvd_latest.latest_id')
            ->whereIn('ai.attempt_id', $releasedAttemptIds)
            ->when(! $includeGuests, fn (QueryBuilder $builder) => $builder->whereNotNull('a.student_id'))
            ->when($classId !== null, fn (QueryBuilder $builder) => $builder->where('a.exam_id', $classId))
            ->when($studentId !== null, fn (QueryBuilder $builder) => $builder->where('a.student_id', $studentId))
            ->when($startDate !== null, fn (QueryBuilder $builder) => $builder->whereRaw("{$usedAtExpr} >= ?", [$startDate]))
            ->when($endDate !== null, fn (QueryBuilder $builder) => $builder->whereRaw("{$usedAtExpr} <= ?", [$endDate]))
            ->selectRaw('ai.id AS attempt_item_id')
            ->selectRaw('ai.question_version_id AS question_version_id')
            ->selectRaw('qv.question_id AS question_id')
            ->selectRaw('a.exam_id AS class_id')
            ->selectRaw('a.student_id AS student_id')
            ->selectRaw("{$usedAtExpr} AS used_at")
            ->selectRaw('qv.type AS question_type')
            ->selectRaw('qv.payload AS version_payload')
            ->selectRaw('q.tags AS question_tags')
            ->selectRaw('ai.max_points AS max_points')
            ->selectRaw("{$isAutoGradableExpr} AS is_auto_gradable")
            ->selectRaw("{$isAutoCorrectExpr} AS is_auto_correct")
            ->selectRaw("{$effectiveMaxExpr} AS effective_max_points")
            ->selectRaw("{$effectiveEarnedExpr} AS earned_points");

        return $query;
    }

    public function isAutoGradableType(string $type): bool
    {
        return in_array($type, ['mcq', 'matching'], true);
    }

    private function resolveBaseEarnedPoints(AttemptItem $attemptItem, float $maxPoints): float
    {
        if ($this->isAutoGradableType((string) $attemptItem->questionVersion?->type)) {
            return $this->isCorrectAutoGradable($attemptItem) ? $maxPoints : 0.0;
        }

        $rubricScore = $attemptItem->rubricScore;

        if ($rubricScore === null || $rubricScore->is_draft) {
            return 0.0;
        }

        return round((float) $rubricScore->total_points, 2);
    }

    private function isCorrectAutoGradable(AttemptItem $attemptItem): bool
    {
        $type = (string) $attemptItem->questionVersion?->type;
        $answerKey = is_array($attemptItem->questionVersion?->answer_key) ? $attemptItem->questionVersion->answer_key : [];
        $response = is_array($attemptItem->response?->response_payload) ? $attemptItem->response->response_payload : [];

        if ($type === 'mcq') {
            $expected = (string) ($answerKey['correct_choice_id'] ?? '');
            $actual = (string) ($response['choice_id'] ?? $response['selected_choice_id'] ?? $response['answer'] ?? '');

            return $expected !== '' && $expected === $actual;
        }

        if ($type === 'matching') {
            $expected = $answerKey['answer_key'] ?? [];
            $actual = $response['answer_key'] ?? $response['matching'] ?? [];

            return is_array($expected) && is_array($actual) && $expected !== [] && $expected === $actual;
        }

        return false;
    }

    private function resolveLatestOverrideDecision(AttemptItem $attemptItem): ?RegradeDecision
    {
        return RegradeDecision::query()
            ->whereIn('decision_type', ['partial_credit', 'void_question'])
            ->where(function ($query) use ($attemptItem): void {
                $query
                    ->where(function ($attemptItemQuery) use ($attemptItem): void {
                        $attemptItemQuery
                            ->where('scope', 'attempt_item')
                            ->where('attempt_item_id', $attemptItem->id);
                    })
                    ->orWhere(function ($versionQuery) use ($attemptItem): void {
                        $versionQuery
                            ->where('scope', 'question_version')
                            ->where('question_version_id', $attemptItem->question_version_id);
                    });
            })
            ->orderByRaw("CASE WHEN scope = 'attempt_item' THEN 0 ELSE 1 END")
            ->orderByDesc('decided_at')
            ->orderByDesc('id')
            ->first();
    }

    private function clamp(float $value, float $min, float $max): float
    {
        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return round($value, 2);
    }

    private function jsonText(string $column, string $path): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $pathTokens = str_replace(['$.', '.'], ['', ','], $path);
            $pathExpr = '{'.$pathTokens.'}';

            return "({$column}::jsonb #>> '{$pathExpr}')";
        }

        if ($driver === 'mysql') {
            return "JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}'))";
        }

        return "json_extract({$column}, '{$path}')";
    }

    private function jsonNumber(string $column, string $path): string
    {
        return "CAST({$this->jsonText($column, $path)} AS DECIMAL(10,2))";
    }

    private function jsonRaw(string $column, string $path): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $pathTokens = str_replace(['$.', '.'], ['', ','], $path);
            $pathExpr = '{'.$pathTokens.'}';

            return "({$column}::jsonb #> '{$pathExpr}')::text";
        }

        if ($driver === 'mysql') {
            return "JSON_EXTRACT({$column}, '{$path}')";
        }

        return "json_extract({$column}, '{$path}')";
    }
}
