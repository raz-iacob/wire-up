<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ActivityEvent;
use App\Enums\RevisionSource;
use App\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
final class ActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'impersonator_id' => null,
            'source' => RevisionSource::SYSTEM,
            'event' => ActivityEvent::SETTINGS_UPDATED,
            'subject_type' => null,
            'subject_id' => null,
            'subject_label' => null,
            'properties' => null,
            'ip' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }
}
