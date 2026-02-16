<?php

declare(strict_types=1);

namespace App\Domain\Questions\Actions;

use App\Models\QuestionVersion;

class EvaluateQuestionPreviewAnswer
{
    /**
     * @param  array<string, mixed>  $response
     * @return array{earned_points:float,max_points:float,is_correct:bool,feedback:string}
     */
    public function execute(QuestionVersion $questionVersion, array $response): array
    {
        if ($questionVersion->type === 'mcq') {
            $expected = (string) data_get($questionVersion->answer_key, 'correct_choice_id', data_get($questionVersion->answer_key, 'correct', ''));
            $actual = (string) data_get($response, 'choice_id', '');
            $isCorrect = $expected !== '' && $expected === $actual;

            return [
                'earned_points' => $isCorrect ? 1.0 : 0.0,
                'max_points' => 1.0,
                'is_correct' => $isCorrect,
                'feedback' => $isCorrect ? 'Correct answer.' : 'Incorrect answer.',
            ];
        }

        if ($questionVersion->type === 'matching') {
            $expected = data_get($questionVersion->answer_key, 'answer_key', []);
            $actual = data_get($response, 'answer_key', []);

            $expectedPairs = is_array($expected) ? $expected : [];
            $actualPairs = is_array($actual) ? $actual : [];
            $total = count($expectedPairs);

            $correct = collect($expectedPairs)
                ->filter(fn (mixed $value, string|int $key): bool => array_key_exists((string) $key, $actualPairs) && (string) $actualPairs[(string) $key] === (string) $value)
                ->count();

            $score = $total > 0 ? round($correct / $total, 2) : 0.0;

            return [
                'earned_points' => $score,
                'max_points' => 1.0,
                'is_correct' => $total > 0 && $correct === $total,
                'feedback' => sprintf('Matched %d/%d correctly.', $correct, $total),
            ];
        }

        return [
            'earned_points' => 0.0,
            'max_points' => 0.0,
            'is_correct' => false,
            'feedback' => 'Open-ended questions require rubric evaluation.',
        ];
    }
}
