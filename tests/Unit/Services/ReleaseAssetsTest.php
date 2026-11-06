<?php

declare(strict_types=1);

use App\Services\ReleaseAssets;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\Support\ReleaseArchive;

beforeEach(function (): void {
    $root = storage_path('framework/testing/release-assets-'.Str::random(8));

    config()->set('wireup.updates_path', $root.'/updates');
    config()->set('wireup.build_path', $root.'/public/build');
});

afterEach(function (): void {
    File::deleteDirectory(dirname((string) config('wireup.updates_path')));
});

it('builds the download address from a GitHub remote', function (string $remote): void {
    Process::fake(['*get-url*' => Process::result(output: "{$remote}\n")]);

    expect(new ReleaseAssets()->url('v1.0.0'))->toBe(ReleaseArchive::url('v1.0.0'));
})->with([
    'ssh' => 'git@github.com:wire-up-dev/wire-up.git',
    'https' => 'https://github.com/wire-up-dev/wire-up.git',
    'https without suffix' => 'https://github.com/wire-up-dev/wire-up',
    'ssh address' => 'ssh://git@github.com/wire-up-dev/wire-up.git',
]);

it('looks for nothing to download when the remote is not on GitHub', function (string $remote, int $exitCode): void {
    Http::fake();
    Process::fake(['*get-url*' => Process::result(output: $remote, exitCode: $exitCode)]);

    $assets = new ReleaseAssets();

    expect($assets->url('v1.0.0'))->toBeNull()
        ->and($assets->published('v1.0.0'))->toBeFalse();

    Http::assertNothingSent();
})->with([
    'another host' => ['git@gitlab.com:someone/wire-up.git', 0],
    'no remote' => ['', 2],
]);

it('knows whether a release has prebuilt assets from its published checksum', function (Closure $response, bool $published): void {
    Process::fake(['*get-url*' => Process::result(output: ReleaseArchive::REMOTE)]);
    Http::fake([ReleaseArchive::url('v1.0.0', 'build.tar.gz.sha256') => $response()]);

    expect(new ReleaseAssets()->published('v1.0.0'))->toBe($published);
})->with([
    'published' => [fn (): mixed => Http::response(str_repeat('a', 64)."  build.tar.gz\n"), true],
    'missing' => [fn (): mixed => Http::response('Not Found', 404), false],
    'not a checksum' => [fn (): mixed => Http::response('<html>'), false],
    'unreachable' => [fn (): mixed => Http::failedConnection(), false],
]);

it('treats a release as still building only while the installed release has prebuilt assets', function (bool $tagPublished, bool $currentPublished, bool $stillBuilding): void {
    Process::fake(['*get-url*' => Process::result(output: ReleaseArchive::REMOTE)]);
    Http::fake([
        ReleaseArchive::url('v1.0.0', 'build.tar.gz.sha256') => $tagPublished ? Http::response(str_repeat('a', 64)) : Http::response('Not Found', 404),
        ReleaseArchive::url('v0.9.0', 'build.tar.gz.sha256') => $currentPublished ? Http::response(str_repeat('b', 64)) : Http::response('Not Found', 404),
    ]);

    expect(new ReleaseAssets()->stillBuilding('v1.0.0', 'v0.9.0'))->toBe($stillBuilding);
})->with([
    'building' => [false, true, true],
    'ready' => [true, true, false],
    'never published' => [false, false, false],
]);

it('downloads the prebuilt assets once they match their checksum', function (): void {
    $archive = ReleaseArchive::make();
    Process::fake(['*get-url*' => Process::result(output: ReleaseArchive::REMOTE)]);
    Http::fake([
        ReleaseArchive::url('v1.0.0', 'build.tar.gz.sha256') => Http::response(hash('sha256', $archive)),
        ReleaseArchive::url('v1.0.0') => Http::response($archive),
    ]);

    $path = new ReleaseAssets()->download('v1.0.0');

    expect($path)->toBe(config('wireup.updates_path').'/build.tar.gz')
        ->and(File::get($path))->toBe($archive);
});

