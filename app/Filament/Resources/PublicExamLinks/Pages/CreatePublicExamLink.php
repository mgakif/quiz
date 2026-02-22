<?php

declare(strict_types=1);

namespace App\Filament\Resources\PublicExamLinks\Pages;

use App\Filament\Resources\PublicExamLinks\PublicExamLinkResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePublicExamLink extends CreateRecord
{
    protected static string $resource = PublicExamLinkResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
