<?php

declare(strict_types=1);

namespace App\Services\AI\Prompts;

use App\Domain\Questions\Data\BlueprintInputData;

class QuestionGeneratePrompt
{
    public function build(BlueprintInputData $blueprint): string
    {
        $schema = file_get_contents(base_path('schemas/question_generate.schema.json')) ?: '{}';
        $blueprintJson = json_encode($blueprint->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';

        return "Generate assessment questions in Turkish. Return JSON only, no markdown.\n"
            . "Do not include any student names or personal identifiers.\n"
            . "Output MUST strictly satisfy this schema:\n{$schema}\n\n"
            . "Blueprint:\n{$blueprintJson}\n";
    }

    /**
     * @param  array<int, string>  $errors
     */
    public function buildFixJson(string $previousOutput, array $errors): string
    {
        $schema = file_get_contents(base_path('schemas/question_generate.schema.json')) ?: '{}';

        return "Previous output failed schema validation.\n"
            . "Schema:\n{$schema}\n\n"
            . "Validation errors:\n- " . implode("\n- ", $errors) . "\n\n"
            . "Previous output:\n{$previousOutput}\n\n"
            . "Return corrected JSON only.";
    }
}
