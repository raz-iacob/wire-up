<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ActivityEvent;
use App\Services\ActivityLog;
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
final class RunBackup implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ?int $userId = null) {}

    public function uniqueId(): string
    {
        return 'wireup-backup';
    }

    public function handle(BackupService $backups, OffsiteBackupService $offsite): void
    {
        if ($backups->state()['status'] !== 'pending') {
            return;
        }

        $backups->markRunning();

        $archive = $backups->create(automatic: false);

        ActivityLog::record(ActivityEvent::BACKUP_CREATED, basename($archive), userId: $this->userId);

        $offsite->copyNewBackup($archive);

        $backups->markFinished(basename($archive));
    }

    public function failed(?Throwable $exception): void
    {
        resolve(BackupService::class)->markFailed((string) $exception?->getMessage());

        ActivityLog::record(ActivityEvent::BACKUP_FAILED, properties: ['reason' => (string) $exception?->getMessage()], userId: $this->userId);
    }
}
