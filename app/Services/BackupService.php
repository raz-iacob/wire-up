<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use RuntimeException;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;
use ZipArchive;

final readonly class BackupService
{
    public const string AUTOMATIC = 'automatic';

    public const string MANUAL = 'manual';

    public const string BEFORE_UPDATE = 'update';

    private const string ARCHIVE_PATTERN = '/^backup-(\d{4}-\d{2}-\d{2}-\d{6})-(automatic|manual)\.zip$/';

    private const string UPDATE_PATTERN = '/^db-backup-(\d{4}-\d{2}-\d{2}-\d{6})\.(sql|sqlite)$/';

    /**
     * @param  (Closure(string): (float|false))|null  $freeSpace
     */
    public function __construct(
        private DatabaseDumper $dumper,
        private SiteBundle $bundle,
        private ?Closure $freeSpace = null,
    ) {}

    public function directory(): string
    {
        return config()->string('wireup.backup_path');
    }

    /**
     * @return array<int, array{file: string, kind: string, bytes: int, created_at: CarbonImmutable}>
     */
    public function all(): array
    {
        if (! File::isDirectory($this->directory())) {
            return [];
        }

        return collect(File::files($this->directory()))
            ->map(function (SplFileInfo $file): ?array {
                $name = $file->getFilename();

                if (preg_match(self::ARCHIVE_PATTERN, $name, $match) === 1) {
                    return ['file' => $name, 'kind' => $match[2], 'bytes' => (int) $file->getSize(), 'created_at' => CarbonImmutable::createFromFormat('Y-m-d-His', $match[1]) ?: CarbonImmutable::now()];
                }

                if (preg_match(self::UPDATE_PATTERN, $name, $match) === 1) {
                    return ['file' => $name, 'kind' => self::BEFORE_UPDATE, 'bytes' => (int) $file->getSize(), 'created_at' => CarbonImmutable::createFromFormat('Y-m-d-His', $match[1]) ?: CarbonImmutable::now()];
                }

                return null;
            })
            ->filter()
            ->sortByDesc(fn (array $backup): string => $backup['created_at']->toIso8601String().$backup['file'])
            ->values()
            ->all();
    }

    public function find(string $file): ?string
    {
        $isBackupName = preg_match(self::ARCHIVE_PATTERN, $file) === 1 || preg_match(self::UPDATE_PATTERN, $file) === 1;
        $path = $this->directory().'/'.$file;

        return $isBackupName && File::isFile($path) ? $path : null;
    }

    public function delete(string $file): bool
    {
        $path = $this->find($file);

        return $path !== null && preg_match(self::ARCHIVE_PATTERN, $file) === 1 && File::delete($path);
    }

    public function totalBytes(): int
    {
        return array_sum(array_column($this->all(), 'bytes'));
    }

    public function freeBytes(): ?int
    {
        $directory = File::isDirectory($this->directory()) ? $this->directory() : storage_path();
        $free = $this->freeSpace instanceof Closure ? ($this->freeSpace)($directory) : disk_free_space($directory);

        return $free === false ? null : (int) $free;
    }

    public function lastAutomatic(): ?CarbonImmutable
    {
        foreach ($this->all() as $backup) {
            if ($backup['kind'] === self::AUTOMATIC) {
                return $backup['created_at'];
            }
        }

        return null;
    }

    public function create(bool $automatic): string
    {
        $directory = $this->directory();
        File::ensureDirectoryExists($directory);
        $this->ensureRoom();

        $kind = $automatic ? self::AUTOMATIC : self::MANUAL;
        $stamp = now()->format('Y-m-d-His');
        $archive = "{$directory}/backup-{$stamp}-{$kind}.zip";
        $dump = "{$directory}/.backup-{$stamp}.{$this->dumper->extension()}";

        try {
            $error = $this->dumper->dump($dump);

            throw_if($error !== null, RuntimeException::class, $error);

            $this->writeArchive($archive, $dump, $kind);
        } catch (Throwable $exception) {
            File::delete($archive);

            throw $exception;
        } finally {
            File::delete($dump);
        }

        if ($automatic) {
            $this->prune();
        }

        return $archive;
    }

    public function uploadsRoot(): ?string
    {
        $disk = config()->string('filesystems.media');

        if (config("filesystems.disks.{$disk}.driver") !== 'local') {
            return null;
        }

        $root = Storage::disk($disk)->path('');

        return File::isDirectory($root) ? $root : null;
    }

    private function writeArchive(string $archive, string $dump, string $kind): void
    {
        $zip = new ZipArchive;

        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(__('The backup file could not be created in :path.', ['path' => $this->directory()]));
        }

        if (! File::isFile($dump)) {
            $zip->close();

            throw new RuntimeException(__('The database dump came out empty, so the backup was stopped.'));
        }

        $zip->addFile($dump, 'database.'.$this->dumper->extension());
        $files = $this->addUploadedFiles($zip);

        $zip->addFromString('manifest.json', json_encode([
            'format' => 1,
            'app_version' => $this->bundle->appVersion(),
            'migrations' => $this->bundle->appliedMigrations(),
            'database' => $this->dumper->driver(),
            'kind' => $kind,
            'created_at' => now()->toIso8601String(),
            'files' => $files,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $zip->close();
    }

    /**
     * @return array{count: int, bytes: int}
     */
    private function addUploadedFiles(ZipArchive $zip): array
    {
        $root = $this->uploadsRoot();

        if ($root === null || ! SettingsService::current()->backupIncludesFiles()) {
            return ['count' => 0, 'bytes' => 0];
        }

        $count = 0;
        $bytes = 0;

        foreach (File::allFiles($root) as $file) {
            $name = 'files/'.str_replace('\\', '/', $file->getRelativePathname());
            $zip->addFile($file->getPathname(), $name);
            $zip->setCompressionName($name, ZipArchive::CM_STORE);
            $count++;
            $bytes += (int) $file->getSize();
        }

        return ['count' => $count, 'bytes' => $bytes];
    }

    private function ensureRoom(): void
    {
        $last = collect($this->all())->first(fn (array $backup): bool => $backup['kind'] !== self::BEFORE_UPDATE);
        $free = $this->freeBytes();

        if ($last !== null && $free !== null && $free < $last['bytes'] * 1.2) {
            throw new RuntimeException(__('There is not enough free disk space for another backup: :free is free and the last backup took :size.', [
                'free' => Number::fileSize($free, precision: 1),
                'size' => Number::fileSize($last['bytes'], precision: 1),
            ]));
        }
    }

    private function prune(): void
    {
        collect($this->all())
            ->where('kind', self::AUTOMATIC)
            ->slice(SettingsService::current()->backupsToKeep())
            ->each(fn (array $backup): bool => File::delete($this->directory().'/'.$backup['file']));
    }
}
