<?php

declare(strict_types=1);

namespace App\Filament\Resources\Appeals;

use App\Filament\Resources\Appeals\Pages\ListAppeals;
use App\Filament\Resources\Appeals\Pages\ViewAppeal;
use App\Filament\Resources\Appeals\Schemas\AppealInfolist;
use App\Filament\Resources\Appeals\Tables\AppealsTable;
use App\Models\Appeal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AppealResource extends Resource
{
    protected static ?string $model = Appeal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static ?string $navigationLabel = 'Appeals';

    public static function infolist(Schema $schema): Schema
    {
        return AppealInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AppealsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppeals::route('/'),
            'view' => ViewAppeal::route('/{record}'),
        ];
    }
}
