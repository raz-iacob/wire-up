<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\FieldType;
use App\Enums\MediaType;
use App\Services\ImageService;
use App\Services\RecordTypePresets;
use App\Services\SettingsService;
use App\Traits\ClearsPageCache;
use App\Traits\HasBlocks;
use App\Traits\HasCategories;
use App\Traits\HasMedia;
use App\Traits\HasPublishing;
use App\Traits\HasRevisions;
use App\Traits\HasSeo;
use App\Traits\HasSlugs;
use App\Traits\HasTranslations;
use App\Traits\HasUserstamps;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\RecordFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read int $record_type_id
 * @property-read array<string, mixed>|null $data
 * @property-read int|null $stock
 * @property-read array<string, mixed>|null $metadata
 * @property-read array<int, string> $published_locales
 * @property-read ContentStatus $status
 * @property-read ContentStatus $computed_status
 * @property-read CarbonImmutable|null $published_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read RecordType $recordType
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, Revision> $revisions
 * @property-read Collection<int, Translation> $translations
 * @property-read string $title
 * @property-read string $description
 * @property-read Collection<int, Slug> $slugs
 * @property-read string $slug
 * @property-read Collection<int, Block> $blocks
 * @property-read User|null $creator
 * @property-read User|null $editor
 */
final class Record extends Model
{
    /** @use HasFactory<RecordFactory> */
    use ClearsPageCache, HasBlocks, HasCategories, HasFactory, HasMedia, HasPublishing, HasRevisions, HasSeo, HasSlugs, HasTranslations, HasUserstamps;

