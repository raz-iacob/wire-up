<?php

declare(strict_types=1);

use App\Console\Commands\CheckForUpdatesCommand;
use App\Services\UpdateService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('wireup.version_file', storage_path('framework/testing/wireup-'.Str::random(8).'/version'));
});

afterEach(function (): void {
    File::deleteDirectory(dirname((string) config('wireup.version_file')));
});

it('reports an available update', function (): void {
    Process::fake(['*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0")]);
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
