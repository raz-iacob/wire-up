<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\ContentStatus;
use App\Jobs\GenerateOgImage;
use App\Models\Page;
use App\Services\OgImageService;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\URL;

trait HasPublishing
{
    public function previewUrl(?string $locale = null): string
    {
        $expiry = now()->addDays(config()->integer('wireup.draft_preview_days'));

        return URL::temporarySignedRoute($this->previewRouteName(), $expiry, $this->previewRouteParameters($locale));
    }

    public function isLiveInLocale(?string $locale = null): bool
    {
        if ($this->status !== ContentStatus::PUBLISHED
            || $this->published_at === null
            || $this->published_at->isFuture()) {
            return false;
        }

        if (count(resolve('localization')->getActiveLocales()) <= 1) {
            return true;
        }

        return in_array($locale ?? app()->getLocale(), $this->published_locales, true);
    }

    protected static function bootHasPublishing(): void
    {
        static::saved(function (self $model): void {
            if ($model->published_locales === [] || ! config()->boolean('wireup.og_images')) {
                return;
            }

            dispatch(new GenerateOgImage($model instanceof Page ? 'page' : 'record', (int) $model->id))->afterCommit();
        });

        static::deleted(fn (self $model) => resolve(OgImageService::class)->forget($model));
    }

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
