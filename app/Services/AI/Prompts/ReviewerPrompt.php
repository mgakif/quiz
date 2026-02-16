<?php

declare(strict_types=1);

namespace App\Services\AI\Prompts;

use App\Models\QuestionVersion;

class ReviewerPrompt
{
    public function build(QuestionVersion $questionVersion): string
    {
        $schema = file_get_contents(base_path('schemas/reviewer.schema.json')) ?: '{}';

        $payload = json_encode($questionVersion->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        $answerKey = json_encode($questionVersion->answer_key, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        $rubric = json_encode($questionVersion->rubric, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'null';

        return "You are a strict question reviewer. Return JSON only, no markdown.\n"
            . "Your output MUST strictly satisfy this schema:\n{$schema}\n\n"
            . "Question type: {$questionVersion->type}\n"
            . "Question payload:\n{$payload}\n\n"
            . "Answer key:\n{$answerKey}\n\n"
            . "Rubric:\n{$rubric}\n";
    }

    /**
     * @param  array<int, string>  $errors
     */
    public function buildFixJson(string $previousOutput, array $errors): string
    {
        $schema = file_get_contents(base_path('schemas/reviewer.schema.json')) ?: '{}';

        $errorsText = implode("\n- ", $errors);

        return "The previous output is invalid for required JSON schema.\n"
            . "Schema:\n{$schema}\n\n"
            . "Validation errors:\n- {$errorsText}\n\n"
            . "Previous output:\n{$previousOutput}\n\n"
            . "Return corrected JSON only, with no markdown.";
    }
}
