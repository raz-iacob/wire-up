<?php

declare(strict_types=1);

use App\Services\SiteBundle;
use App\Services\UpdateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('rejects unsafe archive paths', function (string $path, bool $safe): void {
    expect(SiteBundle::isSafeRelativePath($path))->toBe($safe);
})->with([
    'empty' => ['', false],
    'absolute' => ['/etc/passwd', false],
    'backslash' => ['media\\evil.jpg', false],
    'windows drive' => ['C:/media/evil.jpg', false],
    'parent traversal' => ['media/../../evil.jpg', false],
    'bare traversal' => ['..', false],
    'plain file' => ['media/photo.jpg', true],
    'nested file' => ['media/2026/08/photo.jpg', true],
]);

it('counts the applied migrations', function (): void {
    expect(SiteBundle::current()->appliedMigrations())->toBe(DB::table('migrations')->count());
});

it('reads the installed version for the manifest', function (): void {
    config()->set('wireup.version_file', storage_path('framework/testing/bundle-'.Str::random(8).'/version'));
    resolve(UpdateService::class)->writeCurrentVersion('v9.9.9');

    expect(SiteBundle::current()->appVersion())->toBe('v9.9.9');
});

it('builds a manifest describing the bundle', function (): void {
    $manifest = SiteBundle::current()->manifest(['pages' => 3, 'blocks' => 7], false, 2, 2048, ['media/gone.jpg']);

    expect($manifest['format'])->toBe(SiteBundle::FORMAT)
        ->and($manifest['with_secrets'])->toBeFalse()
        ->and($manifest['tables'])->toBe(['pages' => 3, 'blocks' => 7])
        ->and($manifest['media'])->toBe(['count' => 2, 'bytes' => 2048, 'missing' => ['media/gone.jpg']])
        ->and($manifest['migrations'])->toBe(DB::table('migrations')->count())
        ->and($manifest['exported_at'])->toBe(now()->toIso8601String());
});

it('accepts a manifest from the same install', function (): void {
    $bundle = SiteBundle::current();

    expect($bundle->incompatibilities($bundle->manifest([], false, 0, 0, [])))->toBe([]);
});

it('refuses a bundle written in another format', function (mixed $format): void {
    $problems = SiteBundle::current()->incompatibilities(['format' => $format, 'migrations' => 1]);

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toContain('format');
})->with([
    'older' => [0],
    'newer' => [SiteBundle::FORMAT + 1],
    'missing' => [null],
    'not an integer' => ['1'],
]);

it('refuses a bundle from a newer install', function (): void {
    $bundle = SiteBundle::current();

    $problems = $bundle->incompatibilities([
        'format' => SiteBundle::FORMAT,
        'migrations' => $bundle->appliedMigrations() + 1,
    ]);

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toContain('newer install');
});

it('accepts a bundle from an older install', function (): void {
    $bundle = SiteBundle::current();

    expect($bundle->incompatibilities([
        'format' => SiteBundle::FORMAT,
        'migrations' => $bundle->appliedMigrations() - 1,
    ]))->toBe([]);
});

it('ignores a manifest without a migration count', function (): void {
    expect(SiteBundle::current()->incompatibilities(['format' => SiteBundle::FORMAT]))->toBe([]);
});

it('excludes users and other per-install tables from the transfer', function (): void {
    expect(SiteBundle::TABLES)->not->toContain('users')
        ->and(SiteBundle::TABLES)->not->toContain('roles')
        ->and(SiteBundle::TABLES)->not->toContain('sessions')
        ->and(SiteBundle::TABLES)->not->toContain('submissions')
        ->and(SiteBundle::TABLES)->not->toContain('migrations')
        ->and(SiteBundle::TABLES)->not->toContain('agent_conversations');
});

it('orders tables so parents are inserted before their dependants', function (): void {
    $position = array_flip(SiteBundle::TABLES);

    expect($position['record_types'])->toBeLessThan($position['records'])
        ->and($position['media'])->toBeLessThan($position['mediables'])
        ->and($position['locales'])->toBeLessThan($position['slugs'])
        ->and($position['locales'])->toBeLessThan($position['translations'])
        ->and($position['locales'])->toBeLessThan($position['mediables'])
        ->and($position['categories'])->toBeLessThan($position['categorizables']);
});
