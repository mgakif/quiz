<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class QuestionVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'question_id',
        'version',
        'type',
        'payload',
        'answer_key',
        'rubric',
        'reviewer_status',
        'reviewer_issues',
        'reviewer_summary',
        'reviewer_override_by',
        'reviewer_overridden_at',
        'reviewer_override_note',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'answer_key' => 'array',
            'rubric' => 'array',
            'reviewer_issues' => 'array',
            'reviewer_overridden_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            if (blank($version->uuid)) {
                $version->uuid = (string) Str::uuid();
            }

            $version->reviewer_status ??= 'pending';
        });
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function attemptItems(): HasMany
    {
        return $this->hasMany(AttemptItem::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(QuestionStat::class);
    }

    public function reviewerOverrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_override_by');
    }
}