it('refuses prebuilt assets that do not match their checksum', function (): void {
    Process::fake(['*get-url*' => Process::result(output: ReleaseArchive::REMOTE)]);
    Http::fake([
        ReleaseArchive::url('v1.0.0', 'build.tar.gz.sha256') => Http::response(str_repeat('a', 64)),
        ReleaseArchive::url('v1.0.0') => Http::response('tampered'),
    ]);

    expect(fn (): string => new ReleaseAssets()->download('v1.0.0'))
        ->toThrow(RuntimeException::class, 'did not match their checksum')
        ->and(File::exists(config('wireup.updates_path').'/build.tar.gz'))->toBeFalse();
});

it('refuses to download assets that were never published', function (): void {
    Process::fake(['*get-url*' => Process::result(output: ReleaseArchive::REMOTE)]);
    Http::fake([ReleaseArchive::url('v1.0.0', 'build.tar.gz.sha256') => Http::response('Not Found', 404)]);

    expect(fn (): string => new ReleaseAssets()->download('v1.0.0'))
        ->toThrow(RuntimeException::class, 'No prebuilt frontend assets were published for v1.0.0.');
});

it('replaces the current frontend assets with the downloaded ones', function (): void {
    $build = (string) config('wireup.build_path');
    $updates = (string) config('wireup.updates_path');
    File::ensureDirectoryExists($build.'/assets');
    File::put($build.'/manifest.json', '{"old":true}');
    File::put($build.'/assets/old.css', 'old');
    File::ensureDirectoryExists($updates);
    File::put($updates.'/build.tar.gz', ReleaseArchive::make(['build/manifest.json' => '{"new":true}', 'build/assets/new.css' => 'new']));

    new ReleaseAssets()->install();

    expect(File::get($build.'/manifest.json'))->toBe('{"new":true}')
        ->and(File::get($build.'/assets/new.css'))->toBe('new')
        ->and(File::exists($build.'/assets/old.css'))->toBeFalse()
        ->and(File::exists($build.'-next'))->toBeFalse()
        ->and(File::exists($build.'-previous'))->toBeFalse()
        ->and(File::exists($updates))->toBeFalse();
});

it('keeps the current frontend assets when the download has no manifest', function (): void {
    $build = (string) config('wireup.build_path');
    $updates = (string) config('wireup.updates_path');
    File::ensureDirectoryExists($build);
    File::put($build.'/manifest.json', '{"old":true}');
    File::ensureDirectoryExists($updates);
    File::put($updates.'/build.tar.gz', ReleaseArchive::make(['build/assets/new.css' => 'new']));

    expect(fn () => new ReleaseAssets()->install())
        ->toThrow(RuntimeException::class, 'The downloaded frontend assets have no manifest.')
        ->and(File::get($build.'/manifest.json'))->toBe('{"old":true}')
        ->and(File::exists($build.'-next'))->toBeFalse();
});

it('keeps a copy of the current frontend assets and can put it back', function (): void {
    $build = (string) config('wireup.build_path');
    File::ensureDirectoryExists($build);
    File::put($build.'/manifest.json', '{"release":"v0.9.0"}');

    $assets = new ReleaseAssets();
    $assets->snapshotBuild();

    File::put($build.'/manifest.json', '{"release":"v1.0.0"}');

    $assets->restoreSnapshot();

    expect(File::get($build.'/manifest.json'))->toBe('{"release":"v0.9.0"}')
        ->and(File::exists($build.'-rollback'))->toBeFalse();
});

it('has nothing to keep or put back when there are no frontend assets yet', function (): void {
    $build = (string) config('wireup.build_path');

    $assets = new ReleaseAssets();
    $assets->snapshotBuild();
    $assets->restoreSnapshot();

    expect(File::exists($build))->toBeFalse()
        ->and(File::exists($build.'-rollback'))->toBeFalse();
});

it('drops the kept copy once the update has finished', function (): void {
    $build = (string) config('wireup.build_path');
    File::ensureDirectoryExists($build);
    File::put($build.'/manifest.json', '{}');

    $assets = new ReleaseAssets();
    $assets->snapshotBuild();
    $assets->discardSnapshot();

    expect(File::exists($build.'-rollback'))->toBeFalse()
        ->and(File::exists($build.'/manifest.json'))->toBeTrue();
});
