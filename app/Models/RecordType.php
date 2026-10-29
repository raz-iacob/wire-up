<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FieldPlacement;
use App\Enums\FieldType;
use App\Enums\MediaType;
use App\Traits\ClearsPageCache;
use Carbon\CarbonInterface;
use Database\Factories\RecordTypeFactory;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Once;

/**
 * @property-read int $id
 * @property-read string $key
 * @property-read string $slug_prefix
 * @property-read string $icon
 * @property-read string $name
 * @property-read array<int, array<string, mixed>> $fields
 * @property-read bool $breadcrumbs
 * @property-read bool $has_detail_page
 * @property-read bool $has_index_page
 * @property-read bool $sellable
 * @property-read bool $admin_only
 * @property-read int $position
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Collection<int, Record> $records
 */
#[RouteKey('key')]
final class RecordType extends Model
{
    /** @use HasFactory<RecordTypeFactory> */
    use ClearsPageCache, HasFactory;

    private const array TEMPLATE_PLACEMENTS = [
        'product' => ['gallery' => 'main', 'heading' => 'sidebar', 'overview' => 'sidebar', 'current_price' => 'sidebar'],
        'event' => ['heading' => 'main', 'overview' => 'main', 'starts_at' => 'main', 'location' => 'main', 'photo' => 'sidebar'],
        'project' => ['heading' => 'main', 'overview' => 'main', 'client' => 'main', 'link' => 'main'],
        'team-member' => ['heading' => 'main', 'overview' => 'main', 'role' => 'main', 'photo' => 'sidebar'],
    ];

    private const array DEFAULT_TEMPLATE_PLACEMENTS = ['heading' => 'main', 'overview' => 'main', 'photo' => 'sidebar'];

    /**
     * @var list<string>
     */
    protected $with = ['translations'];

    /**
     * @return Collection<int, self>
     */
    public static function ordered(): Collection
    {
        return once(fn (): Collection => self::query()->orderBy('position')->get());
    }

    /**
     * @return array<int, array{type: self, field: array<string, mixed>}>
     */
    public static function relationshipsTo(string $key): array
    {
        $links = [];

        foreach (self::query()->get() as $type) {
            foreach ($type->fields as $field) {
                if (($field['type'] ?? null) === FieldType::RELATIONSHIP->value && ($field['record_type'] ?? null) === $key) {
                    $links[] = ['type' => $type, 'field' => $field];
                }
            }
        }

        return $links;
    }

    /**
     * @return MorphMany<Translation, $this>
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function nameFor(?string $locale = null, ?string $fallback = null): string
    {
        $translated = $this->nameTranslations()[$locale ?? app()->getLocale()] ?? '';

        return $translated !== '' ? $translated : ($fallback ?? (string) $this->getRawOriginal('name'));
    }

    /**
     * @return array<string, string>
     */
    public function nameTranslations(): array
    {
        return $this->translations
            ->where('key', 'name')
            ->mapWithKeys(fn (Translation $translation): array => [$translation->locale => (string) $translation->body])
            ->all();
    }

    /**
     * @param  array<string, string>  $names
     */
    public function syncNameTranslations(array $names): void
    {
        foreach ($names as $locale => $name) {
            $name = mb_trim($name);

            if ($name === '') {
                $this->translations()->where('key', 'name')->where('locale', $locale)->delete();

                continue;
            }

            $this->translations()->updateOrCreate(['key' => 'name', 'locale' => $locale], ['body' => $name]);
        }

        $this->unsetRelation('translations');
    }

