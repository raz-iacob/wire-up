<?php

declare(strict_types=1);

use App\Console\Commands\UpdateCommand;
use App\Services\UpdateService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('wireup.version_file', storage_path('framework/testing/wireup-'.Str::random(8).'/version'));
});

afterEach(function (): void {
    File::deleteDirectory(dirname((string) config('wireup.version_file')));
});

it('updates to the latest release', function (): void {
    Process::fake([
        '*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0"),
        '*show*' => Process::result(output: ''),
        '*' => Process::result(),
    ]);

    resolve(UpdateService::class)->writeCurrentVersion('v0.9.0');

    $this->artisan(UpdateCommand::class)
        ->expectsOutputToContain('Wire-Up is now on v1.0.0.')
        ->assertExitCode(0);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'down']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['git', 'fetch', '--tags', '--force', 'origin']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['git', '-c', 'advice.detachedHead=false', 'checkout', '--force', 'v1.0.0']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['composer', 'install', '--no-dev', '--no-interaction', '--prefer-dist']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'wireup:backup']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'migrate', '--force']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['npm', 'ci']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['npm', 'run', 'build']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'optimize']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'queue:restart']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'up']);

    $service = resolve(UpdateService::class);

    expect($service->currentVersion())->toBe('v1.0.0')
        ->and($service->state()['status'])->toBe('finished')
        ->and($service->latestVersion())->toBe('v1.0.0');
});

it('updates to a pinned tag', function (): void {
    Process::fake([
        '*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0"),
        '*' => Process::result(),
    ]);

    resolve(UpdateService::class)->writeCurrentVersion('v0.9.0');

    $this->artisan(UpdateCommand::class, ['--tag' => 'v1.0.0'])
        ->expectsOutputToContain('Wire-Up is now on v1.0.0.')
        ->assertExitCode(0);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['git', '-c', 'advice.detachedHead=false', 'checkout', '--force', 'v1.0.0']);
});

it('reports being already up to date', function (): void {
    Process::fake(['*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0")]);

    resolve(UpdateService::class)->writeCurrentVersion('v1.0.0');

    $this->artisan(UpdateCommand::class)
        ->expectsOutputToContain('Already up to date.')
        ->assertExitCode(0);

    Process::assertDidntRun(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'down']);

    expect(resolve(UpdateService::class)->state()['status'])->toBe('idle');
});

it('reruns the update on the same version when forced', function (): void {
    Process::fake([
        '*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0"),
        '*' => Process::result(),
    ]);

    resolve(UpdateService::class)->writeCurrentVersion('v1.0.0');

    $this->artisan(UpdateCommand::class, ['--tag' => 'v1.0.0', '--force' => true])
        ->expectsOutputToContain('Wire-Up is now on v1.0.0.')
        ->assertExitCode(0);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'down']);
});

it('fails when no release tag exists', function (): void {
    Process::fake(['*ls-remote*' => Process::result(output: '')]);

    $this->artisan(UpdateCommand::class)
        ->expectsOutputToContain('No release tag to install.')
        ->assertExitCode(1);

    Process::assertDidntRun(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'down']);

    expect(resolve(UpdateService::class)->state()['status'])->toBe('idle');
});

it('records the failure when disabling maintenance mode fails', function (): void {
    Process::fake([
        "*'up'*" => Process::result(errorOutput: 'up boom', exitCode: 1),
        '*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0"),
        '*' => Process::result(),
    ]);

    resolve(UpdateService::class)->writeCurrentVersion('v0.9.0');

    $this->artisan(UpdateCommand::class, ['--tag' => 'v1.0.0'])
        ->expectsOutputToContain('up boom')
        ->assertExitCode(1);

    $service = resolve(UpdateService::class);

    expect($service->state()['status'])->toBe('failed')
        ->and($service->state()['step'])->toBe('Disable maintenance mode')
        ->and($service->currentVersion())->toBe('v1.0.0');
});

it('does not migrate when the database backup fails', function (): void {
    Process::fake([
        '*wireup:backup*' => Process::result(errorOutput: 'backup boom', exitCode: 1),
        '*' => Process::result(),
    ]);

    resolve(UpdateService::class)->writeCurrentVersion('v0.9.0');

    $this->artisan(UpdateCommand::class, ['--tag' => 'v1.0.0'])
        ->expectsOutputToContain('backup boom')
        ->assertExitCode(1);

    Process::assertDidntRun(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'migrate', '--force']);
    Process::assertDidntRun(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'up']);

    $service = resolve(UpdateService::class);

    expect($service->state()['status'])->toBe('failed')
        ->and($service->state()['step'])->toBe('Back up database');
});

it('leaves the site in maintenance mode when a step fails', function (): void {
    Process::fake([
        '*migrate*' => Process::result(output: 'migration exploded', exitCode: 1),
        '*' => Process::result(),
    ]);

    resolve(UpdateService::class)->writeCurrentVersion('v0.9.0');

    $this->artisan(UpdateCommand::class, ['--tag' => 'v1.0.0'])
        ->expectsOutputToContain('migration exploded')
        ->expectsOutputToContain('php artisan up')
        ->assertExitCode(1);

    Process::assertDidntRun(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'up']);

    $service = resolve(UpdateService::class);

    expect($service->currentVersion())->toBe('v0.9.0')
        ->and($service->state()['status'])->toBe('failed')
        ->and($service->state()['step'])->toBe('Run database migrations')
        ->and($service->state()['output'])->toContain('migration exploded');
});
