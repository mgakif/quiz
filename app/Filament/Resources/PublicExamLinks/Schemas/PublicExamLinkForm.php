<?php

declare(strict_types=1);

namespace App\Filament\Resources\PublicExamLinks\Schemas;

use App\Models\Exam;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PublicExamLinkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Link Settings')
                    ->schema([
                        Select::make('exam_id')
                            ->label('Exam')
                            ->options(Exam::query()->orderByDesc('id')->pluck('title', 'id')->all())
                            ->searchable()
                            ->required(),
                        Toggle::make('is_enabled')
                            ->default(true)
                            ->required(),
                        TextInput::make('max_attempts')
                            ->numeric()
                            ->minValue(1),
                        DateTimePicker::make('expires_at')
                            ->seconds(false),
                        Toggle::make('require_name')
                            ->default(true)
                            ->required(),
                        TextInput::make('token')
                            ->maxLength(128)
                            ->helperText('Leave empty to auto-generate a secure token.'),
                    ])
                    ->columns(2),
            ]);
    }
}
