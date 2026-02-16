<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Assessment extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'term_id',
        'legacy_exam_id',
        'class_id',
        'title',
        'category',
        'weight',
        'max_points',
        'scheduled_at',
        'published',
    ];

    protected function casts(): array
    {
        return [
            'class_id' => 'integer',
            'legacy_exam_id' => 'integer',
            'weight' => 'decimal:2',
            'max_points' => 'decimal:2',
            'published' => 'boolean',
            'scheduled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $assessment): void {
            if (blank($assessment->id)) {
                $assessment->id = (string) Str::uuid();
            }
        });
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class, 'exam_id', 'legacy_exam_id');
    }
}
