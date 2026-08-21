<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

final class SiteExporter
{
    public static function current(): self
    {
        return new self;
    }

    /**
     * @return array{format: int, app_version: ?string, migrations: int, exported_at: string, with_secrets: bool, tables: array<string, int>, media: array{count: int, bytes: int, missing: array<int, string>}}
     */
    public function export(string $path, bool $withSecrets = false): array
    {
        File::ensureDirectoryExists(dirname($path));

        $site = [];
        $counts = [];

        foreach (SiteBundle::TABLES as $table) {
            $rows = $this->rows($table, $withSecrets);

            $site[$table] = $rows;
            $counts[$table] = count($rows);
        }

        $zip = new ZipArchive;

        throw_if($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true, RuntimeException::class, "Unable to write the bundle to {$path}.");

        [$mediaCount, $mediaBytes, $missing] = $this->addMedia($zip, $site['media']);

        $manifest = SiteBundle::current()->manifest($counts, $withSecrets, $mediaCount, $mediaBytes, $missing);

        $zip->addFromString(SiteBundle::MANIFEST_ENTRY, $this->json($manifest));
        $zip->addFromString(SiteBundle::SITE_ENTRY, $this->json($site));

        $zip->close();

        return $manifest;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $table, bool $withSecrets): array
    {
        $query = DB::table($table);

        if (Schema::hasColumn($table, 'id')) {
            $query->orderBy('id');
        }

        $rows = $query->get()
            ->map(fn (object $row): array => $this->scrubRow((array) $row))
            ->all();

        if ($table !== 'settings' || $withSecrets) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => ! in_array($row['key'] ?? null, SiteBundle::SECRET_SETTINGS, true),
        ));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function scrubRow(array $row): array
    {
        foreach ($row as $column => $value) {
            if (! is_string($value) || ! str_starts_with(mb_ltrim($value), '{') && ! str_starts_with(mb_ltrim($value), '[')) {
                continue;
            }

            $decoded = json_decode($value, true);

            if (! is_array($decoded)) {
                continue;
            }

            $scrubbed = $this->scrub($decoded);

            if ($scrubbed !== $decoded) {
                $row[$column] = $this->json($scrubbed);
            }
        }

        return $row;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function scrub(array $data): array
    {
        $scrubbed = [];

        foreach ($data as $key => $value) {
            if (in_array($key, SiteBundle::DERIVED_MEDIA_KEYS, true)) {
                continue;
            }

            $scrubbed[$key] = is_array($value) ? $this->scrub($value) : $value;
        }

        return $scrubbed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $media
     * @return array{0: int, 1: int, 2: array<int, string>}
     */
    private function addMedia(ZipArchive $zip, array $media): array
    {
        $disk = Storage::disk(config()->string('filesystems.media'));

        $count = 0;
        $bytes = 0;
        $missing = [];

        foreach ($media as $item) {
            $source = is_string($item['source'] ?? null) ? $item['source'] : '';

            if ($source === '' || ! SiteBundle::isSafeRelativePath($source)) {
                $missing[] = $source;

                continue;
            }

            if (! $disk->exists($source)) {
                $missing[] = $source;

                continue;
            }

            $zip->addFile($disk->path($source), $source);

            $count++;
            $bytes += (int) $disk->size($source);
        }

        return [$count, $bytes, $missing];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function json(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
