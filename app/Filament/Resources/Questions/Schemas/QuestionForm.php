<?php

declare(strict_types=1);

namespace App\Filament\Resources\Questions\Schemas;

use App\Filament\Resources\Questions\QuestionResource;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class QuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('status')
                    ->options(QuestionResource::statusOptions())
                    ->default('active')
                    ->required(),
                TextInput::make('difficulty')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(5),
                TagsInput::make('tags'),
                Hidden::make('created_by')
                    ->default(fn (): ?int => auth()->id()),
                Select::make('latest_type')
                    ->label('Type')
                    ->options(QuestionResource::typeOptions())
                    ->required()
                    ->disabledOn('edit'),
                CodeEditor::make('latest_payload')
                    ->label('Payload (JSON)')
                    ->language(Language::Json)
                    ->required()
                    ->columnSpanFull()
                    ->helperText('Temporary raw JSON editor.')
                    ->disabledOn('edit'),
                CodeEditor::make('latest_answer_key')
                    ->label('Answer Key (JSON)')
                    ->language(Language::Json)
                    ->required()
                    ->columnSpanFull()
                    ->helperText('Temporary raw JSON editor.')
                    ->disabledOn('edit'),
                CodeEditor::make('latest_rubric')
                    ->label('Rubric (JSON)')
                    ->language(Language::Json)
                    ->columnSpanFull()
                    ->helperText('Optional. Typically used for short/essay questions.')
                    ->disabledOn('edit'),
            ]);
    }
}
