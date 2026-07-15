<?php

declare(strict_types=1);

use App\Console\Commands\PruneImageCacheCommand;
use Illuminate\Support\Facades\File;

beforeEach(fn () => File::deleteDirectory(config('media.cache_path')));

afterEach(fn () => File::deleteDirectory(config('media.cache_path')));

it('prunes the oldest cached variants down to the size cap', function (): void {
    $root = config('media.cache_path');
    File::ensureDirectoryExists($root.'/a');
    File::put($root.'/a/old.jpg', str_repeat('a', 1024 * 1024));
    touch($root.'/a/old.jpg', time() - 100);
    File::put($root.'/a/new.jpg', str_repeat('b', 1024 * 1024));

    config()->set('media.transform_cache_max_megabytes', 1);

    $this->artisan(PruneImageCacheCommand::class)
        ->expectsOutputToContain('Pruned 1 cached image variants.')
        ->assertExitCode(0);

    expect(File::exists($root.'/a/old.jpg'))->toBeFalse()
        ->and(File::exists($root.'/a/new.jpg'))->toBeTrue();

    $this->artisan(PruneImageCacheCommand::class)
        ->expectsOutputToContain('within its size cap')
        ->assertExitCode(0);
});

it('reports an empty cache as within the cap', function (): void {
    $this->artisan(PruneImageCacheCommand::class)
        ->expectsOutputToContain('within its size cap')
        ->assertExitCode(0);
});
