<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Grading\Actions\ApplyAiSuggestionToRubricScore;
use App\Models\AttemptItem;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GradingQueue extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Grading Queue';

    protected static ?string $title = 'Grading Queue';

    protected string $view = 'filament.pages.grading-queue';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AttemptItem::query()
                    ->with(['attempt.student', 'questionVersion', 'response', 'rubricScore', 'aiGrading'])
                    ->whereHas('questionVersion', fn (Builder $query): Builder => $query->whereIn('type', ['short', 'essay']))
                    ->whereHas('response', fn (Builder $query): Builder => $query->whereNotNull('submitted_at'))
                    ->whereDoesntHave('rubricScore')
            )
            ->columns([
                TextColumn::make('attempt.id')
                    ->label('Attempt')
                    ->sortable(),
                TextColumn::make('attempt.student.name')
                    ->label('Student')
                    ->searchable(),
                TextColumn::make('questionVersion.type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('max_points')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('aiGrading.confidence')
                    ->label('AI Confidence')
                    ->numeric(decimalPlaces: 4)
                    ->placeholder('-'),
                TextColumn::make('aiGrading.flags')
                    ->label('AI Flags')
                    ->formatStateUsing(fn (mixed $state): string => is_array($state) ? implode(', ', $state) : '-')
                    ->wrap(),
                TextColumn::make('response.submitted_at')
                    ->label('Submitted At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('grade')
                    ->label('Grade')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (AttemptItem $record): string => GradeAttemptItem::getUrl(['attemptItem' => $record->id])),
                Action::make('applyAiSuggestion')
                    ->label('Apply AI suggestion')
                    ->icon('heroicon-o-cpu-chip')
                    ->visible(fn (AttemptItem $record): bool => in_array($record->aiGrading?->status, ['draft', 'needs_review'], true))
                    ->action(function (AttemptItem $record, ApplyAiSuggestionToRubricScore $action): void {
                        $applied = $action->execute($record);

                        if ($applied === null) {
                            Notification::make()->danger()->title('No AI suggestion to apply.')->send();

                            return;
                        }

                        Notification::make()->success()->title('AI suggestion applied as draft rubric score.')->send();
                    }),
            ]);
    }
}
