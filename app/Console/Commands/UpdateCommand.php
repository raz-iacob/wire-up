<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ActivityEvent;
use App\Services\ActivityLog;
use App\Services\ReleaseAssets;
use App\Services\UpdateService;
use App\Traits\RunsSteps;
use Closure;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Update Wire Up to the latest release')]
#[Signature('wireup:update {--tag= : The release tag to install} {--force : Run even when already on the target version}')]
final class UpdateCommand extends Command
{
    use RunsSteps;

    public function handle(UpdateService $updates, ReleaseAssets $assets): int
    {
        $tag = $this->targetTag($updates);

        if ($tag === null) {
            $this->components->error('No release tag to install.');

            return self::FAILURE;
        }

        if ($tag === $updates->currentVersion() && ! $this->option('force')) {
            $this->components->info('Already up to date.');

            return self::SUCCESS;
        }

        $this->info("Update Wire Up to {$tag}");
        $this->newLine();

        $prebuilt = $assets->published($tag);

        $preparation = $prebuilt
            ? ['Download frontend assets' => fn (): string => $assets->download($tag)]
            : ['Check Node.js' => ['npm', '--version']];

        $frontend = $prebuilt
            ? ['Install frontend assets' => $assets->install(...)]
            : ['Install frontend dependencies' => ['npm', 'ci'], 'Build frontend assets' => ['npm', 'run', 'build']];

        foreach ($preparation as $label => $step) {
            if (! $this->runStep($updates, $tag, $label, $step, siteIsDown: false)) {
                return self::FAILURE;
            }
        }

        $steps = [
            'Enable maintenance mode' => [PHP_BINARY, 'artisan', 'down'],
            'Fetch releases' => ['git', 'fetch', '--tags', '--force', 'origin'],
            "Check out {$tag}" => ['git', '-c', 'advice.detachedHead=false', 'checkout', '--force', $tag],
            'Install PHP dependencies' => ['composer', 'install', '--no-dev', '--no-interaction', '--prefer-dist'],
            'Back up database' => [PHP_BINARY, 'artisan', 'wireup:backup'],
            'Run database migrations' => [PHP_BINARY, 'artisan', 'migrate', '--force'],
            ...$frontend,
            'Cache configuration' => [PHP_BINARY, 'artisan', 'optimize'],
            'Restart queue workers' => [PHP_BINARY, 'artisan', 'queue:restart'],
        ];

        foreach ($steps as $label => $step) {
            if (! $this->runStep($updates, $tag, $label, $step)) {
                return $this->failInMaintenanceMode();
            }
        }

        $updates->writeCurrentVersion($tag);
        $updates->check();

        if (! $this->runStep($updates, $tag, 'Disable maintenance mode', [PHP_BINARY, 'artisan', 'up'])) {
            return $this->failInMaintenanceMode();
        }

        $updates->markFinished($tag);

        ActivityLog::record(ActivityEvent::SYSTEM_UPDATED, $tag);

        $this->newLine();
        $this->info("Wire Up is now on {$tag}.");

        return self::SUCCESS;
    }

    private function targetTag(UpdateService $updates): ?string
    {
        $option = $this->option('tag');

        if (is_string($option) && $option !== '') {
            return $option;
        }

        return $updates->latestVersion() ?? $updates->check();
    }

    /**
     * @param  list<string>|Closure(): mixed  $step
     */
    private function runStep(UpdateService $updates, string $tag, string $label, array|Closure $step, bool $siteIsDown = true): bool
    {
        $updates->markRunning($tag, $label);

        [$successful, $output] = $this->performStep($step);

        $this->components->task($label, fn (): bool => $successful);

        if ($successful) {
            return true;
        }

        $updates->markFailed($tag, $label, $output, $siteIsDown);

        if ($output !== '') {
            $this->components->error(mb_substr($output, -2000));
        }

        return false;
    }

    private function failInMaintenanceMode(): int
    {
        $this->components->warn('The site is in maintenance mode. Fix the issue, then run: php artisan up');

        return self::FAILURE;
    }
}
