<?php

declare(strict_types=1);

namespace App\Filament\Resources\PublicExamLinks\Pages;

use App\Filament\Resources\PublicExamLinks\PublicExamLinkResource;
use Filament\Resources\Pages\EditRecord;

class EditPublicExamLink extends EditRecord
{
    protected static string $resource = PublicExamLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
