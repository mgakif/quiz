<?php

declare(strict_types=1);

namespace App\Filament\Resources\Appeals\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AppealInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Appeal')
                    ->schema([
                        TextEntry::make('uuid')->label('Appeal UUID'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('student.name')->label('Student'),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('reason_text')
                            ->label('Student Reason')
                            ->columnSpanFull(),
                        TextEntry::make('teacher_note')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make('Attempt Item')
                    ->schema([
                        TextEntry::make('attemptItem.attempt.id')->label('Attempt ID'),
                        TextEntry::make('attemptItem.questionVersion.type')->label('Question Type')->badge(),
                        TextEntry::make('attemptItem.response.response_payload')
                            ->label('Student Response')
                            ->formatStateUsing(fn (mixed $state): string => json_encode($state ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]')
                            ->fontFamily('mono')
                            ->columnSpanFull(),
                        TextEntry::make('attemptItem.rubricScore.total_points')
                            ->label('Current Score')
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
