<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuestionVersions\Pages;

use App\Filament\Resources\QuestionVersions\QuestionVersionResource;
use Filament\Resources\Pages\ListRecords;

class ListQuestionVersions extends ListRecords
{
    protected static string $resource = QuestionVersionResource::class;
}
