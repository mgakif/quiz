<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QuestionGeneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'status',
        'model',
        'generated_count',
        'blueprint',
        'raw_output',
        'validation_errors',
        'summary',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'blueprint' => 'array',
            'validation_errors' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $generation): void {
            if (blank($generation->uuid)) {
                $generation->uuid = (string) Str::uuid();
            }
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
