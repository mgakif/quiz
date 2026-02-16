<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuestionVersions;

use App\Filament\Resources\QuestionVersions\Pages\ListQuestionVersions;
use App\Filament\Resources\QuestionVersions\Pages\ViewQuestionVersion;
use App\Filament\Resources\QuestionVersions\Schemas\QuestionVersionInfolist;
use App\Filament\Resources\QuestionVersions\Tables\QuestionVersionsTable;
use App\Models\QuestionVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class QuestionVersionResource extends Resource
{
    protected static ?string $model = QuestionVersion::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Question Reviews';

    protected static string | UnitEnum | null $navigationGroup = 'Question Bank';

    public static function infolist(Schema $schema): Schema
    {
        return QuestionVersionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuestionVersionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuestionVersions::route('/'),
            'view' => ViewQuestionVersion::route('/{record}'),
        ];
    }
}