    public function casts(): array
    {
        return [
            'fields' => 'array',
            'breadcrumbs' => 'boolean',
            'has_detail_page' => 'boolean',
            'has_index_page' => 'boolean',
            'sellable' => 'boolean',
            'admin_only' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return HasMany<Record, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(Record::class);
    }

    /**
     * @return array<int, string>
     */
    public function linkedFromOtherTypes(): array
    {
        return collect(self::relationshipsTo($this->key))
            ->reject(fn (array $link): bool => $link['type']->id === $this->id)
            ->map(fn (array $link): string => $link['type']->name.' → '.$link['type']->fieldLabel($link['field']))
            ->values()
            ->all();
    }

    public function isInUse(): bool
    {
        return $this->records()->exists();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function indexColumnFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            fn (array $field): bool => (bool) ($field['column'] ?? false),
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchableFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            fn (array $field): bool => (bool) ($field['searchable'] ?? false)
                && ! (FieldType::tryFrom($field['type'])?->isMedia() ?? false),
        ));
    }

    /**
     * @return array<int, string>
     */
    public function sortableFieldKeys(): array
    {
        return array_values(array_map(
            fn (array $field): string => $field['key'],
            array_filter(
                $this->fields,
                fn (array $field): bool => (bool) ($field['sortable'] ?? false)
                    && ! (FieldType::tryFrom($field['type'])?->isMedia() ?? false)
                    && ! (FieldType::tryFrom($field['type'])?->isList() ?? false),
            ),
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filterableFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            fn (array $field): bool => (bool) ($field['filterable'] ?? false)
                && (FieldType::tryFrom($field['type'])?->isFilterable() ?? false),
        ));
    }

    public function hasMediaColumns(): bool
    {
        return array_any(
            $this->indexColumnFields(),
            fn (array $field): bool => (bool) (FieldType::tryFrom($field['type'])?->isMedia()),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function displayableFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            fn (array $field): bool => ! in_array($field['key'] ?? '', ['heading', 'overview'], true)
                && ! (FieldType::tryFrom($field['type'] ?? '')?->isMedia() ?? false),
        ));
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, array<string, mixed>>
     */
    public function pickFields(array $keys): array
    {
        $fields = [];

        foreach ($keys as $key) {
            $field = $this->fieldByKey($key);

            if ($field !== null && ! (FieldType::tryFrom($field['type'] ?? '')?->isMedia() ?? false)) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public function hasImageField(): bool
    {
        return array_any(
            $this->fields,
            fn (array $field): bool => in_array(
                MediaType::IMAGE->value,
                FieldType::tryFrom((string) ($field['type'] ?? ''))?->acceptsMedia() ?? [],
                true,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $field
     */
    public function fieldLabel(array $field): string
    {
        $label = is_array($field['label'] ?? null) ? $field['label'] : [];
        $localized = $label[app()->getLocale()] ?? null;

        if (is_string($localized) && $localized !== '') {
            return $localized;
        }

        foreach ($label as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return (string) ($field['key'] ?? '');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fieldByKey(string $key): ?array
    {
        foreach ($this->fields as $field) {
            if (($field['key'] ?? null) === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    public function placementFor(array $field): FieldPlacement
    {
        $stored = FieldPlacement::tryFrom((string) ($field['placement'] ?? ''));

        if ($stored instanceof FieldPlacement) {
            return $stored;
        }

        if (($field['type'] ?? null) === FieldType::RELATIONSHIP->value) {
            return FieldPlacement::MAIN;
        }

        $templatePlacements = self::TEMPLATE_PLACEMENTS[$this->key] ?? self::DEFAULT_TEMPLATE_PLACEMENTS;

        return FieldPlacement::tryFrom($templatePlacements[(string) ($field['key'] ?? '')] ?? '') ?? FieldPlacement::HIDDEN;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    public function isHiddenByOwner(array $field): bool
    {
        return ($field['placement'] ?? null) === FieldPlacement::HIDDEN->value;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fieldsPlacedIn(FieldPlacement $placement): array
    {
        return array_values(array_filter(
            $this->fields,
            fn (array $field): bool => $this->placementFor($field) === $placement,
        ));
    }

    protected static function booted(): void
    {
        self::saving(function (self $type): void {
            if ($type->admin_only) {
                $type->forceFill(['breadcrumbs' => false, 'has_detail_page' => false, 'has_index_page' => false, 'sellable' => false]);
            }
        });
        self::deleting(fn (self $type) => $type->translations()->delete());
        self::saved(fn () => Once::flush());
        self::deleted(fn () => Once::flush());
    }

    /**
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (mixed $stored): string => $this->nameFor(app()->getLocale(), (string) $stored));
    }
}
