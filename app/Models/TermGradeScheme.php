<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TermGradeScheme extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'term_id',
        'weights',
        'normalize_strategy',
    ];

    protected function casts(): array
    {
        return [
            'weights' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $scheme): void {
            if (blank($scheme->id)) {
                $scheme->id = (string) Str::uuid();
            }
        });
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
