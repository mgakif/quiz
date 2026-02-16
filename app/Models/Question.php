<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Question extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_DEPRECATED = 'deprecated';

    protected $fillable = [
        'uuid',
        'status',
        'difficulty',
        'tags',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $question): void {
            if (blank($question->uuid)) {
                $question->uuid = (string) Str::uuid();
            }
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(QuestionVersion::class);
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(QuestionVersion::class)->latestOfMany('version');
    }

    public function stats(): HasMany
    {
        return $this->hasMany(QuestionStat::class);
    }

    public function scopeSelectable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function archive(): void
    {
        $this->update(['status' => self::STATUS_ARCHIVED]);
    }

    public function deprecate(): void
    {
        $this->update(['status' => self::STATUS_DEPRECATED]);
    }

    public function createVersion(array $attributes): QuestionVersion
    {
        $nextVersion = (int) $this->versions()->max('version') + 1;

        return $this->versions()->create([
            'version' => $nextVersion,
            ...$attributes,
        ]);
    }
}
