<?php

declare(strict_types=1);

namespace App\Filament\Resources\Questions;

use App\Filament\Resources\Questions\Pages\CreateQuestion;
use App\Filament\Resources\Questions\Pages\EditQuestion;
use App\Filament\Resources\Questions\Pages\ListQuestions;
use App\Filament\Resources\Questions\Schemas\QuestionForm;
use App\Filament\Resources\Questions\Tables\QuestionsTable;
use App\Models\Question;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use UnitEnum;
use JsonException;

class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'Question Bank';

    public static function form(Schema $schema): Schema
    {
        return QuestionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuestionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuestions::route('/'),
            'create' => CreateQuestion::route('/create'),
            'edit' => EditQuestion::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            Question::STATUS_DRAFT => 'Draft',
            Question::STATUS_ACTIVE => 'Active',
            Question::STATUS_ARCHIVED => 'Archived',
            Question::STATUS_DEPRECATED => 'Deprecated',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            'mcq' => 'MCQ',
            'matching' => 'Matching',
            'short' => 'Short',
            'essay' => 'Essay',
        ];
    }

    /**
     * @throws ValidationException
     */
    public static function decodeJson(?string $value, string $field, bool $nullable = false): ?array
    {
        if (blank($value)) {
            return $nullable ? null : [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                $field => 'Please provide valid JSON.',
            ]);
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                $field => 'JSON value must decode to an object or array.',
            ]);
        }

        return $decoded;
    }

    public static function encodeJson(?array $value): string
    {
        if ($value === null) {
            return '';
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
