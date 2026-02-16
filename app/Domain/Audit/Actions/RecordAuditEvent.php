<?php

declare(strict_types=1);

namespace App\Domain\Audit\Actions;

use App\Models\AuditEvent;
use App\Models\User;

class RecordAuditEvent
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function execute(
        ?User $actor,
        string $actorType,
        string $eventType,
        string $entityType,
        string|int $entityId,
        ?array $meta = null,
    ): AuditEvent {
        return AuditEvent::query()->create([
            'actor_id' => $actor?->id,
            'actor_type' => $actorType,
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => (string) $entityId,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}
