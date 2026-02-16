<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AiGrading;
use App\Models\AttemptItem;
use App\Services\AI\AIClient;
use App\Services\AI\Prompts\OpenEndedGradePrompt;
use App\Services\AI\SchemaValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JsonException;

class AIGradeAttemptItemJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $attemptItemId)
    {
    }

    public function handle(AIClient $aiClient, OpenEndedGradePrompt $prompt, SchemaValidator $schemaValidator): void
    {
        $attemptItem = AttemptItem::query()
            ->with(['questionVersion', 'response'])
            ->find($this->attemptItemId);

        if ($attemptItem === null || $attemptItem->response === null) {
            return;
        }

        if (! in_array($attemptItem->questionVersion->type, ['short', 'essay'], true)) {
            return;
        }

        $first = $this->parseAndValidate(
            $aiClient->complete($prompt->build($attemptItem)),
            $schemaValidator,
        );

        $result = $first;

        if (! $first['valid']) {
            $result = $this->parseAndValidate(
                $aiClient->complete($prompt->buildFixJson($first['raw'], $first['errors'])),
                $schemaValidator,
            );
        }

        if (! $result['valid']) {
            AiGrading::query()->updateOrCreate(
                ['attempt_item_id' => $attemptItem->id],
                [
                    'response_json' => ['raw' => $result['raw'], 'errors' => $result['errors']],
                    'confidence' => 0,
                    'flags' => ['needs_teacher_review', 'language_unclear'],
                    'status' => 'needs_review',
                ],
            );

            return;
        }

        $data = $result['data'];
        $confidence = round((float) ($data['confidence'] ?? 0), 4);
        $flags = is_array($data['flags'] ?? null) ? $data['flags'] : [];

        if ($confidence < 0.60 && ! in_array('needs_teacher_review', $flags, true)) {
            $flags[] = 'needs_teacher_review';
        }

        $status = $confidence < 0.60 ? 'needs_review' : 'draft';

        AiGrading::query()->updateOrCreate(
            ['attempt_item_id' => $attemptItem->id],
            [
                'response_json' => $data,
                'confidence' => $confidence,
                'flags' => $flags,
                'status' => $status,
            ],
        );
    }

    /**
     * @return array{valid:bool,data:array<string,mixed>,errors:array<int,string>,raw:string}
     */
    private function parseAndValidate(string $raw, SchemaValidator $schemaValidator): array
    {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return ['valid' => false, 'data' => [], 'errors' => [$exception->getMessage()], 'raw' => $raw];
        }

        if (! is_array($decoded)) {
            return ['valid' => false, 'data' => [], 'errors' => ['Root JSON must be an object.'], 'raw' => $raw];
        }

        $errors = $schemaValidator->validate($decoded, base_path('schemas/openended_grade.schema.json'));

        return ['valid' => $errors === [], 'data' => $decoded, 'errors' => $errors, 'raw' => $raw];
    }
}
