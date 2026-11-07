<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\BackupService;
use App\Services\OffsiteBackupService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Throwable;

#[Queue('system')]
#[Timeout(3600)]
#[Tries(1)]
final class CopyBackupOffsite implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $file) {}

    public function uniqueId(): string
    {
        return 'wireup-offsite-copy';
    }

    public function handle(BackupService $backups, OffsiteBackupService $offsite): void
    {
        $path = $backups->find($this->file);

        if ($path === null || ! $offsite->configured()) {
            $offsite->markFailed($this->file, $path === null ? __('That backup is no longer on this server.') : __('Off-site storage is not connected.'));

            return;
        }

        $offsite->copyNewBackup($path);
    }

    public function failed(?Throwable $exception): void
    {
        resolve(OffsiteBackupService::class)->markFailed($this->file, $exception instanceof Throwable ? OffsiteBackupService::reason($exception) : __('The copy stopped.'));
    }
}
