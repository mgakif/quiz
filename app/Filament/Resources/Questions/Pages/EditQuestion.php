<?php

declare(strict_types=1);

namespace App\Filament\Resources\Questions\Pages;

use App\Filament\Resources\Questions\QuestionResource;
use Filament\Resources\Pages\EditRecord;

class EditQuestion extends EditRecord
{
    protected static string $resource = QuestionResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $latestVersion = $this->record->latestVersion;

        $data['latest_type'] = $latestVersion?->type;
        $data['latest_payload'] = QuestionResource::encodeJson($latestVersion?->payload);
        $data['latest_answer_key'] = QuestionResource::encodeJson($latestVersion?->answer_key);
        $data['latest_rubric'] = QuestionResource::encodeJson($latestVersion?->rubric);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['latest_type'], $data['latest_payload'], $data['latest_answer_key'], $data['latest_rubric']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
