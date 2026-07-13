<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\UpdateService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

#[Description('Update Wire-Up to the latest release')]
#[Signature('wireup:update {--tag= : The release tag to install} {--force : Run even when already on the target version}')]
final class UpdateCommand extends Command
{
    public function handle(UpdateService $updates): int
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

        $this->info("Update Wire-Up to {$tag}");
        $this->newLine();

        $steps = [
            'Enable maintenance mode' => [PHP_BINARY, 'artisan', 'down'],
            'Fetch releases' => ['git', 'fetch', '--tags', '--force', 'origin'],
            "Check out {$tag}" => ['git', '-c', 'advice.detachedHead=false', 'checkout', '--force', $tag],
            'Install PHP dependencies' => ['composer', 'install', '--no-dev', '--no-interaction', '--prefer-dist'],
            'Back up database' => [PHP_BINARY, 'artisan', 'wireup:backup'],
            'Run database migrations' => [PHP_BINARY, 'artisan', 'migrate', '--force'],
            'Install frontend dependencies' => ['npm', 'ci'],
            'Build frontend assets' => ['npm', 'run', 'build'],
            'Cache configuration' => [PHP_BINARY, 'artisan', 'optimize'],
            'Restart queue workers' => [PHP_BINARY, 'artisan', 'queue:restart'],
        ];

        foreach ($steps as $label => $command) {
            if (! $this->runStep($updates, $tag, $label, $command)) {
                return self::FAILURE;
            }
        }

        $updates->writeCurrentVersion($tag);
        $updates->check();

        if (! $this->runStep($updates, $tag, 'Disable maintenance mode', [PHP_BINARY, 'artisan', 'up'])) {
            return self::FAILURE;
        }

        $updates->markFinished($tag);

        $this->newLine();
        $this->info("Wire-Up is now on {$tag}.");

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
     * @param  list<string>  $command
     */
    private function runStep(UpdateService $updates, string $tag, string $label, array $command): bool
    {
        $updates->markRunning($tag, $label);

        $result = Process::path(base_path())->timeout(600)->run($command);

        $this->components->task($label, fn (): bool => $result->successful());

        if ($result->successful()) {
            return true;
        }

        $output = mb_trim($result->output()."\n".$result->errorOutput());

        $updates->markFailed($tag, $label, $output);

        if ($output !== '') {
            $this->components->error(mb_substr($output, -2000));
        }

        $this->components->warn('The site is in maintenance mode. Fix the issue, then run: php artisan up');

        return false;
    }
}
