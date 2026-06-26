<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PageStatus;
use App\Traits\HasBlocks;
use App\Traits\HasMedia;
use App\Traits\HasSlugs;
use App\Traits\HasTranslations;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 * @property-read array<string, mixed>|null $metadata
 * @property-read array<int, string> $published_locales
 * @property-read PageStatus $status
 * @property-read CarbonImmutable|null $published_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Collection<int, Translation> $translations
 * @property-read string $title
 * @property-read string $description
 * @property-read Collection<int, Slug> $slugs
 * @property-read string $slug
 * @property-read Collection<int, Block> $blocks
 */
final class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasBlocks, HasFactory, HasMedia, HasSlugs, HasTranslations;

    /**
     * @param  array<string, mixed>  $layout
     * @return array{hideHeader: bool, hideFooter: bool, backgroundColor: ?string, backgroundImage: ?string, backgroundFixed: bool, customCss: string, sidebar: array{menus: array<int, string>}}
     */
    public static function normalizeLayout(array $layout): array
    {
        $color = mb_trim((string) ($layout['backgroundColor'] ?? ''));

        return [
            'hideHeader' => (bool) ($layout['hideHeader'] ?? false),
            'hideFooter' => (bool) ($layout['hideFooter'] ?? false),
            'backgroundColor' => $color !== '' ? $color : null,
            'backgroundImage' => self::backgroundImageUrl($layout['backgroundImage'] ?? null),
            'backgroundFixed' => (bool) ($layout['backgroundFixed'] ?? false),
            'customCss' => self::sanitizeCustomCss((string) ($layout['customCss'] ?? '')),
            'sidebar' => self::normalizeSidebar($layout['sidebar'] ?? null),
        ];
    }

    /**
     * @return array{menus: array<int, string>}
     */
    public static function normalizeSidebar(mixed $sidebar): array
    {
        $sidebar = is_array($sidebar) ? $sidebar : [];

        $menus = array_values(array_filter(
            is_array($sidebar['menus'] ?? null) ? $sidebar['menus'] : [],
            fn (mixed $key): bool => is_string($key) && $key !== '',
        ));

        return [
            'menus' => $menus,
        ];
    }

    public static function sanitizeCustomCss(string $css): string
    {
        return str_ireplace('</style', '', mb_trim($css));
    }

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

    public function getUrl(?string $locale = null): string
    {
        return route('page', $this->getSlug($locale));
    }

    /**
     * @return array{hideHeader: bool, hideFooter: bool, backgroundColor: ?string, backgroundImage: ?string, backgroundFixed: bool, customCss: string, sidebar: array{menus: array<int, string>}}
     */
    public function resolvedLayout(): array
    {
        return self::normalizeLayout(is_array($this->metadata['layout'] ?? null) ? $this->metadata['layout'] : []);
    }

    /**
     * @return array<int, string>
     */
    protected function translatedAttributes(): array
    {
        return ['title', 'description'];
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

    /**
     * @param  Builder<Page>  $query
     */
    #[Scope]
    protected function publishedInLocale(Builder $query, ?string $locale = null): void
    {
        $query->published();

        if (count(resolve('localization')->getActiveLocales()) > 1) {
            $query->whereJsonContains('metadata->published_locales', $locale ?? app()->getLocale());
        }
    }

    private static function backgroundImageUrl(mixed $image): ?string
    {
        if (! is_array($image) || empty($image['source'])) {
            return null;
        }

        /** @var array<string, int> $crop */
        $crop = is_array($image['crop']['default'] ?? null) ? $image['crop']['default'] : [];

        $optionParts = ['w=1920', 'q=80', 'fm=jpg'];

        if (($crop['crop_w'] ?? 0) > 0 && ($crop['crop_h'] ?? 0) > 0) {
            $optionParts[] = sprintf('crop=%d-%d-%d-%d', $crop['crop_w'], $crop['crop_h'], $crop['crop_x'] ?? 0, $crop['crop_y'] ?? 0);
        }

        return route('image.show', [
            'options' => implode(',', $optionParts),
            'path' => $image['source'],
        ]);
    }
}
