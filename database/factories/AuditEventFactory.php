<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditEvent>
 */
class AuditEventFactory extends Factory
{
    public function definition(): array
    {
        $actor = User::factory()->teacher()->create();

        return [
            'uuid' => fake()->uuid(),
            'actor_id' => $actor->id,
            'actor_type' => 'teacher',
            'event_type' => 'audit.test',
            'entity_type' => 'test_entity',
            'entity_id' => fake()->uuid(),
            'meta' => ['sample' => true],
            'created_at' => now(),
        ];
    }
}
