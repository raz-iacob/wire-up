<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property-read int $id
 * @property-read string $key
 * @property-read string|null $body
 * @property-read string $locale
 * @property-read int $translatable_id
 * @property-read string $translatable_type
 * @property-read Model $translatable
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class Translation extends Model
{
    /** @use HasFactory<TranslationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'key' => 'string',
            'body' => 'string',
            'locale' => 'string',
            'translatable_id' => 'integer',
            'translatable_type' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}
