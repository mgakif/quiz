<?php

declare(strict_types=1);

namespace App\Filament\Resources\PublicGuestAttempts\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PublicGuestAttemptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->whereNotNull('guest_id')
                ->with(['exam', 'guest', 'publicExamLink']))
            ->columns([
                TextColumn::make('id')
                    ->label('Attempt #')
                    ->sortable(),
                TextColumn::make('exam.title')
                    ->label('Exam')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('guest.display_name')
                    ->label('Guest')
                    ->placeholder('Anonymous')
                    ->searchable(),
                TextColumn::make('publicExamLink.token')
                    ->label('Link Token')
                    ->limit(12)
                    ->placeholder('-'),
                TextColumn::make('grade_state')
                    ->badge(),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('exam_id')
                    ->label('Exam')
                    ->relationship('exam', 'title'),
            ]);
    }
}
