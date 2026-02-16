<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentProfiles\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('student'))
            ->columns([
                TextColumn::make('class_id')
                    ->label('Class')
                    ->sortable(),
                TextColumn::make('student.name')
                    ->label('Student')
                    ->searchable(),
                TextColumn::make('nickname')
                    ->searchable(),
                IconColumn::make('show_on_leaderboard')
                    ->boolean()
                    ->label('Visible'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
