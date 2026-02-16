<?php

declare(strict_types=1);

namespace App\Filament\Resources\RegradeDecisions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RegradeDecisionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['decider', 'attemptItem.questionVersion', 'questionVersion']))
            ->columns([
                TextColumn::make('decider.name')
                    ->label('Decided By')
                    ->searchable(),
                TextColumn::make('decided_at')
                    ->label('Decided At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('scope')
                    ->badge(),
                TextColumn::make('decision_type')
                    ->badge(),
                TextColumn::make('summary')
                    ->label('Summary')
                    ->state(function ($record): string {
                        $payload = is_array($record->payload) ? $record->payload : [];

                        if ($record->decision_type === 'partial_credit') {
                            return 'new_points: '.(string) ($payload['new_points'] ?? '-');
                        }

                        if ($record->decision_type === 'void_question') {
                            return 'mode: '.(string) ($payload['mode'] ?? '-');
                        }

                        if (in_array($record->decision_type, ['answer_key_change', 'rubric_change'], true)) {
                            return 'new_version_id: '.(string) ($payload['new_version_id'] ?? '-');
                        }

                        return '-';
                    }),
            ])
            ->defaultSort('decided_at', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }
}
