<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Settings;
use App\Models\User;
use App\Services\SiteBundle;
use App\Services\SiteExporter;
use App\Services\SiteImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('wireup.transfer_path', storage_path('framework/testing/imports-'.Str::random(8)));
    Storage::fake(config()->string('filesystems.media'));
});

afterEach(function (): void {
    File::deleteDirectory(config()->string('wireup.transfer_path'));
});

function importPath(string $name = 'bundle.zip'): string
{
    $path = config()->string('wireup.transfer_path').'/'.$name;
    File::ensureDirectoryExists(dirname($path));

    return $path;
}

/**
 * @param  array<string, array<int, array<string, mixed>>>  $tables
 * @param  array<string, string>  $files
 * @param  array<string, mixed>  $manifestOverrides
 */
function makeBundle(array $tables = [], array $files = [], array $manifestOverrides = []): string
{
    $tables['locales'] ??= [[
        'id' => 1, 'code' => 'en', 'name' => 'English', 'endonym' => 'English', 'script' => 'Latn',
        'regional' => 'en_GB', 'rtl' => 0, 'active' => 1, 'published' => 1, 'created_at' => null, 'updated_at' => null,
    ]];

    $site = [];
    $counts = [];

    foreach (SiteBundle::TABLES as $table) {
        $site[$table] = $tables[$table] ?? [];
        $counts[$table] = count($site[$table]);
    }

    $manifest = [...SiteBundle::current()->manifest($counts, false, count($files), 0, []), ...$manifestOverrides];

    $path = importPath('made-'.Str::random(6).'.zip');

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString(SiteBundle::MANIFEST_ENTRY, (string) json_encode($manifest));
    $zip->addFromString(SiteBundle::SITE_ENTRY, (string) json_encode($site));

    foreach ($files as $name => $contents) {
        $zip->addFromString($name, $contents);
    }

    $zip->close();

    return $path;
}

it('reports what a bundle holds without writing anything', function (): void {
    Page::factory()->create(['title' => 'Original Page', 'status' => ContentStatus::PUBLISHED]);

    $bundle = makeBundle(
        ['pages' => [['id' => 500, 'metadata' => '{}', 'status' => 'published', 'published_at' => null, 'created_by' => null, 'updated_by' => null, 'created_at' => null, 'updated_at' => null]]],
    );

    $before = DB::table('pages')->count();
    $report = SiteImporter::current()->inspect($bundle);

    expect($report['imported'])->toBeFalse()
        ->and($report['problems'])->toBe([])
        ->and($report['tables']['pages'])->toBe(1)
        ->and(DB::table('pages')->count())->toBe($before)
        ->and(DB::table('pages')->where('id', 500)->exists())->toBeFalse();
});

it('replaces the whole site from the bundle', function (): void {
    $keep = Page::factory()->create(['title' => 'Doomed Page', 'status' => ContentStatus::PUBLISHED]);

    $bundle = makeBundle([
        'pages' => [['id' => 500, 'metadata' => '{}', 'status' => 'published', 'published_at' => null, 'created_by' => null, 'updated_by' => null, 'created_at' => null, 'updated_at' => null]],
        'translations' => [['id' => 900, 'key' => 'title', 'body' => 'Imported Page', 'locale' => 'en', 'translatable_type' => 'page', 'translatable_id' => 500, 'created_at' => null, 'updated_at' => null]],
    ]);

    $report = SiteImporter::current()->import($bundle, null);

    expect($report['imported'])->toBeTrue()
        ->and(DB::table('pages')->where('id', $keep->id)->exists())->toBeFalse()
        ->and(DB::table('pages')->where('id', 500)->exists())->toBeTrue()
        ->and(Page::query()->find(500)?->title)->toBe('Imported Page');
});

