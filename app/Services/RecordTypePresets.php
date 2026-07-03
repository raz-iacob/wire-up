<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FieldType;

final class RecordTypePresets
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            self::product(),
            self::service(),
            self::blogPost(),
            self::event(),
            self::teamMember(),
            self::project(),
            self::job(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $preset) {
            if ($preset['key'] === $key) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_map(fn (array $preset): string => $preset['key'], self::all());
    }

    /**
     * @return array<string, mixed>
     */
    private static function product(): array
    {
        return [
            'key' => 'product',
            'slug_prefix' => 'products',
            'icon' => 'shopping-bag',
            'name' => 'Products',
            'fields' => [
                ...self::contentFields(),
                self::field('current_price', FieldType::MONEY, 'Current price', ['column' => true, 'sortable' => true]),
                self::field('regular_price', FieldType::MONEY, 'Regular price'),
                self::field('sku', FieldType::TEXT, 'SKU', ['translatable' => false, 'searchable' => true, 'column' => true]),
                self::field('gallery', FieldType::MEDIA_GALLERY, 'Gallery'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function service(): array
    {
        return [
            'key' => 'service',
            'slug_prefix' => 'services',
            'icon' => 'wrench-screwdriver',
            'name' => 'Services',
            'fields' => [
                ...self::contentFields(),
                self::field('photo', FieldType::PHOTO, 'Photo'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function blogPost(): array
    {
        return [
            'key' => 'post',
            'slug_prefix' => 'blog',
            'icon' => 'newspaper',
            'name' => 'Blog posts',
            'fields' => [
                ...self::contentFields(),
                self::field('photo', FieldType::PHOTO, 'Photo'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function event(): array
    {
        return [
            'key' => 'event',
            'slug_prefix' => 'events',
            'icon' => 'calendar-days',
            'name' => 'Events',
            'fields' => [
                ...self::contentFields(),
                self::field('starts_at', FieldType::DATETIME, 'Starts', ['column' => true, 'sortable' => true]),
                self::field('ends_at', FieldType::DATETIME, 'Ends'),
                self::field('location', FieldType::TEXT, 'Location', ['translatable' => false]),
                self::field('photo', FieldType::PHOTO, 'Photo'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function teamMember(): array
    {
        return [
            'key' => 'team-member',
            'slug_prefix' => 'team',
            'icon' => 'users',
            'name' => 'Team members',
            'fields' => [
                ...self::contentFields(),
                self::field('role', FieldType::TEXT, 'Role', ['column' => true]),
                self::field('photo', FieldType::PHOTO, 'Photo'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function project(): array
    {
        return [
            'key' => 'project',
            'slug_prefix' => 'projects',
            'icon' => 'rectangle-group',
            'name' => 'Projects',
            'fields' => [
                ...self::contentFields(),
                self::field('client', FieldType::TEXT, 'Client', ['translatable' => false, 'column' => true]),
                self::field('link', FieldType::URL, 'Link', ['translatable' => false]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function job(): array
    {
        return [
            'key' => 'job',
            'slug_prefix' => 'jobs',
            'icon' => 'briefcase',
            'name' => 'Jobs',
            'fields' => [
                ...self::contentFields(),
                self::field('location', FieldType::TEXT, 'Location', ['translatable' => false]),
                self::field('department', FieldType::TEXT, 'Department'),
                self::field('employment_type', FieldType::TEXT, 'Employment type', ['column' => true]),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function contentFields(): array
    {
        return [
            self::field('heading', FieldType::TEXT, 'Title', ['prefills' => 'title']),
            self::field('overview', FieldType::RICH_TEXT, 'Description', ['prefills' => 'description']),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function label(string $value): array
    {
        return [config()->string('app.default_locale', 'en') => $value];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function field(string $key, FieldType $type, string $label, array $overrides = []): array
    {
        return array_merge([
            'key' => $key,
            'type' => $type->value,
            'label' => self::label($label),
            'required' => false,
            'translatable' => $type->isTranslatableByDefault(),
            'column' => false,
            'sortable' => false,
            'searchable' => false,
            'help' => '',
            'options' => [],
            'prefills' => null,
        ], $overrides);
    }
}
