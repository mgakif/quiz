<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PublicExamLink extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'exam_id',
        'token',
        'is_enabled',
        'max_attempts',
        'expires_at',
        'require_name',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'exam_id' => 'integer',
            'is_enabled' => 'boolean',
            'max_attempts' => 'integer',
            'expires_at' => 'datetime',
            'require_name' => 'boolean',
            'created_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $link): void {
            if (blank($link->id)) {
                $link->id = (string) Str::uuid();
            }

            if (blank($link->token)) {
                $link->token = bin2hex(random_bytes(32));
            }
        });
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class, 'public_exam_link_id');
    }

    public function isActive(): bool
    {
        if (! $this->is_enabled) {
            return false;
        }

        if ($this->expires_at !== null && now()->greaterThan($this->expires_at)) {
            return false;
        }

        if ($this->max_attempts !== null && $this->attempts()->count() >= $this->max_attempts) {
            return false;
        }

        return true;
    }
}
