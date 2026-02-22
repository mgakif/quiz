<?php

declare(strict_types=1);

namespace App\Filament\Resources\PublicExamLinks\Tables;

use App\Models\PublicExamLink;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PublicExamLinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['exam', 'creator']))
            ->columns([
                TextColumn::make('exam.title')
                    ->label('Exam')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('token')
                    ->limit(16)
                    ->tooltip(fn (PublicExamLink $record): string => $record->token),
                IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean(),
                TextColumn::make('max_attempts')
                    ->placeholder('Unlimited'),
                TextColumn::make('attempts_count')
                    ->counts('attempts')
                    ->label('Attempts'),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->placeholder('-'),
            ])
            ->recordActions([
                Action::make('copyLink')
                    ->label('Copy Link')
                    ->icon('heroicon-o-link')
                    ->action(function (PublicExamLink $record): void {
                        Notification::make()
                            ->success()
                            ->title('Public link')
                            ->body(url('/public/'.$record->token))
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