it('credits imported content to the importing owner', function (): void {
    $owner = User::factory()->create();

    $bundle = makeBundle([
        'pages' => [
            ['id' => 501, 'metadata' => '{}', 'status' => 'draft', 'published_at' => null, 'created_by' => 99, 'updated_by' => 99, 'created_at' => null, 'updated_at' => null],
            ['id' => 502, 'metadata' => '{}', 'status' => 'draft', 'published_at' => null, 'created_by' => null, 'updated_by' => null, 'created_at' => null, 'updated_at' => null],
        ],
    ]);

    SiteImporter::current()->import($bundle, $owner->id);

    expect(DB::table('pages')->where('id', 501)->value('created_by'))->toBe($owner->id)
        ->and(DB::table('pages')->where('id', 501)->value('updated_by'))->toBe($owner->id)
        ->and(DB::table('pages')->where('id', 502)->value('created_by'))->toBeNull();
});

it('coerces a plain string field label into a localized array', function (): void {
    $bundle = makeBundle([
        'record_types' => [[
            'id' => 7, 'key' => 'product', 'slug_prefix' => 'store', 'icon' => 'cube', 'name' => 'Products',
            'fields' => (string) json_encode([
                ['key' => 'price', 'type' => 'money', 'label' => 'Price'],
                ['key' => 'sku', 'type' => 'text', 'label' => ['en' => 'SKU']],
                ['key' => 'blank', 'type' => 'text', 'label' => null],
                'not-a-field',
            ]),
            'breadcrumbs' => 0, 'position' => 0, 'created_at' => null, 'updated_at' => null,
        ]],
    ]);

    SiteImporter::current()->import($bundle, null);

    $fields = RecordType::query()->find(7)?->fields ?? [];

    expect($fields[0]['label'])->toBe(['en' => 'Price'])
        ->and($fields[1]['label'])->toBe(['en' => 'SKU'])
        ->and($fields[2]['label'])->toBe(['en' => ''])
        ->and($fields[3])->toBe('not-a-field');
});

it('treats unusable record type fields as an empty blueprint', function (): void {
    $bundle = makeBundle([
        'record_types' => [[
            'id' => 8, 'key' => 'thing', 'slug_prefix' => 'things', 'icon' => 'cube', 'name' => 'Things',
            'fields' => 'nonsense', 'breadcrumbs' => 0, 'position' => 0, 'created_at' => null, 'updated_at' => null,
        ]],
    ]);

    SiteImporter::current()->import($bundle, null);

    expect(RecordType::query()->find(8)?->fields)->toBe([]);
});

it('restores media files onto the disk', function (): void {
    $bundle = makeBundle(
        ['media' => [
            ['id' => 300, 'type' => 'image', 'source' => 'media/kept.jpg', 'etag' => 'e1', 'filename' => 'kept.jpg', 'alt_text' => null, 'mime_type' => 'image/jpeg', 'thumbnail' => null, 'size' => 6, 'duration' => null, 'width' => 10, 'height' => 10, 'metadata' => '[]', 'created_at' => null, 'updated_at' => null],
        ]],
        ['media/kept.jpg' => 'JPEGES'],
    );

    $report = SiteImporter::current()->import($bundle, null);

    expect($report['imported'])->toBeTrue()
        ->and($report['media'])->toBe(['expected' => 1, 'present' => 1, 'missing' => []]);

    Storage::disk(config()->string('filesystems.media'))->assertExists('media/kept.jpg');

    expect(Storage::disk(config()->string('filesystems.media'))->get('media/kept.jpg'))->toBe('JPEGES');
});

it('refuses a bundle whose media file is absent and writes nothing', function (): void {
    $page = Page::factory()->create(['title' => 'Untouched', 'status' => ContentStatus::PUBLISHED]);

    $bundle = makeBundle([
        'pages' => [['id' => 510, 'metadata' => '{}', 'status' => 'draft', 'published_at' => null, 'created_by' => null, 'updated_by' => null, 'created_at' => null, 'updated_at' => null]],
        'media' => [
            ['id' => 301, 'type' => 'image', 'source' => 'media/gone.jpg', 'etag' => 'e', 'filename' => 'gone.jpg', 'alt_text' => null, 'mime_type' => 'image/jpeg', 'thumbnail' => null, 'size' => 1, 'duration' => null, 'width' => 1, 'height' => 1, 'metadata' => '[]', 'created_at' => null, 'updated_at' => null],
            ['id' => 302, 'type' => 'image', 'source' => '../escape.jpg', 'etag' => 'e', 'filename' => 'escape.jpg', 'alt_text' => null, 'mime_type' => 'image/jpeg', 'thumbnail' => null, 'size' => 1, 'duration' => null, 'width' => 1, 'height' => 1, 'metadata' => '[]', 'created_at' => null, 'updated_at' => null],
        ],
    ]);

    $report = SiteImporter::current()->import($bundle, null);

    expect($report['imported'])->toBeFalse()
        ->and($report['problems'])->toHaveCount(1)
        ->and($report['problems'][0])->toContain('2 media file(s)')
        ->and($report['media']['missing'])->toBe(['media/gone.jpg', '../escape.jpg'])
        ->and(DB::table('pages')->where('id', $page->id)->exists())->toBeTrue()
        ->and(DB::table('pages')->where('id', 510)->exists())->toBeFalse();
});

