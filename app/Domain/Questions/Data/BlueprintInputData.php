<?php

declare(strict_types=1);

namespace App\Domain\Questions\Data;

class BlueprintInputData
{
    /**
     * @param  array<int, string>  $topics
     * @param  array<int, string>  $learningObjectives
     * @param  array{mcq:int,matching:int,short:int,essay:int}  $typeCounts
     * @param  array{easy:int,medium:int,hard:int}  $difficultyDistribution
     */
    public function __construct(
        public array $topics,
        public array $learningObjectives,
        public array $typeCounts,
        public array $difficultyDistribution,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            topics: array_values(array_filter(array_map('strval', $data['topics'] ?? []))),
            learningObjectives: array_values(array_filter(array_map('strval', $data['learning_objectives'] ?? []))),
            typeCounts: [
                'mcq' => max(0, (int) ($data['type_counts']['mcq'] ?? 0)),
                'matching' => max(0, (int) ($data['type_counts']['matching'] ?? 0)),
                'short' => max(0, (int) ($data['type_counts']['short'] ?? 0)),
                'essay' => max(0, (int) ($data['type_counts']['essay'] ?? 0)),
            ],
            difficultyDistribution: [
                'easy' => max(0, (int) ($data['difficulty_distribution']['easy'] ?? 0)),
                'medium' => max(0, (int) ($data['difficulty_distribution']['medium'] ?? 0)),
                'hard' => max(0, (int) ($data['difficulty_distribution']['hard'] ?? 0)),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'topics' => $this->topics,
            'learning_objectives' => $this->learningObjectives,
            'type_counts' => $this->typeCounts,
            'difficulty_distribution' => $this->difficultyDistribution,
        ];
    }
}
