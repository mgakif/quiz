<?php

declare(strict_types=1);

namespace App\Filament\Resources\Appeals\Pages;

use App\Domain\Appeals\Actions\ResolveAppeal;
use App\Domain\Regrade\Actions\ApplyDecisionAndRegrade;
use App\Filament\Resources\Appeals\AppealResource;
use App\Models\Appeal;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

class ViewAppeal extends ViewRecord
{
    protected static string $resource = AppealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regradePreview')
                ->label('Regrade Preview')
                ->color('gray')
                ->schema([
                    Select::make('scope')
                        ->options([
                            'attempt_item' => 'Attempt Item',
                            'question_version' => 'Question Version',
                        ])
                        ->default('attempt_item')
                        ->required(),
                ])
                ->action(function (Appeal $record, array $data, ApplyDecisionAndRegrade $applyDecisionAndRegrade): void {
                    $affectedCount = $applyDecisionAndRegrade->previewAffectedAttemptCount(
                        scope: (string) ($data['scope'] ?? 'attempt_item'),
                        attemptItem: $record->attemptItem,
                        questionVersion: $record->attemptItem?->questionVersion,
                    );

                    Notification::make()
                        ->title("Affected attempts: {$affectedCount}")
                        ->success()
                        ->send();
                }),
            Action::make('rejectAppeal')
                ->label('Reject')
                ->color('danger')
                ->visible(fn (Appeal $record): bool => in_array($record->status, [Appeal::STATUS_OPEN, Appeal::STATUS_REVIEWING], true))
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('teacher_note')
                        ->label('Teacher Note')
                        ->required(),
                ])
                ->action(function (Appeal $record, array $data, ResolveAppeal $resolveAppeal): void {
                    $teacher = auth()->user();

                    if (! $teacher instanceof \App\Models\User) {
                        return;
                    }

                    $resolveAppeal->execute(
                        appeal: $record,
                        teacher: $teacher,
                        status: Appeal::STATUS_REJECTED,
                        teacherNote: (string) ($data['teacher_note'] ?? ''),
                    );

                    Notification::make()
                        ->title('Appeal rejected.')
                        ->success()
                        ->send();
                }),
            Action::make('resolveAppeal')
                ->label('Resolve & Regrade')
                ->color('success')
                ->visible(fn (Appeal $record): bool => in_array($record->status, [Appeal::STATUS_OPEN, Appeal::STATUS_REVIEWING], true))
                ->schema([
                    Select::make('scope')
                        ->options([
                            'attempt_item' => 'Attempt Item',
                            'question_version' => 'Question Version',
                        ])
                        ->default('attempt_item')
                        ->required(),
                    Select::make('decision_type')
                        ->options([
                            'answer_key_change' => 'Answer Key Change',
                            'rubric_change' => 'Rubric Change',
                            'partial_credit' => 'Partial Credit',
                            'void_question' => 'Void Question',
                        ])
                        ->required(),
                    Textarea::make('payload_json')
                        ->label('Decision Payload (JSON)')
                        ->helperText('Examples: {"new_answer_key":{"correct_choice_id":"B"}}, {"new_points":5,"reason":"Appeal accepted"}, {"mode":"give_full"}')
                        ->default('{}')
                        ->required()
                        ->columnSpanFull(),
                    Textarea::make('teacher_note')
                        ->label('Teacher Note')
                        ->required(),
                ])
                ->action(function (Appeal $record, array $data, ResolveAppeal $resolveAppeal): void {
                    $teacher = auth()->user();

                    if (! $teacher instanceof \App\Models\User) {
                        return;
                    }

                    $payloadJson = (string) ($data['payload_json'] ?? '{}');
                    $payload = json_decode($payloadJson, true);

                    if (! is_array($payload)) {
                        throw ValidationException::withMessages([
                            'payload_json' => 'Payload must be valid JSON.',
                        ]);
                    }

                    $resolveAppeal->execute(
                        appeal: $record,
                        teacher: $teacher,
                        status: Appeal::STATUS_RESOLVED,
                        teacherNote: (string) ($data['teacher_note'] ?? ''),
                        decision: [
                            'scope' => (string) ($data['scope'] ?? 'attempt_item'),
                            'decision_type' => (string) ($data['decision_type'] ?? ''),
                            'payload' => $payload,
                        ],
                    );

                    Notification::make()
                        ->title('Appeal resolved and regrade queued.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
