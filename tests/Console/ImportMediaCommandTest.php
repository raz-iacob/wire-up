<?php

declare(strict_types=1);

use App\Models\Media;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(config()->string('filesystems.media'));

    $this->sandbox = storage_path('framework/testing/cli-import-'.bin2hex(random_bytes(5)));
    File::ensureDirectoryExists($this->sandbox);
});

afterEach(function (): void {
    File::deleteDirectory($this->sandbox);
});

function cliFile(string $name, string $contents = '<svg xmlns="http://www.w3.org/2000/svg"></svg>'): string
{
    $path = test()->sandbox.'/'.$name;
    file_put_contents($path, $contents);

    return $path;
}

it('imports a file from any readable path', function (): void {
    $path = cliFile('poster.svg');

    $this->artisan('wireup:media:import', ['path' => [$path]])
        ->assertSuccessful();

    expect(Media::query()->sole()->filename)->toBe('poster.svg');
});

it('imports several files in one call', function (): void {
    $this->artisan('wireup:media:import', ['path' => [
        cliFile('one.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>'),
        cliFile('two.svg', '<svg xmlns="http://www.w3.org/2000/svg"><circle/></svg>'),
    ]])->assertSuccessful();

    expect(Media::query()->count())->toBe(2);
});

it('applies the given alt text', function (): void {
    $this->artisan('wireup:media:import', ['path' => [cliFile('hero.svg')], '--alt' => 'A hero image'])
        ->assertSuccessful();

    expect(Media::query()->sole()->alt_text)->toBe('A hero image');
});

it('falls back to the file name when no alt text is given', function (): void {
    $this->artisan('wireup:media:import', ['path' => [cliFile('fallback-name.svg')]])
        ->assertSuccessful();

    expect(Media::query()->sole()->alt_text)->toBe('fallback-name');
});

it('fails when a file does not exist', function (): void {
    $this->artisan('wireup:media:import', ['path' => [$this->sandbox.'/missing.png']])
        ->expectsOutputToContain('No file at')
        ->assertFailed();

    expect(Media::query()->count())->toBe(0);
});

it('keeps importing the remaining files after one fails', function (): void {
    $this->artisan('wireup:media:import', ['path' => [
        $this->sandbox.'/missing.png',
        cliFile('survivor.svg'),
    ]])->assertFailed();

    expect(Media::query()->sole()->filename)->toBe('survivor.svg');
});

it('reports a file it cannot convert without aborting the run', function (): void {
    $this->artisan('wireup:media:import', ['path' => [
        cliFile('broken.heic', 'not an image'),
        cliFile('fine.svg'),
    ]])
        ->expectsOutputToContain('broken.heic')
        ->assertFailed();

    expect(Media::query()->sole()->filename)->toBe('fine.svg');
});
