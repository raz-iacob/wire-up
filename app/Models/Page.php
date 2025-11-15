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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read array<string, mixed>|null $metadata
 * @property-read PageStatus $status
 * @property-read CarbonInterface|null $published_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Collection<int, Translation> $translations
 * @property-read Collection<int, Slug> $slugs
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
            'name' => 'string',
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
        return ['title', 'seo_title', 'seo_description'];
    }

    /**
     * @param  Builder<Page>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', PageStatus::PUBLISHED)->where('published_at', '<=', now());
    }
}
