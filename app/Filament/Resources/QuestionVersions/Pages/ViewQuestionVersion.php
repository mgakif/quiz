<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuestionVersions\Pages;

use App\Domain\Questions\Actions\MarkQuestionVersionReviewedOverride;
use App\Domain\Questions\Actions\PublishQuestionVersion;
use App\Filament\Resources\QuestionVersions\QuestionVersionResource;
use App\Models\QuestionVersion;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewQuestionVersion extends ViewRecord
{
    protected static string $resource = QuestionVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label('Publish')
                ->color('success')
                ->visible(fn (QuestionVersion $record): bool => $record->reviewer_status === 'pass' && $record->question?->status !== 'active')
                ->authorize(fn (): bool => auth()->user()?->isTeacher() ?? false)
                ->requiresConfirmation()
                ->action(function (QuestionVersion $record, PublishQuestionVersion $publishQuestionVersion): void {
                    $publishQuestionVersion->execute(
                        questionVersion: $record,
                        teacher: auth()->user(),
                        override: false,
                    );

                    Notification::make()
                        ->success()
                        ->title('Question published.')
                        ->send();
                }),
            Action::make('overridePublish')
                ->label('Override Publish')
                ->color('warning')
                ->visible(fn (QuestionVersion $record): bool => $record->reviewer_status !== 'pass' && $record->question?->status !== 'active')
                ->authorize(fn (): bool => auth()->user()?->isTeacher() ?? false)
                ->schema([
                    Textarea::make('note')
                        ->label('Override Note')
                        ->required(),
                ])
                ->action(function (QuestionVersion $record, array $data, PublishQuestionVersion $publishQuestionVersion): void {
                    $publishQuestionVersion->execute(
                        questionVersion: $record,
                        teacher: auth()->user(),
                        override: true,
                        overrideNote: (string) ($data['note'] ?? ''),
                    );

                    Notification::make()
                        ->success()
                        ->title('Question published with override.')
                        ->send();
                }),
            Action::make('markReviewedOverride')
                ->label('Mark as Reviewed (override)')
                ->color('warning')
                ->visible(fn (QuestionVersion $record): bool => $record->reviewer_status === 'fail')
                ->authorize(fn (): bool => auth()->user()?->isTeacher() ?? false)
                ->schema([
                    Textarea::make('note')
                        ->label('Teacher Note')
                        ->required(),
                ])
                ->action(function (QuestionVersion $record, array $data, MarkQuestionVersionReviewedOverride $overrideAction): void {
                    $overrideAction->execute(
                        questionVersion: $record,
                        teacher: auth()->user(),
                        note: (string) ($data['note'] ?? ''),
                    );

                    Notification::make()
                        ->success()
                        ->title('Question version marked as reviewed.')
                        ->send();
                }),
        ];
    }
}
