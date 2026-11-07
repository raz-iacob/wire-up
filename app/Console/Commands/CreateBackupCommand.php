<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ActivityEvent;
use App\Notifications\BackupFailed;
use App\Services\ActivityLog;
use App\Services\BackupService;
use App\Services\OffsiteBackupService;
use App\Services\SettingsService;
use App\Services\SlackWebhookChannel;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Throwable;

#[Description('Back up the database and uploaded files to storage/app/backups, and off-site when that is connected')]
#[Signature('wireup:backup:create {--scheduled : Run as the automatic backup, only when automatic backups are switched on}')]
final class CreateBackupCommand extends Command
{
    public function handle(BackupService $backups, OffsiteBackupService $offsite): int
    {
        $scheduled = (bool) $this->option('scheduled');
        $settings = SettingsService::current();

        if ($scheduled && ! $settings->backupsEnabled()) {
            return self::SUCCESS;
        }

        $lastAutomatic = $backups->lastAutomatic();

        if ($scheduled && $settings->backupFrequency() === 'weekly' && $lastAutomatic instanceof CarbonImmutable && $lastAutomatic->gt(now()->subDays(6))) {
            return self::SUCCESS;
        }

        try {
            $archive = $backups->create(automatic: $scheduled);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            ActivityLog::record(ActivityEvent::BACKUP_FAILED, properties: ['reason' => $exception->getMessage()]);

            if ($scheduled) {
                $this->notifyFailure($exception->getMessage());
            }

            return self::FAILURE;
        }

        ActivityLog::record(ActivityEvent::BACKUP_CREATED, basename($archive));

        $this->components->info("Backed up to {$archive}");

        if (! $offsite->configured()) {
            return self::SUCCESS;
        }

        $reason = $offsite->copyNewBackup($archive);

        if ($reason === null) {
            $this->components->info('Copied off-site to '.$offsite->location());

            return self::SUCCESS;
        }

        $this->components->warn('The off-site copy failed: '.$reason);

        if ($scheduled) {
            $this->notifyFailure($reason, savedLocally: true);
        }

        return self::SUCCESS;
    }

    private function notifyFailure(string $reason, bool $savedLocally = false): void
    {
        $routes = array_filter([
            'mail' => SettingsService::current()->contactEmail(),
            SlackWebhookChannel::class => (string) config('services.slack.webhook_url'),
        ]);

        if ($routes === []) {
            return;
        }

        $notifiable = new AnonymousNotifiable;

        foreach ($routes as $channel => $route) {
            $notifiable->route($channel, $route);
        }

        try {
            Notification::sendNow($notifiable, new BackupFailed($reason, $savedLocally));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
