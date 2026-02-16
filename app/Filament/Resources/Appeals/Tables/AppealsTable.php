<?php

declare(strict_types=1);

namespace App\Filament\Resources\Appeals\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AppealsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['student', 'attemptItem.attempt', 'attemptItem.questionVersion']))
            ->columns([
                TextColumn::make('uuid')
                    ->label('Appeal UUID')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('student.name')
                    ->label('Student')
                    ->searchable(),
                TextColumn::make('attemptItem.attempt.id')
                    ->label('Attempt')
                    ->sortable(),
                TextColumn::make('attemptItem.attempt.exam_id')
                    ->label('Exam')
                    ->sortable(),
                TextColumn::make('attemptItem.questionVersion.payload')
                    ->label('Question')
                    ->state(fn ($record): string => (string) (data_get($record->attemptItem?->questionVersion?->payload, 'stem') ?? data_get($record->attemptItem?->questionVersion?->payload, 'text') ?? ''))
                    ->limit(40),
                TextColumn::make('attemptItem.questionVersion.type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'reviewing' => 'Reviewing',
                        'resolved' => 'Resolved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
