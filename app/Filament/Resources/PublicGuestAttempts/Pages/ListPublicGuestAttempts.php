<?php

declare(strict_types=1);

namespace App\Filament\Resources\PublicGuestAttempts\Pages;

use App\Filament\Resources\PublicGuestAttempts\PublicGuestAttemptResource;
use Filament\Resources\Pages\ListRecords;

class ListPublicGuestAttempts extends ListRecords
{
    protected static string $resource = PublicGuestAttemptResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
