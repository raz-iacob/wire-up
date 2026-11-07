<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ActivityEvent;
use App\Integrations\OffsiteBackups;
use Aws\Exception\AwsException;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\File as UploadableFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\FileAttributes;
use RuntimeException;
use Throwable;

final readonly class OffsiteBackupService
{
    private const string CACHE_FILES = 'wireup:offsite:files';

    private const string CACHE_STATE = 'wireup:backup:offsite';

    /**
     * @param  (Closure(array<string, mixed>): FilesystemAdapter)|null  $diskFactory
     */
    public function __construct(
        private BackupService $backups,
        private ?Closure $diskFactory = null,
    ) {}

    public static function reason(Throwable $exception): string
    {
        $aws = $exception;

        while ($aws instanceof Throwable && ! $aws instanceof AwsException) {
            $aws = $aws->getPrevious();
        }

        $reason = $aws instanceof AwsException && $aws->getAwsErrorCode() !== null
            ? $aws->getAwsErrorCode().': '.$aws->getAwsErrorMessage()
            : $exception->getMessage();

        return Str::limit(mb_trim($reason), 300);
    }

    public function configured(): bool
    {
        return new OffsiteBackups()->connected($this->saved());
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function diskConfig(array $settings): array
    {
        $endpoint = $this->text($settings, 'offsite_endpoint');

        return [
            'driver' => 's3',
            'key' => $this->text($settings, 'offsite_access_key'),
            'secret' => $this->text($settings, 'offsite_secret_key'),
            'region' => $this->text($settings, 'offsite_region') ?: 'auto',
            'bucket' => $this->text($settings, 'offsite_bucket'),
            'endpoint' => $endpoint === '' ? null : $endpoint,
            'use_path_style_endpoint' => $endpoint !== '',
            'http' => ['connect_timeout' => 10],
            'throw' => true,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $settings
     */
    public function disk(?array $settings = null): FilesystemAdapter
    {
        $config = $this->diskConfig($settings ?? $this->saved());
        $disk = $this->diskFactory instanceof Closure ? ($this->diskFactory)($config) : Storage::build($config);

        return $disk instanceof FilesystemAdapter ? $disk : throw new RuntimeException('Off-site storage must be a Flysystem disk.');
    }

    /**
     * @param  array<string, mixed>|null  $settings
     */
    public function folder(?array $settings = null): string
    {
        return mb_trim($this->text($settings ?? $this->saved(), 'offsite_folder'), '/');
    }

    /**
     * @param  array<string, mixed>|null  $settings
     */
    public function location(?array $settings = null): string
    {
        $settings ??= $this->saved();

        return mb_rtrim($this->text($settings, 'offsite_bucket').'/'.$this->folder($settings), '/');
    }

    public function copy(string $archive): void
    {
        $this->disk()->putFileAs($this->folder(), new UploadableFile($archive), basename($archive));

        $this->prune();
    }

    public function copyNewBackup(string $archive): ?string
    {
        if (! $this->configured()) {
            return null;
        }

        try {
            $this->copy($archive);
        } catch (Throwable $exception) {
            $reason = self::reason($exception);

            $this->markFailed(basename($archive), $reason);

            ActivityLog::record(ActivityEvent::BACKUP_OFFSITE_FAILED, basename($archive), ['reason' => $reason]);

            return $reason;
        } finally {
            Cache::forget($this->filesKey());
        }

        $this->clearState();

        return null;
    }

    /**
     * @return list<array{file: string, kind: string, bytes: int, created_at: CarbonImmutable}>|null
     */
    public function files(): ?array
    {
        if (! $this->configured()) {
            return [];
        }

        $cached = Cache::get($this->filesKey());

        if (is_array($cached)) {
            /** @var list<array{file: string, kind: string, bytes: int, created_at: CarbonImmutable}> $cached */
            return $cached;
        }

        $files = rescue($this->listing(...), report: false);

        if (is_array($files)) {
            Cache::put($this->filesKey(), $files, now()->addMinutes(10));
        }

        return $files;
    }

    public function fetch(?string $file = null): string
    {
        $chosen = collect($this->listing())->first(fn (array $backup): bool => $file === null || $backup['file'] === $file);

        throw_if($chosen === null, RuntimeException::class, $file === null
            ? __('There are no backups in off-site storage yet.')
            : __('There is no backup called :file in off-site storage.', ['file' => $file]));

        File::ensureDirectoryExists($this->backups->directory());

        $source = $this->readStream($chosen['file']);
        $target = fopen($this->backups->directory().'/'.$chosen['file'], 'wb');

        throw_if($target === false, RuntimeException::class, __('The backup could not be saved in :path.', ['path' => $this->backups->directory()]));

        stream_copy_to_stream($source, $target);
        fclose($target);
        fclose($source);

        return $chosen['file'];
    }

    /**
     * @return resource
     */
    public function readStream(string $file): mixed
    {
        $described = $this->backups->describe($file);

        throw_if($described === null || $described['kind'] === BackupService::BEFORE_UPDATE, RuntimeException::class, __('That is not a backup file.'));

        $stream = $this->disk()->readStream($this->path($file));

        return is_resource($stream) ? $stream : throw new RuntimeException(__('That backup could not be read from off-site storage.'));
    }

    /**
     * @return array{status: string, file: ?string, output: ?string}|null
     */
    public function state(): ?array
    {
        $raw = Cache::get(self::CACHE_STATE);

        if (! is_array($raw)) {
            return null;
        }

        $status = is_string($raw['status'] ?? null) ? $raw['status'] : 'failed';
        $at = is_string($raw['at'] ?? null) ? CarbonImmutable::parse($raw['at']) : now();

        return [
            'status' => $status === 'copying' && $at->lt(now()->subMinutes(70)) ? 'stalled' : $status,
            'file' => is_string($raw['file'] ?? null) ? $raw['file'] : null,
            'output' => is_string($raw['output'] ?? null) ? $raw['output'] : null,
        ];
    }

    public function markCopying(string $file): void
    {
        $this->writeState('copying', $file);
    }

    public function markFailed(string $file, string $reason): void
    {
        $this->writeState('failed', $file, $reason);
    }

    public function clearState(): void
    {
        Cache::forget(self::CACHE_STATE);
    }

    private function prune(): void
    {
        $disk = $this->disk();

        foreach (array_slice($this->listing(), SettingsService::current()->offsiteBackupsToKeep()) as $backup) {
            $disk->delete($this->path($backup['file']));
        }
    }

    /**
     * @return list<array{file: string, kind: string, bytes: int, created_at: CarbonImmutable}>
     */
    private function listing(): array
    {
        $backups = [];

        foreach ($this->disk()->getDriver()->listContents($this->folder(), false) as $item) {
            $name = basename($item->path());
            $described = $this->backups->describe($name);

            if (! $item instanceof FileAttributes || $described === null || $described['kind'] === BackupService::BEFORE_UPDATE) {
                continue;
            }

            $backups[] = ['file' => $name, 'kind' => $described['kind'], 'bytes' => (int) $item->fileSize(), 'created_at' => $described['created_at']];
        }

        usort($backups, fn (array $a, array $b): int => strcmp($b['created_at']->toIso8601String().$b['file'], $a['created_at']->toIso8601String().$a['file']));

        return $backups;
    }

    private function filesKey(): string
    {
        $saved = $this->saved();

        return self::CACHE_FILES.':'.hash('xxh128', $this->text($saved, 'offsite_endpoint').'|'.$this->location($saved));
    }

    private function path(string $file): string
    {
        return mb_ltrim($this->folder().'/'.$file, '/');
    }

    private function writeState(string $status, string $file, ?string $output = null): void
    {
        Cache::forever(self::CACHE_STATE, [
            'status' => $status,
            'file' => $file,
            'output' => $output,
            'at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function saved(): array
    {
        $site = config('site');

        return is_array($site) ? $site : [];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function text(array $settings, string $key): string
    {
        $value = $settings[$key] ?? null;

        return is_string($value) ? mb_trim($value) : '';
    }
}
