<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BackupService;
use App\Services\OffsiteBackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Description('Download a backup from off-site storage into storage/app/backups, ready to restore')]
#[Signature('wireup:backup:fetch {file? : The backup file name, e.g. backup-2026-11-06-030000-automatic.zip; the newest when left out}')]
final class FetchBackupCommand extends Command
{
    public function handle(OffsiteBackupService $offsite, BackupService $backups): int
    {
        if (! $offsite->configured()) {
            $this->components->error('Off-site backups are not connected. Turn on off-site copies in Settings → Backups first.');

            return self::FAILURE;
        }

        $file = $this->argument('file');

        try {
            $fetched = $offsite->fetch(is_string($file) && $file !== '' ? basename($file) : null);
        } catch (Throwable $exception) {
            $this->components->error(OffsiteBackupService::reason($exception));

            return self::FAILURE;
        }

        $this->components->info("Downloaded {$fetched} to {$backups->directory()}.");
        $this->line("  To restore it, run: php artisan wireup:backup:restore {$fetched}");

        return self::SUCCESS;
    }
}
