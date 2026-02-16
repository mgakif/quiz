<?php

declare(strict_types=1);

namespace App\Filament\Resources\RegradeDecisions\Pages;

use App\Filament\Resources\RegradeDecisions\RegradeDecisionResource;
use Filament\Resources\Pages\ListRecords;

class ListRegradeDecisions extends ListRecords
{
    protected static string $resource = RegradeDecisionResource::class;
}
