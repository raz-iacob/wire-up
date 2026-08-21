<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Settings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('wireup.transfer_path', storage_path('framework/testing/transfers-'.Str::random(8)));
    Storage::fake(config()->string('filesystems.media'));
});

afterEach(function (): void {
    File::deleteDirectory(config()->string('wireup.transfer_path'));
});

it('exports to an explicit path', function (): void {
    $path = config()->string('wireup.transfer_path').'/chosen.zip';

    $this->artisan('wireup:export', ['--path' => $path])
        ->expectsOutputToContain('chosen.zip')
        ->expectsOutputToContain('Site exported.')
        ->assertSuccessful();

    expect(File::exists($path))->toBeTrue();
});

it('exports to a dated file in the transfer directory by default', function (): void {
    $this->artisan('wireup:export')->assertSuccessful();

    $bundles = File::glob(config()->string('wireup.transfer_path').'/site-*.zip');

    expect($bundles)->toHaveCount(1);
});

it('reports whether secrets are included', function (): void {
    Settings::set(['pexels_api_key' => 'secret-key']);

    $this->artisan('wireup:export', ['--path' => config()->string('wireup.transfer_path').'/a.zip'])
        ->expectsOutputToContain('excluded')
        ->assertSuccessful();

    $this->artisan('wireup:export', ['--path' => config()->string('wireup.transfer_path').'/b.zip', '--with-secrets' => true])
        ->expectsOutputToContain('included')
        ->assertSuccessful();
});

it('warns about media files missing from disk', function (): void {
    Media::factory()->create(['type' => MediaType::IMAGE, 'source' => 'media/absent.jpg']);
    Media::factory()->create(['type' => MediaType::IMAGE, 'source' => '']);

    $this->artisan('wireup:export', ['--path' => config()->string('wireup.transfer_path').'/c.zip'])
        ->expectsOutputToContain('2 media file(s) are recorded but missing')
        ->expectsOutputToContain('media/absent.jpg')
        ->expectsOutputToContain('(blank source)')
        ->assertSuccessful();
});

it('reports media size in human units', function (): void {
    Storage::disk(config()->string('filesystems.media'))->put('media/big.jpg', str_repeat('x', 2_500_000));
    Media::factory()->create(['type' => MediaType::IMAGE, 'source' => 'media/big.jpg']);

    $this->artisan('wireup:export', ['--path' => config()->string('wireup.transfer_path').'/d.zip'])
        ->expectsOutputToContain('MB')
        ->assertSuccessful();
});

it('reports a bundle with no media as zero bytes', function (): void {
    $this->artisan('wireup:export', ['--path' => config()->string('wireup.transfer_path').'/e.zip'])
        ->expectsOutputToContain('0 B')
        ->assertSuccessful();
});

it('reports kilobyte-sized media', function (): void {
    Storage::disk(config()->string('filesystems.media'))->put('media/small.jpg', str_repeat('x', 4096));
    Media::factory()->create(['type' => MediaType::IMAGE, 'source' => 'media/small.jpg']);

    $this->artisan('wireup:export', ['--path' => config()->string('wireup.transfer_path').'/f.zip'])
        ->expectsOutputToContain('KB')
        ->assertSuccessful();
});

it('treats a blank path option as absent', function (): void {
    $this->artisan('wireup:export', ['--path' => ''])->assertSuccessful();

    expect(File::glob(config()->string('wireup.transfer_path').'/site-*.zip'))->toHaveCount(1);
});
