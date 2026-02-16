<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Questions\Actions\GenerateQuestionsFromBlueprint;
use App\Domain\Questions\Data\BlueprintInputData;
use App\Models\QuestionGeneration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateQuestionsFromBlueprintJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $generationId)
    {
    }

    public function handle(GenerateQuestionsFromBlueprint $action): void
    {
        $generation = QuestionGeneration::query()->find($this->generationId);

        if ($generation === null) {
            return;
        }

        $blueprint = BlueprintInputData::fromArray($generation->blueprint ?? []);

        $action->execute($blueprint, $generation);
    }
}
