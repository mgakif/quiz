<?php

declare(strict_types=1);

namespace App\Filament\Resources\Exams\Schemas;

use App\Models\Term;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class ExamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Exam')
                    ->schema([
                        TextInput::make('id')
                            ->label('Exam ID')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->disabledOn('edit')
                            ->rules([
                                fn ($record) => Rule::unique('exams', 'id')->ignore($record?->id),
                            ]),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('class_id')
                            ->label('Class ID')
                            ->numeric()
                            ->minValue(1),
                        DateTimePicker::make('scheduled_at')
                            ->seconds(false),
                    ])
                    ->columns(2),
                Section::make('Assessment')
                    ->schema([
                        Select::make('term_id')
                            ->label('Term')
                            ->options(
                                Term::query()
                                    ->orderByDesc('is_active')
                                    ->orderByDesc('start_date')
                                    ->pluck('name', 'id')
                                    ->all(),
                            )
                            ->default(function (): ?string {
                                $defaultTerm = Term::query()
                                    ->where('is_active', true)
                                    ->orderByDesc('start_date')
                                    ->first()
                                    ?? Term::query()->orderByDesc('start_date')->first();

                                return $defaultTerm?->id;
                            })
                            ->required()
                            ->searchable(),
                        Select::make('category')
                            ->options([
                                'quiz' => 'Quiz',
                                'exam' => 'Exam',
                                'assignment' => 'Assignment',
                                'participation' => 'Participation',
                            ])
                            ->default('quiz')
                            ->required(),
                        TextInput::make('weight')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->default(1.00)
                            ->required(),
                        Toggle::make('published')
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }
}
