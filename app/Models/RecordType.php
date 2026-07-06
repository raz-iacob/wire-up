<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FieldType;
use App\Enums\MediaType;
use Carbon\CarbonInterface;
use Database\Factories\RecordTypeFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property-read string $key
 * @property-read string $slug_prefix
 * @property-read string $icon
 * @property-read string $name
 * @property-read array<int, array<string, mixed>> $fields
 * @property-read int $position
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Collection<int, Record> $records
 */
final class RecordType extends Model
{
    /** @use HasFactory<RecordTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'fields' => 'array',
            'position' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * @return HasMany<Record, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(Record::class);
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
                    && ! (FieldType::tryFrom($field['type'])?->isMedia() ?? false),
            ),
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
}
