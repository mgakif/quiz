<?php

declare(strict_types=1);

namespace App\Filament\Resources\Questions\Pages;

use App\Filament\Resources\Questions\QuestionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuestion extends CreateRecord
{
    protected static string $resource = QuestionResource::class;

    /**
     * @var array{type:string,payload:array<string,mixed>,answer_key:array<string,mixed>,rubric:array<string,mixed>|null}
     */
    protected array $versionDraft = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->versionDraft = [
            'type' => (string) $data['latest_type'],
            'payload' => QuestionResource::decodeJson($data['latest_payload'] ?? null, 'latest_payload') ?? [],
            'answer_key' => QuestionResource::decodeJson($data['latest_answer_key'] ?? null, 'latest_answer_key') ?? [],
            'rubric' => QuestionResource::decodeJson($data['latest_rubric'] ?? null, 'latest_rubric', true),
        ];

        unset($data['latest_type'], $data['latest_payload'], $data['latest_answer_key'], $data['latest_rubric']);

        $data['created_by'] ??= auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->createVersion($this->versionDraft);
    }
}
