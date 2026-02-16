<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RegradeDecision extends Model
{
    /** @use HasFactory<\Database\Factories\RegradeDecisionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'scope',
        'attempt_item_id',
        'question_version_id',
        'decision_type',
        'payload',
        'decided_by',
        'decided_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'decided_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $decision): void {
            if (blank($decision->uuid)) {
                $decision->uuid = (string) Str::uuid();
            }
        });
    }

    public function attemptItem(): BelongsTo
    {
        return $this->belongsTo(AttemptItem::class);
    }

    public function questionVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
