<?php

declare(strict_types=1);

namespace App\Filament\Resources\PublicExamLinks\Pages;

use App\Filament\Resources\PublicExamLinks\PublicExamLinkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPublicExamLinks extends ListRecords
{
    protected static string $resource = PublicExamLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
