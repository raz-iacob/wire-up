<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasPublishing
{
    /**
     * @return Attribute<array<int, string>, never>
     */
    protected function publishedLocales(): Attribute
    {
        return Attribute::get(function (): array {
            /** @var array<int, string> $locales */
            $locales = $this->metadata['published_locales'] ?? [];

            return $locales;
        });
    }

    /**
     * @return Attribute<ContentStatus, null>
     */
    protected function computedStatus(): Attribute
    {
        return Attribute::get(function (): ContentStatus {
            if ($this->status === ContentStatus::PUBLISHED && $this->published_at?->isFuture()) {
                return ContentStatus::SCHEDULED;
            }

            return $this->status;
        });
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', ContentStatus::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function publishedInLocale(Builder $query, ?string $locale = null): void
    {
        $query->published();

        if (count(resolve('localization')->getActiveLocales()) > 1) {
            $query->whereJsonContains('metadata->published_locales', $locale ?? app()->getLocale());
        }
    }
}
