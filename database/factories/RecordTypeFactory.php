<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RecordType;
use App\Services\RecordTypePresets;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RecordType>
 */
final class RecordTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $word = fake()->unique()->word();
        $prefix = Str::slug(Str::plural($word));

        return [
            'key' => $word,
            'slug_prefix' => RecordType::query()->where('slug_prefix', $prefix)->exists()
                ? $prefix.'-'.Str::lower(Str::random(6))
                : $prefix,
            'icon' => 'rectangle-stack',
            'name' => Str::title(Str::plural($word)),
            'fields' => [],
            'breadcrumbs' => false,
            'has_detail_page' => true,
            'has_index_page' => false,
            'sellable' => false,
            'position' => 0,
        ];
    }

    public function sellable(): self
    {
        return $this->state(fn (array $attributes): array => [
            'sellable' => true,
            'fields' => RecordTypePresets::withSellableFields([]),
        ]);
    }
}
