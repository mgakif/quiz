<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuestionVersions\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QuestionVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question.uuid')
                    ->label('Question UUID')
                    ->searchable(),
                TextColumn::make('version')
                    ->sortable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('reviewer_status')
                    ->badge(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('reviewer_status')
                    ->options([
                        'pending' => 'Pending',
                        'pass' => 'Pass',
                        'fail' => 'Fail',
                    ]),
                SelectFilter::make('type')
                    ->options([
                        'mcq' => 'MCQ',
                        'matching' => 'Matching',
                        'short' => 'Short',
                        'essay' => 'Essay',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
