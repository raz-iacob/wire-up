<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ActivityEvent;
use App\Notifications\BackupFailed;
use App\Services\ActivityLog;
use App\Services\BackupService;
use App\Services\SettingsService;
use App\Services\SlackWebhookChannel;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Throwable;

#[Description('Back up the database and uploaded files to storage/app/backups')]
#[Signature('wireup:backup:create {--scheduled : Run as the automatic backup, only when automatic backups are switched on}')]
final class CreateBackupCommand extends Command
{
    public function handle(BackupService $backups): int
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

        return self::SUCCESS;
    }

    private function notifyFailure(string $reason): void
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
            Notification::sendNow($notifiable, new BackupFailed($reason));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
