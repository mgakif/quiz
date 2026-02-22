<?php

declare(strict_types=1);

namespace App\Filament\Resources\PublicExamLinks;

use App\Filament\Resources\PublicExamLinks\Pages\CreatePublicExamLink;
use App\Filament\Resources\PublicExamLinks\Pages\EditPublicExamLink;
use App\Filament\Resources\PublicExamLinks\Pages\ListPublicExamLinks;
use App\Filament\Resources\PublicExamLinks\Schemas\PublicExamLinkForm;
use App\Filament\Resources\PublicExamLinks\Tables\PublicExamLinksTable;
use App\Models\PublicExamLink;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PublicExamLinkResource extends Resource
{
    protected static ?string $model = PublicExamLink::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Public Links';

    protected static string|UnitEnum|null $navigationGroup = 'Gradebook';

    public static function form(Schema $schema): Schema
    {
        return PublicExamLinkForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PublicExamLinksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPublicExamLinks::route('/'),
            'create' => CreatePublicExamLink::route('/create'),
            'edit' => EditPublicExamLink::route('/{record}/edit'),
        ];
    }
}
