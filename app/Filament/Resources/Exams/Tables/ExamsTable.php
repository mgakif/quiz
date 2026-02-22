<?php

declare(strict_types=1);

namespace App\Filament\Resources\Exams\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('assessment.term'))
            ->columns([
                TextColumn::make('id')
                    ->label('Exam ID')
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('class_id')
                    ->label('Class')
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('assessment.term.name')
                    ->label('Term')
                    ->placeholder('-'),
                TextColumn::make('assessment.category')
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('assessment.weight')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),
                IconColumn::make('assessment.published')
                    ->label('Published')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
