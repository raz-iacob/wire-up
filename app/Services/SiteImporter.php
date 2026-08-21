<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

final class SiteImporter
{
    public static function current(): self
    {
        return new self;
    }

    /**
     * @return array{manifest: array<string, mixed>, problems: array<int, string>, tables: array<string, int>, media: array{expected: int, present: int, missing: array<int, string>}, imported: bool}
     */
    public function inspect(string $path): array
    {
        return $this->run($path, null, dryRun: true);
    }

    /**
     * @return array{manifest: array<string, mixed>, problems: array<int, string>, tables: array<string, int>, media: array{expected: int, present: int, missing: array<int, string>}, imported: bool}
     */
    public function import(string $path, ?int $ownerId): array
    {
        return $this->run($path, $ownerId, dryRun: false);
    }

    /**
     * @return array{manifest: array<string, mixed>, problems: array<int, string>, tables: array<string, int>, media: array{expected: int, present: int, missing: array<int, string>}, imported: bool}
     */
    private function run(string $path, ?int $ownerId, bool $dryRun): array
    {
        $zip = new ZipArchive;

        throw_if($zip->open($path) !== true, RuntimeException::class, "Unable to read the bundle at {$path}.");

        try {
            $manifest = $this->entry($zip, SiteBundle::MANIFEST_ENTRY);
            $site = $this->entry($zip, SiteBundle::SITE_ENTRY);

            $problems = SiteBundle::current()->incompatibilities($manifest);

            $tables = [];
            foreach (SiteBundle::TABLES as $table) {
                $tables[$table] = count(is_array($site[$table] ?? null) ? $site[$table] : []);
            }

            $audit = $this->auditMedia($zip, is_array($site['media'] ?? null) ? $site['media'] : []);
            $media = $audit['report'];

            if ($media['missing'] !== []) {
                $problems[] = __(':count media file(s) named in the bundle are not inside it.', [
                    'count' => (string) count($media['missing']),
                ]);
            }

            if ($dryRun || $problems !== []) {
                return ['manifest' => $manifest, 'problems' => $problems, 'tables' => $tables, 'media' => $media, 'imported' => false];
            }

            $this->replace($site, $ownerId);
            $this->restoreMedia($zip, $audit['sources']);
            $this->flushCaches();

            return ['manifest' => $manifest, 'problems' => [], 'tables' => $tables, 'media' => $media, 'imported' => true];
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(ZipArchive $zip, string $name): array
    {
        $contents = $zip->getFromName($name);

        throw_if($contents === false, RuntimeException::class, "The bundle is missing {$name}.");

        $decoded = json_decode($contents, true);

        throw_unless(is_array($decoded), RuntimeException::class, "The bundle has an unreadable {$name}.");

        return $decoded;
    }

    /**
     * @param  array<int, mixed>  $media
     * @return array{report: array{expected: int, present: int, missing: array<int, string>}, sources: array<int, string>}
     */
    private function auditMedia(ZipArchive $zip, array $media): array
    {
        $sources = [];
        $missing = [];

        foreach ($media as $item) {
            $source = is_array($item) && is_string($item['source'] ?? null) ? $item['source'] : '';

            if ($source === '' || ! SiteBundle::isSafeRelativePath($source) || $zip->locateName($source) === false) {
                $missing[] = $source;

                continue;
            }

            $sources[] = $source;
        }

        return [
            'report' => ['expected' => count($media), 'present' => count($sources), 'missing' => $missing],
            'sources' => $sources,
        ];
    }

    /**
     * @param  array<string, mixed>  $site
     */
    private function replace(array $site, ?int $ownerId): void
    {
        DB::transaction(function () use ($site, $ownerId): void {
            foreach (array_reverse(SiteBundle::TABLES) as $table) {
                DB::table($table)->delete();
            }

            foreach (SiteBundle::TABLES as $table) {
                $rows = is_array($site[$table] ?? null) ? $site[$table] : [];

                if ($rows === []) {
                    continue;
                }

                $prepared = array_map(
                    fn (mixed $row): array => $this->prepareRow($table, is_array($row) ? $row : [], $ownerId),
                    $rows,
                );

                foreach (array_chunk($prepared, 200) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function prepareRow(string $table, array $row, ?int $ownerId): array
    {
        foreach (SiteBundle::USERSTAMP_COLUMNS as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = $row[$column] === null ? null : $ownerId;
            }
        }

        if ($table === 'record_types') {
            $row['fields'] = $this->normalizeFields($row['fields'] ?? null);
        }

        return $row;
    }

    private function normalizeFields(mixed $fields): string
    {
        $decoded = is_string($fields) ? json_decode($fields, true) : $fields;

        if (! is_array($decoded)) {
            return '[]';
        }

        $locale = config()->string('app.default_locale', 'en');

        $normalized = array_map(function (mixed $field) use ($locale): mixed {
            if (! is_array($field)) {
                return $field;
            }

            $label = $field['label'] ?? null;

            if (is_array($label)) {
                return $field;
            }

            $field['label'] = [$locale => is_scalar($label) ? (string) $label : ''];

            return $field;
        }, $decoded);

        return (string) json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<int, string>  $sources  Already checked by auditMedia.
     */
    private function restoreMedia(ZipArchive $zip, array $sources): void
    {
        $disk = Storage::disk(config()->string('filesystems.media'));

        foreach ($sources as $source) {
            $disk->put($source, (string) $zip->getFromName($source));
        }
    }

    private function flushCaches(): void
    {
        Settings::flush();

        cache()->forget('site-locales');
    }
}
