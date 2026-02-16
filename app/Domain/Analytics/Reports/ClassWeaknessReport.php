<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\ScoreExtractor;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use stdClass;

class ClassWeaknessReport
{
    public function __construct(private ScoreExtractor $scoreExtractor)
    {
    }

    /**
     * @return array<int, array{key:string,attempts:int,avg_score:float,avg_percent:float|null}>
     */
    public function execute(
        ?int $classId = null,
        ?CarbonInterface $startDate = null,
        ?CarbonInterface $endDate = null,
        string $mode = 'tag',
    ): array {
        $rows = $this->scoreExtractor
            ->releasedScoredItemsQuery(
                startDate: $startDate,
                endDate: $endDate,
                classId: $classId,
            )
            ->get();

        return $this->aggregateRows($rows, $mode);
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return array<int, array{key:string,attempts:int,avg_score:float,avg_percent:float|null}>
     */
    private function aggregateRows(Collection $rows, string $mode): array
    {
        /** @var array<string, array{key:string,attempts:int,score_sum:float,percent_sum:float,percent_count:int}> $groups */
        $groups = [];

        foreach ($rows as $row) {
            $keys = $this->resolveKeysForMode($row, $mode);
            $earned = round((float) $row->earned_points, 2);
            $effectiveMaxPoints = round((float) $row->effective_max_points, 2);
            $avgPercent = $effectiveMaxPoints > 0
                ? round(($earned / $effectiveMaxPoints) * 100, 2)
                : null;

            foreach ($keys as $key) {
                $groups[$key] ??= [
                    'key' => $key,
                    'attempts' => 0,
                    'score_sum' => 0.0,
                    'percent_sum' => 0.0,
                    'percent_count' => 0,
                ];

                $groups[$key]['attempts']++;
                $groups[$key]['score_sum'] += $earned;

                if ($avgPercent !== null) {
                    $groups[$key]['percent_sum'] += $avgPercent;
                    $groups[$key]['percent_count']++;
                }
            }
        }

        return collect($groups)
            ->map(fn (array $group): array => [
                'key' => $group['key'],
                'attempts' => $group['attempts'],
                'avg_score' => $group['attempts'] > 0 ? round($group['score_sum'] / $group['attempts'], 2) : 0.0,
                'avg_percent' => $group['percent_count'] > 0 ? round($group['percent_sum'] / $group['percent_count'], 2) : null,
            ])
            ->sortBy('key')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function resolveKeysForMode(stdClass $row, string $mode): array
    {
        $payload = $this->decodeJsonValue($row->version_payload ?? null);
        $questionTags = $this->decodeJsonValue($row->question_tags ?? null);

        if ($mode === 'learning_objective') {
            $learningObjectives = $payload['learning_objectives'] ?? null;

            if (is_array($learningObjectives)) {
                return collect($learningObjectives)
                    ->map(fn (mixed $value): string => trim((string) $value))
                    ->filter()
                    ->values()
                    ->all();
            }

            $learningObjective = trim((string) ($payload['learning_objective'] ?? ''));

            return $learningObjective === '' ? [] : [$learningObjective];
        }

        $tagsFromPayload = is_array($payload['tags'] ?? null) ? $payload['tags'] : [];
        $tags = $tagsFromPayload !== [] ? $tagsFromPayload : (is_array($questionTags) ? $questionTags : []);

        return collect($tags)
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
