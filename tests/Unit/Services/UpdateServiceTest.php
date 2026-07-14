<?php

declare(strict_types=1);

use App\Models\Settings;
use App\Notifications\SystemUpdateCompleted;
use App\Services\UpdateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
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
    Process::fake([
        '*ls-remote*' => Process::result(output: "aaa\trefs/tags/{$latest}"),
        '*fetch*' => Process::result(),
        '*show*' => Process::result(output: ''),
    ]);

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

it('stores release notes from the changelog when an update is available', function (): void {
    Process::fake([
        '*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0"),
        '*fetch*' => Process::result(),
        '*show*' => Process::result(output: implode("\n", [
            '# Changelog',
            '',
            'Intro text.',
            '',
            '## v1.0.0 — 2026-08-01',
            '',
            '- Added dark mode',
            '- Fixed the footer',
            '',
            '## v0.9.0 — 2026-07-01',
            '',
            '- Initial release',
        ])),
    ]);

    $service = new UpdateService();
    $service->writeCurrentVersion('v0.9.0');
    $service->check();

    expect($service->changelog())->toBe([
        ['version' => 'v1.0.0', 'notes' => ['Added dark mode', 'Fixed the footer']],
    ]);
});

it('stores no release notes when the changelog fetch fails', function (): void {
    Process::fake([
        '*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0"),
        '*fetch*' => Process::result(exitCode: 1),
    ]);

    $service = new UpdateService();
    $service->writeCurrentVersion('v0.9.0');
    $service->check();

    expect($service->changelog())->toBe([]);
});

it('stores no release notes when the tag has no changelog', function (): void {
    Process::fake([
        '*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0"),
        '*fetch*' => Process::result(),
        '*show*' => Process::result(exitCode: 1),
    ]);

    $service = new UpdateService();
    $service->writeCurrentVersion('v0.9.0');
    $service->check();

    expect($service->changelog())->toBe([]);
});

it('stores no release notes when the changelog has no newer sections', function (): void {
    Process::fake([
        '*ls-remote*' => Process::result(output: "aaa\trefs/tags/v1.0.0"),
        '*fetch*' => Process::result(),
        '*show*' => Process::result(output: "## v0.9.0\n\n- Old news"),
    ]);

    $service = new UpdateService();
    $service->writeCurrentVersion('v0.9.0');
    $service->check();

    expect($service->changelog())->toBe([]);
});

it('returns an empty changelog when the cached notes are malformed', function (): void {
    Cache::forever('wireup:update:latest', ['version' => 'v1.0.0', 'notes' => 'nope']);

    expect(new UpdateService()->changelog())->toBe([]);

    Cache::forever('wireup:update:latest', ['version' => 'v1.0.0', 'notes' => [['version' => 1, 'notes' => 'x'], 'junk']]);

    expect(new UpdateService()->changelog())->toBe([]);

    Cache::forever('wireup:update:latest', ['version' => 'v1.0.0', 'notes' => [['version' => 'v1.0.0', 'notes' => 'not-a-list']]]);

    expect(new UpdateService()->changelog())->toBe([]);
});

it('drops non-string note lines from the cached changelog', function (): void {
    Cache::forever('wireup:update:latest', ['version' => 'v1.0.0', 'notes' => [['version' => 'v1.0.0', 'notes' => ['Real note', 42]]]]);

    expect(new UpdateService()->changelog())->toBe([
        ['version' => 'v1.0.0', 'notes' => ['Real note']],
    ]);
});

it('starts with an idle state', function (): void {
    $service = new UpdateService();

    expect($service->state())->toBe(['status' => 'idle', 'tag' => null, 'step' => null, 'output' => null, 'at' => null])
        ->and($service->updating())->toBeFalse();
});

it('tracks the update lifecycle', function (): void {
    $service = new UpdateService();

    $service->markPending('v1.0.0');

    expect($service->state()['status'])->toBe('pending')
        ->and($service->state()['tag'])->toBe('v1.0.0')
        ->and($service->updating())->toBeTrue();

    $service->markRunning('v1.0.0', 'Run database migrations');

    expect($service->state()['status'])->toBe('running')
        ->and($service->state()['step'])->toBe('Run database migrations')
        ->and($service->updating())->toBeTrue();

    $service->markFinished('v1.0.0');

    expect($service->state()['status'])->toBe('finished')
        ->and($service->updating())->toBeFalse();

    $service->clearState();

    expect($service->state()['status'])->toBe('idle');
});

it('truncates failure output in the state', function (): void {
    $service = new UpdateService();
    $service->markFailed('v1.0.0', 'Build frontend assets', str_repeat('x', 3000).'END');

    $output = $service->state()['output'];

    expect($service->state()['status'])->toBe('failed')
        ->and($output)->toEndWith('END')
        ->and(mb_strlen((string) $output))->toBe(2000);
});

it('marks a silent pending update as stalled', function (): void {
    $service = new UpdateService();
    $service->markPending('v1.0.0');

    $this->travel(9)->minutes();

    expect($service->state()['status'])->toBe('pending');

    $this->travel(2)->minutes();

    expect($service->state()['status'])->toBe('stalled')
        ->and($service->updating())->toBeFalse();
});

it('marks a silent running update as stalled', function (): void {
    $service = new UpdateService();
    $service->markRunning('v1.0.0', 'Install PHP dependencies');

    $this->travel(29)->minutes();

    expect($service->state()['status'])->toBe('running');

    $this->travel(2)->minutes();

    expect($service->state()['status'])->toBe('stalled');
});

it('ignores a malformed cached state', function (): void {
    Cache::forever('wireup:update:state', ['status' => 123, 'tag' => 5, 'step' => [], 'output' => 9, 'at' => 456]);

    expect(new UpdateService()->state())->toBe(['status' => 'idle', 'tag' => null, 'step' => null, 'output' => null, 'at' => null]);
});

it('emails the contact address when an update finishes', function (): void {
    Notification::fake();
    Settings::set(['contact_email' => 'owner@example.com']);

    resolve(UpdateService::class)->markFinished('v1.2.0');

    Notification::assertSentOnDemandTimes(SystemUpdateCompleted::class, 1);
});

it('emails the contact address when an update fails', function (): void {
    Notification::fake();
    Settings::set(['contact_email' => 'owner@example.com']);

    resolve(UpdateService::class)->markFailed('v1.2.0', 'Run database migrations', 'migration exploded');

    Notification::assertSentOnDemand(
        SystemUpdateCompleted::class,
        fn (SystemUpdateCompleted $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'owner@example.com',
    );
});

it('sends no outcome mail when no contact email is configured', function (): void {
    Notification::fake();

    resolve(UpdateService::class)->markFinished('v1.2.0');

    Notification::assertNothingSent();
});

it('keeps the update state when the outcome mail cannot be sent', function (): void {
    Settings::set(['contact_email' => 'owner@example.com']);

    Notification::shouldReceive('sendNow')->once()->andThrow(new RuntimeException('smtp down'));

    $service = resolve(UpdateService::class);
    $service->markFailed('v1.2.0', 'Build frontend assets', 'boom');

    expect($service->state()['status'])->toBe('failed');
});