    /**
     * @return array<int, int>|null
     */
    public static function normalizeIds(mixed $value): ?array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, is_array($value) ? $value : [$value]),
            fn (int $id): bool => $id > 0,
        )));

        return $ids === [] ? null : $ids;
    }

    /**
     * @param  array<int, string>|null  $allowed
     * @return array<int, string>|null
     */
    public static function normalizeList(mixed $value, ?array $allowed = null): ?array
    {
        $items = is_array($value) ? $value : explode(',', is_scalar($value) ? (string) $value : '');

        $clean = array_values(array_unique(array_filter(
            array_map(fn (mixed $item): string => is_scalar($item) ? mb_trim((string) $item) : '', $items),
            fn (string $item): bool => $item !== '' && ($allowed === null || in_array($item, $allowed, true)),
        )));

        return $clean === [] ? null : $clean;
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'record_type_id' => 'integer',
            'data' => 'array',
            'stock' => 'integer',
            'metadata' => 'array',
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
            'created_by' => 'integer',
            'updated_by' => 'integer',
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

    public function hasDetailPage(): bool
    {
        $this->loadMissing('recordType');

        return $this->recordType->has_detail_page;
    }

    public function getUrl(?string $locale = null): string
    {
        $this->loadMissing('recordType');

        return route('record', [$this->recordType->slug_prefix, $this->getSlug($locale)]);
    }

    /**
     * @return array<string, string>
     */
    public function previewRouteParameters(?string $locale = null): array
    {
        $this->loadMissing('recordType');

        return ['recordType' => $this->recordType->slug_prefix, 'slug' => $this->getSlug($locale)];
    }

    public function previewRouteName(): string
    {
        return 'record';
    }

    public function plainText(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $this->loadMissing('blocks');

        $overview = data_get($this->data, "overview.{$locale}");
        $parts = [is_string($overview) ? html_entity_decode(strip_tags($overview), ENT_QUOTES | ENT_HTML5, 'UTF-8') : ''];

        foreach ($this->blocks as $block) {
            $parts[] = $block->plainText($locale);
        }

        return (string) str(collect($parts)->filter()->implode(' '))->squish();
    }

    public function displayHeading(): string
    {
        $heading = $this->fieldValue('heading', true);

        return is_string($heading) && mb_trim($heading) !== '' ? $heading : $this->title;
    }

    public function displayExcerpt(int $limit = 160): string
    {
        $overview = $this->fieldValue('overview', true);
        $source = is_string($overview) && mb_trim($overview) !== '' ? $overview : $this->description;

        return str(html_entity_decode(strip_tags((string) $source), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            ->squish()
            ->limit($limit)
            ->value();
    }

    /**
     * @param  array<string, mixed>  $field
     */
    public function columnValue(array $field, bool $publishedOnly = false): string
    {
        $type = FieldType::tryFrom($field['type']);
        $key = $field['key'];
        $locale = app()->getLocale();

        if ($type === FieldType::RELATIONSHIP) {
            $titles = $this->linkedRecords($field, $publishedOnly)->map(fn (self $record): string => $record->title)->implode(', ');

            return $titles !== '' ? str($titles)->limit(60)->value() : '—';
        }

        if ($type?->isMedia()) {
            return (string) $this->media
                ->filter(fn (Media $media): bool => $media->pivot->role === $key)
                ->count();
        }

        $value = ($field['translatable'] ?? false)
            ? data_get($this->data, "{$key}.{$locale}")
            : data_get($this->data, $key);

        if (is_array($value)) {
            $value = implode(', ', array_filter($value, is_scalar(...)));
        }

        if ($value === null || $value === '') {
            return '—';
        }

        return match ($type) {
            FieldType::BOOLEAN => $value ? __('Yes') : __('No'),
            FieldType::MONEY => SettingsService::current()->formatMoney(is_numeric($value) ? $value : null),
            FieldType::DATE => str($value)->substr(0, 10)->value(),
            FieldType::DATETIME => str($value)->replace('T', ' ')->substr(0, 16)->value(),
            FieldType::RICH_TEXT => str(strip_tags((string) $value))->squish()->limit(60)->value(),
            default => str((string) $value)->limit(60)->value(),
        };
    }

    /**
     * @param  array<string, mixed>  $field
     * @return Collection<int, self>
     */
    public function linkedRecords(array $field, bool $publishedOnly = true): Collection
    {
        $stored = $this->data[(string) $field['key']] ?? null;
        $ids = array_values(array_filter(array_map(intval(...), is_array($stored) ? $stored : [])));

        if ($ids === []) {
            return new Collection;
        }

        return self::query()
            ->whereKey($ids)
            ->whereHas('recordType', fn (Builder $query): Builder => $query
                ->where('key', (string) ($field['record_type'] ?? ''))
                ->when($publishedOnly, fn (Builder $query): Builder => $query->where('admin_only', false)))
            ->when($publishedOnly, fn (Builder $query): Builder => $query->publishedInLocale())
            ->with(['translations', 'slugs', 'recordType'])
            ->get()
            ->sortBy(fn (self $record): int => (int) array_search($record->id, $ids, true))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $field
     */
    public function hasFieldContent(array $field): bool
    {
        $key = (string) ($field['key'] ?? '');
        $translatable = (bool) ($field['translatable'] ?? false);
        $type = FieldType::tryFrom((string) ($field['type'] ?? ''));

        if ($key === 'heading') {
            return true;
        }

        if ($key === RecordTypePresets::PRICE_FIELD) {
            return is_numeric($this->fieldValue($key, false))
                || (bool) $this->fieldValue('sold', false)
                || $this->stock === 0;
        }

        if ($type?->isMedia() ?? false) {
            return $this->fieldMedia($key, $translatable) !== [];
        }

        if ($type === FieldType::RELATIONSHIP) {
            return $this->linkedRecords($field)->isNotEmpty();
        }

        $value = $this->fieldValue($key, $translatable);

        if (is_array($value)) {
            return array_filter($value, fn (mixed $item): bool => is_string($item) && mb_trim($item) !== '') !== [];
        }

        if (is_bool($value)) {
            return true;
        }

        $hasText = is_scalar($value) && mb_trim(strip_tags((string) $value)) !== '';

        return $hasText || ($key === 'overview' && mb_trim($this->description) !== '');
    }

    public function fieldValue(string $key, bool $translatable): mixed
    {
        $value = $this->data[$key] ?? null;

        if (! $translatable) {
            return $value;
        }

        return is_array($value) ? ($value[app()->getLocale()] ?? null) : null;
    }

    /**
     * @return array<int, Media>
     */
    public function fieldMedia(string $key, bool $translatable): array
    {
        $locale = $translatable ? app()->getLocale() : resolve('localization')->getDefaultLocale();

        return $this->media
            ->filter(fn (Media $media): bool => ! $media->is_private && $media->pivot->role === $key && $media->pivot->locale === $locale)
            ->sortBy(fn (Media $media): int => (int) $media->pivot->position)
            ->values()
            ->all();
    }

    public function primaryImageUrl(int $width = 800): ?string
    {
        $this->loadMissing('recordType', 'media');

        foreach ($this->recordType->fields as $field) {
            $type = FieldType::tryFrom((string) ($field['type'] ?? ''));

            if ($type === null) {
                continue;
            }

            if (! in_array(MediaType::IMAGE->value, $type->acceptsMedia(), true)) {
                continue;
            }

            foreach ($this->fieldMedia((string) ($field['key'] ?? ''), (bool) ($field['translatable'] ?? false)) as $media) {
                if ($media->type !== MediaType::IMAGE) {
                    continue;
                }

                $cropSet = is_array($media->pivot->crop ?? null) ? $media->pivot->crop : [];
                $crop = is_array($cropSet['default'] ?? null) ? $cropSet['default'] : [];
                $options = ["w={$width}"];

                if (($crop['crop_w'] ?? 0) > 0 && ($crop['crop_h'] ?? 0) > 0) {
                    $options[] = sprintf('crop=%d-%d-%d-%d', $crop['crop_w'], $crop['crop_h'], $crop['crop_x'] ?? 0, $crop['crop_y'] ?? 0);
                }

                return ImageService::url(implode(',', $options), $media->source);
            }
        }

        return null;
    }

    /**
     * @return array{hideHeader: bool, hideFooter: bool, backgroundColor: ?string, backgroundImage: ?string, backgroundFixed: bool, customCss: string, sidebar: array{menus: array<int, string>}}
     */
    public function resolvedLayout(): array
    {
        return Page::normalizeLayout(is_array($this->metadata['layout'] ?? null) ? $this->metadata['layout'] : []);
    }

    /**
     * @param  Builder<Record>  $query
     */
    #[Scope]
    protected function matchingSearch(Builder $query, string $search, RecordType $recordType): void
    {
        if ($search === '') {
            return;
        }

        $locale = app()->getLocale();

        $query->where(function (Builder $inner) use ($search, $recordType, $locale): void {
            $inner->whereTranslationLike('title', $search)
                ->orWhereLike("data->heading->{$locale}", "%{$search}%");

            foreach ($recordType->searchableFields() as $field) {
                $path = ($field['translatable'] ?? false)
                    ? "data->{$field['key']}->{$locale}"
                    : "data->{$field['key']}";

                $inner->orWhereLike($path, "%{$search}%");
            }
        });
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
