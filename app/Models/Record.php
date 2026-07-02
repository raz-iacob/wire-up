<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Traits\HasBlocks;
use App\Traits\HasMedia;
use App\Traits\HasPublishing;
use App\Traits\HasSlugs;
use App\Traits\HasTranslations;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\RecordFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $record_type_id
 * @property-read array<string, mixed>|null $data
 * @property-read array<string, mixed>|null $metadata
 * @property-read array<int, string> $published_locales
 * @property-read ContentStatus $status
 * @property-read ContentStatus $computed_status
 * @property-read CarbonImmutable|null $published_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read RecordType $recordType
 * @property-read Collection<int, Translation> $translations
 * @property-read string $title
 * @property-read string $description
 * @property-read Collection<int, Slug> $slugs
 * @property-read string $slug
 * @property-read Collection<int, Block> $blocks
 */
final class Record extends Model
{
    /** @use HasFactory<RecordFactory> */
    use HasBlocks, HasFactory, HasMedia, HasPublishing, HasSlugs, HasTranslations;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'record_type_id' => 'integer',
            'data' => 'array',
            'metadata' => 'array',
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RecordType, $this>
     */
    public function recordType(): BelongsTo
    {
        return $this->belongsTo(RecordType::class);
    }

    public function isNoindex(): bool
    {
        return (bool) ($this->metadata['noindex'] ?? false);
    }

    /**
     * @return array<int, string>
     */
    protected function translatedAttributes(): array
    {
        return ['title', 'description'];
    }

    protected function slugBasePath(): string
    {
        $this->loadMissing('recordType');

        return $this->recordType->slug_prefix;
    }
}
