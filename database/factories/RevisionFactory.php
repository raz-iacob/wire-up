<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RevisionSource;
use App\Models\Page;
use App\Models\Revision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Revision>
 */
final class RevisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'revisionable_type' => 'page',
            'revisionable_id' => Page::factory(),
            'user_id' => null,
            'source' => RevisionSource::EDITOR,
            'hash' => hash('sha256', 'revision'),
            'content' => ['title' => ['en' => 'Revision']],
            'created_at' => now(),
        ];
    }
}
