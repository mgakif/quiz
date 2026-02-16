<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuditEvent extends Model
{
    /** @use HasFactory<\Database\Factories\AuditEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'actor_id',
        'actor_type',
        'event_type',
        'entity_type',
        'entity_id',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            if (blank($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