it('refuses a bundle from a newer install and writes nothing', function (): void {
    $page = Page::factory()->create(['status' => ContentStatus::PUBLISHED]);

    $bundle = makeBundle(
        ['pages' => [['id' => 520, 'metadata' => '{}', 'status' => 'draft', 'published_at' => null, 'created_by' => null, 'updated_by' => null, 'created_at' => null, 'updated_at' => null]]],
        [],
        ['migrations' => SiteBundle::current()->appliedMigrations() + 5],
    );

    $report = SiteImporter::current()->import($bundle, null);

    expect($report['imported'])->toBeFalse()
        ->and($report['problems'][0])->toContain('newer install')
        ->and(DB::table('pages')->where('id', $page->id)->exists())->toBeTrue();
});

it('refreshes cached settings after an import', function (): void {
    Settings::set(['currency' => 'GBP']);

    expect(config('site.currency'))->toBe('GBP');

    $bundle = makeBundle([
        'settings' => [['id' => 1, 'key' => 'currency', 'value' => '"JPY"', 'created_at' => null, 'updated_at' => null]],
    ]);

    SiteImporter::current()->import($bundle, null);

    expect(Settings::get('currency'))->toBe('JPY')
        ->and(config('site.currency'))->toBe('JPY');
});

it('survives a full export and import round trip', function (): void {
    $type = RecordType::factory()->create(['key' => 'product', 'slug_prefix' => 'store', 'name' => 'Products']);
    $page = Page::factory()->create(['title' => 'Store Front', 'status' => ContentStatus::PUBLISHED]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'store', 'base_path' => '']);
    $record = Record::factory()->for($type)->create(['title' => 'Green Giant', 'status' => ContentStatus::PUBLISHED, 'data' => ['sold' => false]]);
    $record->slugs()->create(['locale' => 'en', 'slug' => 'green-giant', 'base_path' => 'store']);
    Settings::set(['custom_css' => '.a{color:red}']);

    $bundle = importPath('round-trip.zip');
    SiteExporter::current()->export($bundle);

    DB::table('records')->delete();
    DB::table('pages')->delete();
    Settings::set(['custom_css' => 'wiped']);

    $report = SiteImporter::current()->import($bundle, null);

    expect($report['imported'])->toBeTrue()
        ->and(Page::query()->find($page->id)?->title)->toBe('Store Front')
        ->and(Record::query()->find($record->id)?->title)->toBe('Green Giant')
        ->and(Record::query()->find($record->id)?->recordType->key)->toBe('product')
        ->and(Settings::get('custom_css'))->toBe('.a{color:red}');
});

it('fails on a bundle that cannot be opened', function (): void {
    SiteImporter::current()->inspect(importPath('missing.zip'));
})->throws(RuntimeException::class, 'Unable to read the bundle');

it('fails on a bundle with no manifest', function (): void {
    $path = importPath('empty.zip');

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('readme.txt', 'nothing here');
    $zip->close();

    SiteImporter::current()->inspect($path);
})->throws(RuntimeException::class, 'missing manifest.json');

it('fails on a bundle with unreadable json', function (): void {
    $path = importPath('broken.zip');

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString(SiteBundle::MANIFEST_ENTRY, 'not json at all');
    $zip->close();

    SiteImporter::current()->inspect($path);
})->throws(RuntimeException::class, 'unreadable manifest.json');
