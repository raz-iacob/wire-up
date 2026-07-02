<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Record;
use App\Models\RecordType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Record>
 */
final class RecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'record_type_id' => RecordType::factory(),
            'data' => [],
            'metadata' => ['published_locales' => [config()->string('app.default_locale', 'en')]],
            'status' => ContentStatus::DRAFT,
            'published_at' => null,
        ];
    }
}
