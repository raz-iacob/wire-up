<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasMedia;
use App\Traits\HasTranslations;
use Carbon\CarbonInterface;
use Database\Factories\SettingsFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * @property-read int $id
 * @property array<string, mixed>|null $metadata
 * @property-read Collection<int, Translation> $translations
 * @property-read string $title
 * @property-read string $description
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class Settings extends Model
{
    /** @use HasFactory<SettingsFactory> */
    use HasFactory, HasMedia, HasTranslations;

    public static function current(): self
    {
        return self::query()->firstOrCreate([]);
    }

    public static function cached(): ?self
    {
        return once(fn (): ?self => Schema::hasTable('settings')
            ? self::query()->with(['translations', 'media'])->first()
            : null);
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function translatedAttributes(): array
    {
        return ['title', 'description'];
    }
}
