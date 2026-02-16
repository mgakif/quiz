<?php

declare(strict_types=1);

namespace App\Services\AI\Prompts;

use App\Models\AttemptItem;

class OpenEndedGradePrompt
{
    public function build(AttemptItem $attemptItem): string
    {
        $schema = file_get_contents(base_path('schemas/openended_grade.schema.json')) ?: '{}';
        $rubric = json_encode($attemptItem->questionVersion->rubric, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'null';
        $response = json_encode($attemptItem->response?->response_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';

        return "Grade this open-ended response with rubric. Return JSON only.\n"
            . "No student name or PII. Use only provided response text and rubric criteria.\n"
            . "Output MUST satisfy schema:\n{$schema}\n\n"
            . "Question type: {$attemptItem->questionVersion->type}\n"
            . "Max points: {$attemptItem->max_points}\n"
            . "Rubric:\n{$rubric}\n\n"
            . "Anonymized response:\n{$response}\n";
    }

    /**
     * @param  array<int, string>  $errors
     */
    public function buildFixJson(string $previousOutput, array $errors): string
    {
        $schema = file_get_contents(base_path('schemas/openended_grade.schema.json')) ?: '{}';

        return "Previous grading JSON is invalid.\n"
            . "Schema:\n{$schema}\n\n"
            . "Validation errors:\n- " . implode("\n- ", $errors) . "\n\n"
            . "Previous output:\n{$previousOutput}\n\n"
            . "Return corrected JSON only.";
    }
}
