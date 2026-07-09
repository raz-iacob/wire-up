<?php

declare(strict_types=1);

use App\Services\UpdateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('wireup.version_file', storage_path('framework/testing/wireup-'.Str::random(8).'/version'));
});

afterEach(function (): void {
    File::deleteDirectory(dirname((string) config('wireup.version_file')));
});

it('returns null when the version file is missing', function (): void {
    expect(new UpdateService()->currentVersion())->toBeNull();
});

it('returns null when the version file is blank', function (): void {
    $service = new UpdateService();
    $service->writeCurrentVersion("  \n");

    expect($service->currentVersion())->toBeNull();
});

it('reads the trimmed version from the version file', function (): void {
    $service = new UpdateService();
    $service->writeCurrentVersion("v1.2.3\n");

    expect($service->currentVersion())->toBe('v1.2.3');
});

it('refreshes the current version from git', function (): void {
    Process::fake(['*describe*' => Process::result(output: "v1.2.3\n")]);

    $service = new UpdateService();

    expect($service->refreshCurrentVersionFromGit())->toBe('v1.2.3')
        ->and($service->currentVersion())->toBe('v1.2.3');
});

it('returns null when git describe fails', function (): void {
    Process::fake(['*describe*' => Process::result(exitCode: 1)]);

    $service = new UpdateService();

    expect($service->refreshCurrentVersionFromGit())->toBeNull()
        ->and($service->currentVersion())->toBeNull();
});

it('returns null when git describe reports no tag', function (): void {
    Process::fake(['*describe*' => Process::result(output: '')]);

    $service = new UpdateService();

    expect($service->refreshCurrentVersionFromGit())->toBeNull()
        ->and($service->currentVersion())->toBeNull();
});

it('picks the highest semver tag from the remote, ignoring peeled and non-semver refs', function (): void {
    Process::fake(['*ls-remote*' => Process::result(output: implode("\n", [
        "aaa\trefs/tags/v0.9.1",
        "bbb\trefs/tags/v0.10.0",
        "ccc\trefs/tags/v0.10.0^{}",
        "ddd\trefs/tags/not-semver",
        "eee\trefs/tags/v1.0.0-beta",
    ]))]);

    $service = new UpdateService();

    expect($service->check())->toBe('v0.10.0')
        ->and($service->latestVersion())->toBe('v0.10.0')
        ->and($service->lastCheckedAt()?->toIso8601String())->toBe(now()->toIso8601String());
});

it('caches a null latest version when the remote has no tags', function (): void {
    Process::fake(['*ls-remote*' => Process::result(output: '')]);

    $service = new UpdateService();

    expect($service->check())->toBeNull()
        ->and($service->latestVersion())->toBeNull()
        ->and($service->lastCheckedAt()?->toIso8601String())->toBe(now()->toIso8601String());
});

it('caches a null latest version when the remote is unreachable', function (): void {
    Process::fake(['*ls-remote*' => Process::result(exitCode: 1)]);

    $service = new UpdateService();

    expect($service->check())->toBeNull()
        ->and($service->latestVersion())->toBeNull();
});

it('returns no latest version before any check ran', function (): void {
    $service = new UpdateService();

    expect($service->latestVersion())->toBeNull()
        ->and($service->lastCheckedAt())->toBeNull();
});

it('ignores a malformed cached check result', function (): void {
    Cache::forever('wireup:update:latest', ['version' => 123, 'checked_at' => 456]);

    $service = new UpdateService();

    expect($service->latestVersion())->toBeNull()
        ->and($service->lastCheckedAt())->toBeNull();
});

it('reports whether an update is available', function (string $current, string $latest, bool $expected): void {
    Process::fake(['*ls-remote*' => Process::result(output: "aaa\trefs/tags/{$latest}")]);

    $service = new UpdateService();
    $service->writeCurrentVersion($current);
    $service->check();

    expect($service->updateAvailable())->toBe($expected);
})->with([
    'newer remote' => ['v0.9.1', 'v0.10.0', true],
    'same version' => ['v1.0.0', 'v1.0.0', false],
    'older remote' => ['v1.1.0', 'v1.0.0', false],
]);

it('reports no update when either version is unknown', function (): void {
    Process::fake(['*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0")]);

    $service = new UpdateService();

    expect($service->updateAvailable())->toBeFalse();

    $service->check();

    expect($service->updateAvailable())->toBeFalse();
});
