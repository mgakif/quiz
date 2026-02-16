<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentProfiles;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class StudentProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('student_id')
                    ->label('Student')
                    ->options(
                        User::query()
                            ->where('role', User::ROLE_STUDENT)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all(),
                    )
                    ->required()
                    ->searchable()
                    ->rules([
                        fn ($record) => Rule::unique('student_profiles', 'student_id')
                            ->ignore($record?->id),
                    ]),
                TextInput::make('class_id')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                TextInput::make('nickname')
                    ->required()
                    ->maxLength(50)
                    ->rules([
                        fn ($get, $record) => Rule::unique('student_profiles', 'nickname')
                            ->where(fn ($query) => $query->where('class_id', (int) ($get('class_id') ?? 0)))
                            ->ignore($record?->id),
                    ]),
                Toggle::make('show_on_leaderboard')
                    ->default(true)
                    ->label('Show on leaderboard'),
            ]);
    }
}
