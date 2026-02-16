<?php

declare(strict_types=1);

namespace App\Domain\Questions\Actions;

use App\Models\QuestionVersion;
use App\Services\AI\AIClient;
use App\Services\AI\Prompts\ReviewerPrompt;
use App\Services\AI\SchemaValidator;
use JsonException;

class ReviewGeneratedQuestion
{
    public function __construct(
        public AIClient $aiClient,
        public ReviewerPrompt $reviewerPrompt,
        public SchemaValidator $schemaValidator,
    ) {
    }

    /**
     * @return array{reviewer_status:string, reviewer_issues:array<int, array<string, mixed>>, reviewer_summary:string}
     */
    public function execute(QuestionVersion $questionVersion): array
    {
        $firstAttempt = $this->parseAndValidate(
            $this->aiClient->complete($this->reviewerPrompt->build($questionVersion)),
        );

        if ($firstAttempt['valid']) {
            return $this->persistResult($questionVersion, $firstAttempt['data']);
        }

        $retryAttempt = $this->parseAndValidate(
            $this->aiClient->complete(
                $this->reviewerPrompt->buildFixJson(
                    previousOutput: $firstAttempt['raw'],
                    errors: $firstAttempt['errors'],
                ),
            ),
        );

        if ($retryAttempt['valid']) {
            return $this->persistResult($questionVersion, $retryAttempt['data']);
        }

        return $this->persistResult($questionVersion, [
            'result' => 'fail',
            'issues' => [[
                'code' => 'language_error',
                'severity' => 'error',
                'message' => 'Reviewer output failed schema validation.',
                'suggested_fix' => 'Return valid JSON that matches reviewer.schema.json.',
            ]],
            'summary' => 'Reviewer output was invalid after retry.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{reviewer_status:string, reviewer_issues:array<int, array<string, mixed>>, reviewer_summary:string}
     */
    private function persistResult(QuestionVersion $questionVersion, array $data): array
    {
        $status = (string) ($data['result'] ?? 'fail');
        $issues = is_array($data['issues'] ?? null) ? $data['issues'] : [];

        if (($status === 'fail') && ($issues === [])) {
            $issues = [[
                'code' => 'language_error',
                'severity' => 'error',
                'message' => 'Reviewer failed without detailed issues.',
                'suggested_fix' => 'Provide explicit reviewer issues.',
            ]];
        }

        $result = [
            'reviewer_status' => $status,
            'reviewer_issues' => $issues,
            'reviewer_summary' => (string) ($data['summary'] ?? ''),
        ];

        $questionVersion->update($result);

        return $result;
    }

    /**
     * @return array{valid:bool,data:array<string,mixed>,errors:array<int,string>,raw:string}
     */
    private function parseAndValidate(string $raw): array
    {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return [
                'valid' => false,
                'data' => [],
                'errors' => [$exception->getMessage()],
                'raw' => $raw,
            ];
        }

        if (! is_array($decoded)) {
            return [
                'valid' => false,
                'data' => [],
                'errors' => ['Root JSON must be an object.'],
                'raw' => $raw,
            ];
        }

        $errors = $this->schemaValidator->validate($decoded, base_path('schemas/reviewer.schema.json'));

        return [
            'valid' => $errors === [],
            'data' => $decoded,
            'errors' => $errors,
            'raw' => $raw,
        ];
    }
}
