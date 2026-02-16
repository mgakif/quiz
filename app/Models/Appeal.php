<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Appeal extends Model
{
    /** @use HasFactory<\Database\Factories\AppealFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'uuid',
        'attempt_item_id',
        'student_id',
        'reason_text',
        'status',
        'teacher_note',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $appeal): void {
            if (blank($appeal->uuid)) {
                $appeal->uuid = (string) Str::uuid();
            }
        });
    }

    public function attemptItem(): BelongsTo
    {
        return $this->belongsTo(AttemptItem::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
