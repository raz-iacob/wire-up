<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RunSystemUpdate;
use App\Services\UpdateService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Check for a newer Wire-Up release')]
#[Signature('wireup:check')]
final class CheckForUpdatesCommand extends Command
{
    public function handle(UpdateService $updates): int
    {
        $latest = $updates->check();
        $current = $updates->currentVersion();

        $this->components->twoColumnDetail('Current version', $current ?? 'unknown');
        $this->components->twoColumnDetail('Latest version', $latest ?? 'unknown');

        if ($latest === null) {
            $this->components->warn('No release tags found.');
        } elseif ($updates->updateAvailable()) {
            $this->components->info("Update available: {$latest}.");
        } else {
            $this->components->info('Up to date.');
        }

        if ($latest !== null
            && (bool) config('site.auto_update')
            && $updates->updateAvailable()
            && in_array($updates->state()['status'], ['idle', 'finished'], true)) {
            $updates->markPending($latest);
            dispatch(new RunSystemUpdate($latest));
            $this->components->info("Auto-update to {$latest} queued.");
        }

        return self::SUCCESS;
    }
}
