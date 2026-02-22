<?php

declare(strict_types=1);

namespace App\Domain\Gradebook;

use App\Models\TermGradeScheme;

class GradeScheme
{
    public const STRATEGY_USE_SCHEME_ONLY = 'use_scheme_only';

    public const STRATEGY_SCHEME_TIMES_ASSESSMENT_WEIGHT = 'scheme_times_assessment_weight';

    /**
     * @var array<int, string>
     */
    public const CATEGORIES = ['quiz', 'exam', 'assignment', 'participation'];

    /**
     * @return array{
     *   weights: array<string, float>,
     *   normalize_strategy: string
     * }
     */
    public function getSchemeForTerm(string $termId): array
    {
        $scheme = TermGradeScheme::query()
            ->where('term_id', $termId)
            ->first();

        $default = $this->defaultScheme();

        if (! $scheme instanceof TermGradeScheme) {
            return $default;
        }

        $rawWeights = is_array($scheme->weights) ? $scheme->weights : [];
        $weights = [];

        foreach (self::CATEGORIES as $category) {
            $weights[$category] = max(0.0, (float) ($rawWeights[$category] ?? 1.0));
        }

        $strategy = (string) $scheme->normalize_strategy;

        if (! in_array($strategy, [
            self::STRATEGY_USE_SCHEME_ONLY,
            self::STRATEGY_SCHEME_TIMES_ASSESSMENT_WEIGHT,
        ], true)) {
            $strategy = self::STRATEGY_SCHEME_TIMES_ASSESSMENT_WEIGHT;
        }

        return [
            'weights' => $weights,
            'normalize_strategy' => $strategy,
        ];
    }

    /**
     * @return array{
     *   weights: array<string, float>,
     *   normalize_strategy: string
     * }
     */
    public function defaultScheme(): array
    {
        return [
            'weights' => [
                'quiz' => 1.0,
                'exam' => 1.0,
                'assignment' => 1.0,
                'participation' => 1.0,
            ],
            'normalize_strategy' => self::STRATEGY_SCHEME_TIMES_ASSESSMENT_WEIGHT,
        ];
    }
}
