<?php

declare(strict_types=1);

namespace App\Filament\Resources\RegradeDecisions;

use App\Filament\Resources\RegradeDecisions\Pages\ListRegradeDecisions;
use App\Filament\Resources\RegradeDecisions\Tables\RegradeDecisionsTable;
use App\Models\RegradeDecision;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RegradeDecisionResource extends Resource
{
    protected static ?string $model = RegradeDecision::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $navigationLabel = 'Regrade Decisions';

    public static function table(Table $table): Table
    {
        return RegradeDecisionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegradeDecisions::route('/'),
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
}
