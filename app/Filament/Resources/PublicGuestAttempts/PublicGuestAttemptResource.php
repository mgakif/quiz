<?php

declare(strict_types=1);

namespace App\Filament\Resources\PublicGuestAttempts;

use App\Filament\Resources\PublicGuestAttempts\Pages\ListPublicGuestAttempts;
use App\Filament\Resources\PublicGuestAttempts\Tables\PublicGuestAttemptsTable;
use App\Models\Attempt;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PublicGuestAttemptResource extends Resource
{
    protected static ?string $model = Attempt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Public Attempts';

    protected static string|UnitEnum|null $navigationGroup = 'Gradebook';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return PublicGuestAttemptsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPublicGuestAttempts::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isTeacher() ?? false;
    }
}
