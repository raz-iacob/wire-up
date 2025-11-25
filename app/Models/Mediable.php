<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\MediableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $media_id
 * @property int $mediable_id
 * @property string $mediable_type
 * @property string $locale
 * @property string|null $role
 * @property array<string, mixed>|null $crop
 * @property array<string, mixed>|null $metadata
 * @property int|null $position
 * @property bool $published
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Media $media
 * @property-read Model $mediable
 */
final class Mediable extends Model
{
    /** @use HasFactory<MediableFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'media_id' => 'integer',
            'mediable_id' => 'integer',
            'mediable_type' => 'string',
            'locale' => 'string',
            'role' => 'string',
            'crop' => 'array',
            'metadata' => 'array',
            'position' => 'integer',
            'published' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
