<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\QuestionGeneration;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class Generations extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Generations';

    protected static ?string $title = 'Generations';

    protected string $view = 'filament.pages.generations';

    public function table(Table $table): Table
    {
        return $table
            ->query(QuestionGeneration::query())
            ->columns([
                TextColumn::make('status')->badge(),
                TextColumn::make('generated_count')->label('Generated')->numeric(),
                TextColumn::make('model')->placeholder('-'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ]);
    }
}
