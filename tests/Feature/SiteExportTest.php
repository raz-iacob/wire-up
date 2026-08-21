<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Page;
use App\Models\Settings;
use App\Services\MediaItem;
use App\Services\SiteBundle;
use App\Services\SiteExporter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('wireup.transfer_path', storage_path('framework/testing/transfers-'.Str::random(8)));
});

afterEach(function (): void {
    File::deleteDirectory(config()->string('wireup.transfer_path'));
});

function exportPath(string $name = 'bundle.zip'): string
{
    return config()->string('wireup.transfer_path').'/'.$name;
}

/**
 * @return array<string, mixed>
 */
function bundleEntry(string $zipPath, string $entry): array
{
    $zip = new ZipArchive;
    $zip->open($zipPath);
    $contents = (string) $zip->getFromName($entry);
    $zip->close();

    $decoded = json_decode($contents, true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * @return array<int, string>
 */
function bundleEntries(string $zipPath): array
{
    $zip = new ZipArchive;
    $zip->open($zipPath);

    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = (string) $zip->getNameIndex($i);
    }
    $zip->close();

    return $names;
}

/**
 * @param  array<string, mixed>  $rows
 * @return array<string, mixed>
 */
function bundleRow(array $rows, string $column, mixed $value): array
{
    foreach ($rows as $row) {
        if (is_array($row) && ($row[$column] ?? null) === $value) {
            return $row;
        }
    }

    return [];
}

function storedMedia(string $source, string $body = 'binary'): Media
{
    Storage::disk(config()->string('filesystems.media'))->put($source, $body);

    return Media::factory()->create(['type' => MediaType::IMAGE, 'source' => $source]);
}

it('writes a bundle holding a manifest, the site rows and the media files', function (): void {
    Storage::fake(config()->string('filesystems.media'));

    Page::factory()->create(['status' => ContentStatus::PUBLISHED, 'title' => 'Exported Page']);
    storedMedia('media/photo.jpg');

    $path = exportPath();
    $manifest = SiteExporter::current()->export($path);

    expect(File::exists($path))->toBeTrue();

    $entries = bundleEntries($path);

    expect($entries)->toContain(SiteBundle::MANIFEST_ENTRY)
        ->and($entries)->toContain(SiteBundle::SITE_ENTRY)
        ->and($entries)->toContain('media/photo.jpg');

    expect($manifest['media']['count'])->toBe(1)
        ->and($manifest['media']['missing'])->toBe([])
        ->and($manifest['tables']['pages'])->toBe(Page::query()->count());

    $site = bundleEntry($path, SiteBundle::SITE_ENTRY);

    expect(array_keys($site))->toBe(SiteBundle::TABLES)
        ->and($site['media'])->toHaveCount(1);
});

it('excludes credential settings by default', function (): void {
    Storage::fake(config()->string('filesystems.media'));

    Settings::set(['pexels_api_key' => 'secret-key', 'mail_password' => 'hunter2', 'currency' => 'CAD']);

    $site = bundleEntry(tap(exportPath(), fn (string $path): array => SiteExporter::current()->export($path)), SiteBundle::SITE_ENTRY);

    $keys = array_column($site['settings'], 'key');

    expect($keys)->not->toContain('pexels_api_key')
        ->and($keys)->not->toContain('mail_password')
        ->and($keys)->toContain('currency');
});

it('includes credential settings on request', function (): void {
    Storage::fake(config()->string('filesystems.media'));

    Settings::set(['pexels_api_key' => 'secret-key']);

    $path = exportPath();
    $manifest = SiteExporter::current()->export($path, withSecrets: true);

    $keys = array_column(bundleEntry($path, SiteBundle::SITE_ENTRY)['settings'], 'key');

    expect($manifest['with_secrets'])->toBeTrue()
        ->and($keys)->toContain('pexels_api_key');
});

it('strips signed preview urls out of exported json', function (): void {
    Storage::fake(config()->string('filesystems.media'));

    $media = storedMedia('media/hero.jpg');

    $page = Page::factory()->create(['status' => ContentStatus::PUBLISHED]);
    $page->blocks()->create([
        'type' => 'photo',
        'position' => 0,
        'content' => ['image' => MediaItem::fromMedia($media)],
    ]);

    Settings::set(['favicon' => MediaItem::fromMedia($media)]);

    $path = exportPath();
    SiteExporter::current()->export($path);

    $zip = new ZipArchive;
    $zip->open($path);
    $raw = (string) $zip->getFromName(SiteBundle::SITE_ENTRY);
    $zip->close();

    expect($raw)->not->toContain('signature=')
        ->not->toContain('"preview"')
        ->not->toContain('"crop_src"')
        ->and($raw)->toContain('media/hero.jpg');

    $site = bundleEntry($path, SiteBundle::SITE_ENTRY);
    $row = bundleRow($site['blocks'], 'blockable_id', $page->id);
    $block = json_decode((string) $row['content'], true);

    expect($block['image']['source'])->toBe('media/hero.jpg')
        ->and($block['image'])->toHaveKey('id')
        ->and($block['image'])->not->toHaveKey('preview');
});

it('reports media rows whose file is missing from disk', function (): void {
    Storage::fake(config()->string('filesystems.media'));

    storedMedia('media/present.jpg');
    Media::factory()->create(['type' => MediaType::IMAGE, 'source' => 'media/absent.jpg']);
    Media::factory()->create(['type' => MediaType::IMAGE, 'source' => '']);
    Media::factory()->create(['type' => MediaType::IMAGE, 'source' => 'media/../escape.jpg']);

    $manifest = SiteExporter::current()->export(exportPath());

    expect($manifest['media']['count'])->toBe(1)
        ->and($manifest['media']['missing'])->toBe(['media/absent.jpg', '', 'media/../escape.jpg']);
});

it('leaves json columns untouched when there is nothing to strip', function (): void {
    Storage::fake(config()->string('filesystems.media'));

    $page = Page::factory()->create(['status' => ContentStatus::PUBLISHED]);
    $page->blocks()->create(['type' => 'rich-text', 'position' => 0, 'content' => ['body' => ['en' => '<p>Hello</p>']]]);

    Settings::set(['currency' => 'CAD', 'menus' => []]);

    $path = exportPath();
    SiteExporter::current()->export($path);

    $site = bundleEntry($path, SiteBundle::SITE_ENTRY);
    $row = bundleRow($site['blocks'], 'blockable_id', $page->id);
    $content = json_decode((string) $row['content'], true);

    expect($content['body']['en'])->toBe('<p>Hello</p>');
});

it('fails when the bundle cannot be written', function (): void {
    Storage::fake(config()->string('filesystems.media'));

    $directory = config()->string('wireup.transfer_path').'/occupied.zip';
    File::ensureDirectoryExists($directory);

    SiteExporter::current()->export($directory);
})->throws(RuntimeException::class, 'Unable to write the bundle');

it('leaves text that only looks like json alone', function (): void {
    Storage::fake(config()->string('filesystems.media'));

    Page::factory()->create(['status' => ContentStatus::PUBLISHED, 'title' => '{not actually json']);

    $path = exportPath();
    SiteExporter::current()->export($path);

    $site = bundleEntry($path, SiteBundle::SITE_ENTRY);

    expect(bundleRow($site['translations'], 'body', '{not actually json'))->not->toBe([]);
});
