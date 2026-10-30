<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RevisionSource;
use Carbon\CarbonInterface;
use Database\Factories\RevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property-read int $id
 * @property-read string $revisionable_type
 * @property-read int $revisionable_id
 * @property-read int|null $user_id
 * @property-read RevisionSource $source
 * @property-read string $hash
 * @property-read array<string, mixed> $content
 * @property-read CarbonInterface $created_at
 * @property-read User|null $user
 */
final class Revision extends Model
{
    /** @use HasFactory<RevisionFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'revisionable_id' => 'integer',
            'user_id' => 'integer',
            'source' => RevisionSource::class,
            'content' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function authorName(): string
    {
        return match ($this->source) {
            RevisionSource::EDITOR => $this->user?->visibleName() ?? __('Someone'),
            default => $this->source->label(),
        };
    }
}
