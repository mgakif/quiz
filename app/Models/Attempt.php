<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'student_id',
        'guest_id',
        'public_exam_link_id',
        'started_at',
        'submitted_at',
        'grade_state',
        'release_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'release_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(GuestUser::class, 'guest_id');
    }

    public function publicExamLink(): BelongsTo
    {
        return $this->belongsTo(PublicExamLink::class, 'public_exam_link_id');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'exam_id', 'legacy_exam_id');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id', 'id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AttemptItem::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $attempt): void {
            $hasStudent = $attempt->student_id !== null;
            $hasGuest = $attempt->guest_id !== null;

            if ($hasStudent === $hasGuest) {
                throw new \InvalidArgumentException('Exactly one of student_id or guest_id must be set.');
            }
        });
    }
}
