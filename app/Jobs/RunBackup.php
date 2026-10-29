<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\BackupService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Throwable;

#[Timeout(3600)]
#[Tries(1)]
final class RunBackup implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function uniqueId(): string
    {
        return 'wireup-backup';
    }

    public function handle(BackupService $backups): void
    {
        if ($backups->state()['status'] !== 'pending') {
            return;
        }

        $backups->markRunning();

        $backups->markFinished(basename($backups->create(automatic: false)));
    }

    public function failed(?Throwable $exception): void
    {
        resolve(BackupService::class)->markFailed((string) $exception?->getMessage());
    }
}
