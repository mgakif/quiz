<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiGrading;
use App\Models\AttemptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiGrading>
 */
class AiGradingFactory extends Factory
{
    protected $model = AiGrading::class;

    public function definition(): array
    {
        return [
            'attempt_item_id' => AttemptItem::factory(),
            'response_json' => [
                'version' => '1.0',
                'total_points' => 5,
                'max_points' => 10,
                'confidence' => 0.85,
                'criteria_scores' => [
                    [
                        'criterion_id' => 'K1',
                        'score' => 5,
                        'max_score' => 10,
                        'reasoning' => 'Basic reasoning text.',
                        'evidence' => [],
                    ],
                ],
                'overall_feedback' => 'Good start.',
                'flags' => [],
            ],
            'confidence' => 0.85,
            'flags' => [],
            'status' => 'draft',
        ];
    }
}
