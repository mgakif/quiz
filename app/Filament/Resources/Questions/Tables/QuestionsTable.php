<?php

declare(strict_types=1);

namespace App\Filament\Resources\Questions\Tables;

use App\Filament\Resources\Questions\QuestionResource;
use App\Models\Question;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['latestVersion.stats']))
            ->columns([
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('latestVersion.type')
                    ->label('Type')
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('difficulty')
                    ->numeric()
                    ->placeholder('-'),
                TextColumn::make('latestVersion.stats.usage_count')
                    ->label('Usage Count')
                    ->numeric()
                    ->default(0),
                TextColumn::make('latestVersion.stats.correct_rate')
                    ->label('Correct Rate')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->placeholder('-'),
                TextColumn::make('latestVersion.stats.avg_score')
                    ->label('Avg Score')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(QuestionResource::statusOptions()),
                SelectFilter::make('type')
                    ->options(QuestionResource::typeOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $type = $data['value'] ?? null;

                        if (blank($type)) {
                            return $query;
                        }

                        return $query->whereHas('latestVersion', fn (Builder $versionQuery): Builder => $versionQuery->where('type', $type));
                    }),
                SelectFilter::make('difficulty')
                    ->options([
                        1 => '1',
                        2 => '2',
                        3 => '3',
                        4 => '4',
                        5 => '5',
                    ]),
                Filter::make('tag')
                    ->schema([
                        TextInput::make('tag')
                            ->label('Tag'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $tag = $data['tag'] ?? null;

                        if (blank($tag)) {
                            return $query;
                        }

                        return $query->whereJsonContains('tags', $tag);
                    }),
            ])
            ->recordActions([
                Action::make('newVersion')
                    ->label('New Version')
                    ->icon('heroicon-o-document-duplicate')
                    ->authorize(fn (Question $record): bool => auth()->user()?->can('createVersion', $record) ?? false)
                    ->schema([
                        Select::make('type')
                            ->options(QuestionResource::typeOptions())
                            ->required(),
                        CodeEditor::make('payload')
                            ->label('Payload (JSON)')
                            ->language(Language::Json)
                            ->required()
                            ->columnSpanFull(),
                        CodeEditor::make('answer_key')
                            ->label('Answer Key (JSON)')
                            ->language(Language::Json)
                            ->required()
                            ->columnSpanFull(),
                        CodeEditor::make('rubric')
                            ->label('Rubric (JSON)')
                            ->language(Language::Json)
                            ->columnSpanFull(),
                    ])
                    ->action(function (Question $record, array $data): void {
                        $record->createVersion([
                            'type' => $data['type'],
                            'payload' => QuestionResource::decodeJson($data['payload'] ?? null, 'payload') ?? [],
                            'answer_key' => QuestionResource::decodeJson($data['answer_key'] ?? null, 'answer_key') ?? [],
                            'rubric' => QuestionResource::decodeJson($data['rubric'] ?? null, 'rubric', true),
                        ]);
                    }),
                Action::make('archive')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Question $record): bool => $record->status !== Question::STATUS_ARCHIVED)
                    ->authorize(fn (Question $record): bool => auth()->user()?->can('archive', $record) ?? false)
                    ->action(fn (Question $record): bool => $record->update(['status' => Question::STATUS_ARCHIVED])),
                Action::make('deprecate')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Question $record): bool => $record->status !== Question::STATUS_DEPRECATED)
                    ->authorize(fn (Question $record): bool => auth()->user()?->can('deprecate', $record) ?? false)
                    ->action(fn (Question $record): bool => $record->update(['status' => Question::STATUS_DEPRECATED])),
                EditAction::make(),
            ]);
    }
}
