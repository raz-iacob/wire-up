<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use App\Enums\ContentStatus;
use App\Enums\FieldType;
use App\Models\Block;
use App\Models\Category;
use App\Models\Record;
use App\Models\RecordType;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Mcp\Response;

final readonly class Records
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function json(array $payload): Response
    {
        return Response::text(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     */
    public static function typeSummary(RecordType $type): array
    {
        return [
            'key' => $type->key,
            'name' => $type->name,
            'slug_prefix' => $type->slug_prefix,
            'icon' => $type->icon,
            'record_count' => $type->records()->count(),
            'fields' => array_map(self::fieldSummary(...), $type->fields),
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    public static function fieldSummary(array $field): array
    {
        $labels = is_array($field['label'] ?? null) ? $field['label'] : [];

        return array_filter([
            'key' => $field['key'] ?? '',
            'type' => $field['type'] ?? '',
            'label' => (string) ($labels[app()->getLocale()] ?? Arr::first($labels) ?? ($field['key'] ?? '')),
            'required' => (bool) ($field['required'] ?? false),
            'translatable' => (bool) ($field['translatable'] ?? false),
            'options' => is_array($field['options'] ?? null) ? array_values($field['options']) : [],
            'prefills' => in_array($field['prefills'] ?? null, ['title', 'description'], true) ? $field['prefills'] : null,
        ], fn (mixed $value): bool => $value !== [] && $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public static function recordSummary(Record $record): array
    {
        $record->loadMissing('recordType', 'slugs', 'translations');
        $slug = $record->getSlug();

        return [
            'id' => $record->id,
            'type' => $record->recordType->key,
            'title' => $record->title,
            'slug' => $slug,
            'url' => $slug !== '' ? $record->getUrl() : null,
            'status' => $record->status->value,
            'published_at' => $record->published_at?->toAtomString(),
            'published_locales' => $record->published_locales,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function recordDetailed(Record $record): array
    {
        $record->loadMissing('recordType', 'blocks', 'media', 'categories');

        return [
            ...self::recordSummary($record),
            'description' => $record->description,
            'slugs' => $record->getSlugsArray(),
            'fields' => array_map(self::fieldSummary(...), $record->recordType->fields),
            'data' => is_array($record->data) ? $record->data : [],
            'media' => self::media($record),
            'categories' => $record->categories
                ->map(fn (Category $category): array => ['id' => $category->id, 'name' => $category->name])
                ->all(),
            'blocks' => $record->blocks
                ->map(fn (Block $block): array => [
                    'id' => $block->id,
                    'type' => $block->type->value,
                    'content' => $block->content ?? [],
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function media(Record $record): array
    {
        $record->loadMissing('media');

        $roles = [];

        foreach ($record->media as $media) {
            $roles[$media->pivot->role][] = [
                'id' => $media->id,
                'locale' => $media->pivot->locale,
                'source' => $media->source,
            ];
        }

        return $roles;
    }

    /**
     * @param  array<string, array<int, int>>  $media
     * @return array<string, array<string, array<int, array<string, int>>>>
     */
    public static function normalizeMedia(array $media, string $locale): array
    {
        $normalized = [];

        foreach ($media as $role => $ids) {
            $normalized[$role] = [
                $locale => array_values(array_map(
                    fn (int $id): array => ['id' => $id],
                    array_filter($ids, is_int(...)),
                )),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    public static function cleanData(RecordType $type, array $input, string $locale, array $existing = []): array
    {
        $clean = [];

        foreach ($type->fields as $field) {
            $fieldType = FieldType::tryFrom((string) ($field['type'] ?? ''));
            $key = (string) ($field['key'] ?? '');
            if ($fieldType === null) {
                continue;
            }
            if ($fieldType->isMedia()) {
                continue;
            }
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];

            if ((bool) ($field['translatable'] ?? false)) {
                $current = is_array($existing[$key] ?? null) ? $existing[$key] : [];
                $clean[$key] = is_array($value) ? [...$current, ...$value] : [...$current, $locale => $value];

                continue;
            }

            $clean[$key] = match ($fieldType) {
                FieldType::BOOLEAN => (bool) $value,
                FieldType::NUMBER, FieldType::MONEY => is_numeric($value) ? $value + 0 : null,
                default => $value === '' ? null : $value,
            };
        }

        return [...$existing, ...$clean];
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    public static function serializeFields(array $fields, string $locale): array
    {
        return array_map(function (array $field) use ($locale): array {
            $type = FieldType::tryFrom((string) ($field['type'] ?? '')) ?? FieldType::TEXT;

            return [
                'key' => (string) ($field['key'] ?? ''),
                'type' => $type->value,
                'label' => [$locale => (string) ($field['label'] ?? '')],
                'required' => (bool) ($field['required'] ?? false),
                'translatable' => (bool) ($field['translatable'] ?? $type->isTranslatableByDefault()),
                'column' => (bool) ($field['column'] ?? false),
                'sortable' => (bool) ($field['sortable'] ?? false),
                'searchable' => (bool) ($field['searchable'] ?? false),
                'help' => (string) ($field['help'] ?? ''),
                'options' => array_values(array_filter(
                    is_array($field['options'] ?? null) ? $field['options'] : [],
                    fn (mixed $option): bool => is_string($option) && $option !== '',
                )),
                'prefills' => in_array($field['prefills'] ?? null, ['title', 'description'], true) ? $field['prefills'] : null,
            ];
        }, $fields);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function fieldRules(): array
    {
        return [
            'fields' => ['array'],
            'fields.*.key' => ['required', 'string', 'distinct', 'regex:/^[a-z][a-z0-9_]*$/', 'not_in:'.implode(',', config()->array('records.reserved_field_keys'))],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', 'string', 'in:'.implode(',', FieldType::values())],
            'fields.*.required' => ['boolean'],
            'fields.*.translatable' => ['boolean'],
            'fields.*.column' => ['boolean'],
            'fields.*.sortable' => ['boolean'],
            'fields.*.searchable' => ['boolean'],
            'fields.*.help' => ['nullable', 'string', 'max:500'],
            'fields.*.options' => ['array'],
            'fields.*.options.*' => ['string'],
            'fields.*.prefills' => ['nullable', 'string', 'in:title,description'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function fieldMessages(): array
    {
        return [
            'fields.*.key.required' => 'Every field needs a "key".',
            'fields.*.key.distinct' => 'Each field key must be unique within the type.',
            'fields.*.key.regex' => 'Field keys must start with a letter and use lowercase letters, numbers and underscores.',
            'fields.*.key.not_in' => 'That field key is reserved. Choose another: '.implode(', ', config()->array('records.reserved_field_keys')).'.',
            'fields.*.label.required' => 'Every field needs a "label".',
            'fields.*.type.required' => 'Every field needs a "type".',
            'fields.*.type.in' => 'Unknown field type. Valid types are: '.implode(', ', FieldType::values()).'.',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function reservedPrefixes(): array
    {
        return array_merge(
            config()->array('records.reserved_prefixes'),
            resolve('localization')->getActiveLocaleCodes()->all(),
        );
    }

    public static function suggestSlugPrefix(string $name): string
    {
        return Str::slug(Str::plural($name));
    }

    public static function statusFor(bool $publish): ContentStatus
    {
        return $publish ? ContentStatus::PUBLISHED : ContentStatus::DRAFT;
    }

    /**
     * @return array<int, string>
     */
    public static function mediaFieldKeys(RecordType $type): array
    {
        return array_values(array_map(
            fn (array $field): string => (string) $field['key'],
            array_filter(
                $type->fields,
                fn (array $field): bool => (bool) (FieldType::tryFrom((string) ($field['type'] ?? ''))?->isMedia()),
            ),
        ));
    }

    /**
     * @param  array<string, mixed>  $media
     */
    public static function unknownMediaRole(RecordType $type, array $media): ?string
    {
        $mediaFieldKeys = self::mediaFieldKeys($type);

        foreach (array_keys($media) as $role) {
            if (! in_array($role, $mediaFieldKeys, true)) {
                return "\"{$role}\" is not a media field on this content type. Media fields are: ".($mediaFieldKeys === [] ? 'none' : implode(', ', $mediaFieldKeys)).'.';
            }
        }

        return null;
    }
}
