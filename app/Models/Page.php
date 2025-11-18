<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PageStatus;
use App\Traits\HasSlugs;
use App\Traits\HasTranslations;
use Carbon\CarbonInterface;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read array<string, mixed>|null $metadata
 * @property-read PageStatus $status
 * @property-read Carbon|null $published_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Collection<int, Translation> $translations
 * @property-read string $title
 * @property-read string $description
 * @property-read Collection<int, Slug> $slugs
 * @property-read string $slug
 */
final class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasSlugs, HasTranslations;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'metadata' => 'array',
            'status' => PageStatus::class,
            'published_at' => 'datetime',
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

    /**
     * @return Attribute<PageStatus, null>
     */
    protected function computedStatus(): Attribute
    {
        return Attribute::get(function (): PageStatus {
            if ($this->status === PageStatus::PUBLISHED && $this->published_at?->isFuture()) {
                return PageStatus::SCHEDULED;
            }

            return $this->status;
        });
    }

    /**
     * @param  Builder<Page>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', PageStatus::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
