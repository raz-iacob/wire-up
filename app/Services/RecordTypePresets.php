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
                self::field('price', FieldType::MONEY, 'Price', ['column' => true, 'sortable' => true]),
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
                self::field('summary', FieldType::TEXTAREA, 'Summary'),
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
                self::field('excerpt', FieldType::TEXTAREA, 'Excerpt'),
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
                self::field('starts_at', FieldType::DATETIME, 'Starts', ['column' => true, 'sortable' => true]),
                self::field('ends_at', FieldType::DATETIME, 'Ends'),
                self::field('location', FieldType::TEXT, 'Location', ['translatable' => false]),
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
                self::field('role', FieldType::TEXT, 'Role', ['column' => true]),
                self::field('bio', FieldType::RICH_TEXT, 'Bio'),
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
                self::field('client', FieldType::TEXT, 'Client', ['translatable' => false, 'column' => true]),
                self::field('summary', FieldType::TEXTAREA, 'Summary'),
                self::field('gallery', FieldType::MEDIA_GALLERY, 'Gallery'),
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
                self::field('location', FieldType::TEXT, 'Location', ['translatable' => false, 'column' => true]),
                self::field('department', FieldType::TEXT, 'Department', ['column' => true]),
                self::field('employment_type', FieldType::SELECT, 'Employment type', [
                    'translatable' => false,
                    'column' => true,
                    'options' => ['Full-time', 'Part-time', 'Contract', 'Internship'],
                ]),
                self::field('description', FieldType::RICH_TEXT, 'Description'),
            ],
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
        ], $overrides);
    }
}
