<?php

declare(strict_types=1);

use App\Console\Commands\UpdateCommand;
use App\Enums\ActivityEvent;
use App\Enums\RevisionSource;
use App\Models\Activity;
use App\Services\UpdateService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\Support\ReleaseArchive;

beforeEach(function (): void {
    $root = storage_path('framework/testing/wireup-'.Str::random(8));

    config()->set('wireup.version_file', $root.'/version');
    config()->set('wireup.updates_path', $root.'/updates');
    config()->set('wireup.build_path', $root.'/build');
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
        ->expectsOutputToContain('Wire Up is now on v1.0.0.')
        ->assertExitCode(0);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'down']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['git', 'fetch', '--tags', '--force', 'origin']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['git', '-c', 'advice.detachedHead=false', 'checkout', '--force', 'v1.0.0']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['composer', 'install', '--no-dev', '--no-interaction', '--prefer-dist']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'wireup:backup']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'migrate', '--force']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['npm', '--version']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['npm', 'ci']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['npm', 'run', 'build']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'optimize']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'queue:restart']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'up']);

    $service = resolve(UpdateService::class);

    expect($service->currentVersion())->toBe('v1.0.0')
        ->and($service->state()['status'])->toBe('finished')
        ->and($service->latestVersion())->toBe('v1.0.0')
        ->and(Activity::query()->sole()->only(['event', 'source', 'subject_label']))->toBe(['event' => ActivityEvent::SYSTEM_UPDATED, 'source' => RevisionSource::SYSTEM, 'subject_label' => 'v1.0.0']);
});

it('updates to a pinned tag', function (): void {
    Process::fake([
        '*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0"),
        '*' => Process::result(),
    ]);

    resolve(UpdateService::class)->writeCurrentVersion('v0.9.0');

    $this->artisan(UpdateCommand::class, ['--tag' => 'v1.0.0'])
        ->expectsOutputToContain('Wire Up is now on v1.0.0.')
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
        ->expectsOutputToContain('Wire Up is now on v1.0.0.')
        ->assertExitCode(0);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'down']);
});

it('fails when no release tag exists', function (): void {
    Process::fake([
        '*ls-remote*' => Process::result(output: ''),
        '*describe*' => Process::result(exitCode: 1),
        '*fetch*' => Process::result(),
    ]);

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

it('installs the prebuilt frontend assets instead of building them on the server', function (): void {
    $archive = ReleaseArchive::make(['build/manifest.json' => '{"release":"v1.0.0"}']);
    Process::fake([
        '*get-url*' => Process::result(output: ReleaseArchive::REMOTE),
        '*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0"),
        '*' => Process::result(),
    ]);
    Http::fake([
        ReleaseArchive::url('v1.0.0', 'build.tar.gz.sha256') => Http::response(hash('sha256', $archive)),
        ReleaseArchive::url('v1.0.0') => Http::response($archive),
    ]);

    resolve(UpdateService::class)->writeCurrentVersion('v0.9.0');

    $this->artisan(UpdateCommand::class, ['--tag' => 'v1.0.0'])
        ->expectsOutputToContain('Wire Up is now on v1.0.0.')
        ->assertExitCode(0);

    Process::assertDidntRun(fn (PendingProcess $process): bool => is_array($process->command) && $process->command[0] === 'npm');

    expect(File::get(config('wireup.build_path').'/manifest.json'))->toBe('{"release":"v1.0.0"}')
        ->and(resolve(UpdateService::class)->state()['status'])->toBe('finished');
});

it('stops before maintenance mode when the prebuilt assets fail verification', function (): void {
    Process::fake([
        '*get-url*' => Process::result(output: ReleaseArchive::REMOTE),
        '*' => Process::result(),
    ]);
    Http::fake([
        ReleaseArchive::url('v1.0.0', 'build.tar.gz.sha256') => Http::response(str_repeat('a', 64)),
        ReleaseArchive::url('v1.0.0') => Http::response('tampered'),
    ]);

    resolve(UpdateService::class)->writeCurrentVersion('v0.9.0');

    $this->artisan(UpdateCommand::class, ['--tag' => 'v1.0.0'])
        ->expectsOutputToContain('did not match their checksum')
        ->doesntExpectOutputToContain('maintenance mode')
        ->assertExitCode(1);

    Process::assertDidntRun(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'down']);

    $state = resolve(UpdateService::class)->state();

    expect($state['status'])->toBe('failed')
        ->and($state['step'])->toBe('Download frontend assets')
        ->and($state['site_down'])->toBeFalse();
});

it('stops before maintenance mode when the release has to be built and Node.js is missing', function (): void {
    Process::fake([
        '*--version*' => Process::result(errorOutput: 'npm: not found', exitCode: 127),
        '*' => Process::result(),
    ]);

    resolve(UpdateService::class)->writeCurrentVersion('v0.9.0');

    $this->artisan(UpdateCommand::class, ['--tag' => 'v1.0.0'])
        ->expectsOutputToContain('npm: not found')
        ->doesntExpectOutputToContain('maintenance mode')
        ->assertExitCode(1);

    Process::assertDidntRun(fn (PendingProcess $process): bool => $process->command === [PHP_BINARY, 'artisan', 'down']);

    $state = resolve(UpdateService::class)->state();

    expect($state['step'])->toBe('Check Node.js')
        ->and($state['site_down'])->toBeFalse();
});
