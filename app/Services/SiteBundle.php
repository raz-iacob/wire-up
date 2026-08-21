<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class SiteBundle
{
    public const int FORMAT = 1;

    public const string MANIFEST_ENTRY = 'manifest.json';

    public const string SITE_ENTRY = 'site.json';

    /**
     * @var array<int, string>
     */
    public const array TABLES = [
        'locales',
        'settings',
        'categories',
        'media',
        'record_types',
        'pages',
        'records',
        'blocks',
        'slugs',
        'translations',
        'mediables',
        'categorizables',
    ];

    /**
     * @var array<int, string>
     */
    public const array SECRET_SETTINGS = [
        'pexels_api_key',
        'google_analytics_credentials',
        'google_analytics_property_id',
        'slack_webhook_url',
        'ai_provider',
        'ai_api_key',
        'mail_host',
        'mail_username',
        'mail_password',
        'mail_from_address',
        'mail_from_name',
        'mail_encryption',
        'google_maps_api_key',
    ];

    /**
     * @var array<int, string>
     */
    public const array DERIVED_MEDIA_KEYS = ['preview', 'crop_src'];

    /**
     * @var array<int, string>
     */
    public const array USERSTAMP_COLUMNS = ['created_by', 'updated_by'];

    public static function current(): self
    {
        return new self;
    }

    public static function isSafeRelativePath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\')) {
            return false;
        }

        if (preg_match('#^[a-zA-Z]:#', $path) === 1) {
            return false;
        }

        return ! in_array('..', explode('/', $path), true);
    }

    public function appliedMigrations(): int
    {
        return DB::table('migrations')->count();
    }

    public function appVersion(): ?string
    {
        return resolve(UpdateService::class)->currentVersion();
    }

    /**
     * @param  array<string, int>  $tables
     * @param  array<int, string>  $missingMedia
     * @return array{format: int, app_version: ?string, migrations: int, exported_at: string, with_secrets: bool, tables: array<string, int>, media: array{count: int, bytes: int, missing: array<int, string>}}
     */
    public function manifest(array $tables, bool $withSecrets, int $mediaCount, int $mediaBytes, array $missingMedia): array
    {
        return [
            'format' => self::FORMAT,
            'app_version' => $this->appVersion(),
            'migrations' => $this->appliedMigrations(),
            'exported_at' => now()->toIso8601String(),
            'with_secrets' => $withSecrets,
            'tables' => $tables,
            'media' => [
                'count' => $mediaCount,
                'bytes' => $mediaBytes,
                'missing' => array_values($missingMedia),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<int, string>
     */
    public function incompatibilities(array $manifest): array
    {
        $problems = [];

        $format = $manifest['format'] ?? null;

        if (! is_int($format) || $format !== self::FORMAT) {
            $problems[] = __('This bundle uses format :given; this install reads format :expected.', [
                'given' => is_scalar($format) ? (string) $format : '?',
                'expected' => (string) self::FORMAT,
            ]);
        }

        $migrations = $manifest['migrations'] ?? null;

        if (is_int($migrations) && $migrations > $this->appliedMigrations()) {
            $problems[] = __('The bundle comes from a newer install (:given migrations against :expected here). Update this site first.', [
                'given' => (string) $migrations,
                'expected' => (string) $this->appliedMigrations(),
            ]);
        }

        return $problems;
    }
}
