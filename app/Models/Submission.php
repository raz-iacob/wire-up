<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Locale;

/**
 * @property-read int $id
 * @property-read int|null $page_id
 * @property-read int|null $block_id
 * @property-read string $type
 * @property-read string|null $form_name
 * @property-read string|null $name
 * @property-read string|null $email
 * @property-read string|null $phone
 * @property-read string|null $subject
 * @property-read string|null $message
 * @property-read array<string, mixed>|null $metadata
 * @property-read string|null $ip
 * @property-read string|null $locale
 * @property-read string|null $country
 * @property-read CarbonInterface|null $read_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'page_id' => 'integer',
            'block_id' => 'integer',
            'metadata' => 'array',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function countryName(): ?string
    {
        if ($this->country === null || $this->country === '') {
            return null;
        }

        return Locale::getDisplayRegion('-'.$this->country, app()->getLocale());
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<Block, $this>
     */
    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    /**
     * @param  Builder<$this>  $query
     */
    protected function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }
}
