<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuestionVersions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QuestionVersionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Question Version')
                    ->schema([
                        TextEntry::make('question.uuid')->label('Question UUID'),
                        TextEntry::make('version'),
                        TextEntry::make('type')->badge(),
                    ]),
                Section::make('Reviewer')
                    ->schema([
                        TextEntry::make('reviewer_status')->badge(),
                        TextEntry::make('reviewer_summary')->placeholder('-'),
                        TextEntry::make('reviewer_issues')
                            ->formatStateUsing(fn (mixed $state): string => json_encode($state ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]')
                            ->fontFamily('mono')
                            ->columnSpanFull(),
                        TextEntry::make('reviewerOverrideBy.name')->label('Override By')->placeholder('-'),
                        TextEntry::make('reviewer_overridden_at')->dateTime()->placeholder('-'),
                        TextEntry::make('reviewer_override_note')->placeholder('-')->columnSpanFull(),
                    ]),
            ]);
    }
}
