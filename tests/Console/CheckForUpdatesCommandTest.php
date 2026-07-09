<?php

declare(strict_types=1);

use App\Console\Commands\CheckForUpdatesCommand;
use App\Jobs\RunSystemUpdate;
use App\Models\Settings;
use App\Services\UpdateService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('wireup.version_file', storage_path('framework/testing/wireup-'.Str::random(8).'/version'));
});

afterEach(function (): void {
    File::deleteDirectory(dirname((string) config('wireup.version_file')));
});

it('reports an available update', function (): void {
    Process::fake([
        '*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0"),
        '*fetch*' => Process::result(),
        '*show*' => Process::result(output: ''),
    ]);
    resolve(UpdateService::class)->writeCurrentVersion('v0.9.0');

    $this->artisan(CheckForUpdatesCommand::class)
        ->expectsOutputToContain('v0.9.0')
        ->expectsOutputToContain('Update available: v1.0.0.')
        ->assertExitCode(0);

    expect(resolve(UpdateService::class)->latestVersion())->toBe('v1.0.0');
});

it('reports being up to date', function (): void {
    Process::fake(['*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0")]);
    resolve(UpdateService::class)->writeCurrentVersion('v1.0.0');

    $this->artisan(CheckForUpdatesCommand::class)
        ->expectsOutputToContain('Up to date.')
        ->assertExitCode(0);
});

it('warns when no release tags exist', function (): void {
    Process::fake(['*ls-remote*' => Process::result(output: '')]);

    $this->artisan(CheckForUpdatesCommand::class)
        ->expectsOutputToContain('unknown')
        ->expectsOutputToContain('No release tags found.')
        ->assertExitCode(0);
});

it('queues an automatic update when enabled', function (): void {
    Queue::fake();
    Process::fake([
        '*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0"),
        '*fetch*' => Process::result(),
        '*show*' => Process::result(output: ''),
    ]);
    Settings::set(['auto_update' => true]);
    resolve(UpdateService::class)->writeCurrentVersion('v0.9.0');

    $this->artisan(CheckForUpdatesCommand::class)
        ->expectsOutputToContain('Auto-update to v1.0.0 queued.')
        ->assertExitCode(0);

    Queue::assertPushed(RunSystemUpdate::class, fn (RunSystemUpdate $job): bool => $job->tag === 'v1.0.0');

    expect(resolve(UpdateService::class)->state()['status'])->toBe('pending');
});

it('does not queue an automatic update when disabled', function (): void {
    Queue::fake();
    Process::fake([
        '*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0"),
        '*fetch*' => Process::result(),
        '*show*' => Process::result(output: ''),
    ]);
    resolve(UpdateService::class)->writeCurrentVersion('v0.9.0');

    $this->artisan(CheckForUpdatesCommand::class)->assertExitCode(0);

    Queue::assertNothingPushed();
});

it('does not queue an automatic update when up to date', function (): void {
    Queue::fake();
    Process::fake(['*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0")]);
    Settings::set(['auto_update' => true]);
    resolve(UpdateService::class)->writeCurrentVersion('v1.0.0');

    $this->artisan(CheckForUpdatesCommand::class)->assertExitCode(0);

    Queue::assertNothingPushed();
});

it('does not queue an automatic update while one is pending or failed', function (string $status): void {
    Queue::fake();
    Process::fake([
        '*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0"),
        '*fetch*' => Process::result(),
        '*show*' => Process::result(output: ''),
    ]);
    Settings::set(['auto_update' => true]);

    $service = resolve(UpdateService::class);
    $service->writeCurrentVersion('v0.9.0');

    match ($status) {
        'pending' => $service->markPending('v1.0.0'),
        'failed' => $service->markFailed('v1.0.0', 'Run database migrations', 'boom'),
        default => null,
    };

    $this->artisan(CheckForUpdatesCommand::class)->assertExitCode(0);

    Queue::assertNothingPushed();
})->with(['pending', 'failed']);
