<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\User;
use App\Services\SiteBundle;
use App\Services\SiteExporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('wireup.transfer_path', storage_path('framework/testing/import-cmd-'.Str::random(8)));
    config()->set('wireup.version_file', storage_path('framework/testing/import-cmd-'.Str::random(8).'/missing'));
    Storage::fake(config()->string('filesystems.media'));
});

afterEach(function (): void {
    File::deleteDirectory(config()->string('wireup.transfer_path'));
});

function exportedBundle(): string
{
    $path = config()->string('wireup.transfer_path').'/bundle.zip';
    SiteExporter::current()->export($path);

    return $path;
}

it('fails when the bundle does not exist', function (): void {
    $this->artisan('wireup:import', ['path' => '/tmp/definitely-not-here.zip'])
        ->expectsOutputToContain('No bundle at')
        ->assertFailed();
});

it('summarizes a bundle on a dry run without writing', function (): void {
    Page::factory()->create(['title' => 'Before Import', 'status' => ContentStatus::PUBLISHED]);

    $bundle = exportedBundle();
    $before = DB::table('pages')->count();

    $this->artisan('wireup:import', ['path' => $bundle, '--dry-run' => true])
        ->expectsOutputToContain('pages')
        ->expectsOutputToContain('media files')
        ->expectsOutputToContain('Dry run only')
        ->assertSuccessful();

    expect(DB::table('pages')->count())->toBe($before);
});

it('imports when forced', function (): void {
    $page = Page::factory()->create(['title' => 'Kept Title', 'status' => ContentStatus::PUBLISHED]);
    $bundle = exportedBundle();

    Page::query()->whereKey($page->id)->delete();

    $this->artisan('wireup:import', ['path' => $bundle, '--force' => true])
        ->expectsOutputToContain('Site imported.')
        ->assertSuccessful();

    expect(Page::query()->find($page->id)?->title)->toBe('Kept Title');
});

it('asks before replacing the site and stops when declined', function (): void {
    $page = Page::factory()->create(['title' => 'Survivor', 'status' => ContentStatus::PUBLISHED]);
    $bundle = exportedBundle();

    Page::query()->whereKey($page->id)->update(['status' => ContentStatus::DRAFT]);

    $this->artisan('wireup:import', ['path' => $bundle])
        ->expectsConfirmation('This replaces every page, record, media item and setting on this site. Continue?', 'no')
        ->expectsOutputToContain('Import cancelled.')
        ->assertSuccessful();

    expect(Page::query()->find($page->id)?->status)->toBe(ContentStatus::DRAFT);
});

it('imports when the confirmation is accepted', function (): void {
    $page = Page::factory()->create(['title' => 'Restored', 'status' => ContentStatus::PUBLISHED]);
    $bundle = exportedBundle();

    Page::query()->whereKey($page->id)->delete();

    $this->artisan('wireup:import', ['path' => $bundle])
        ->expectsConfirmation('This replaces every page, record, media item and setting on this site. Continue?', 'yes')
        ->expectsOutputToContain('Site imported.')
        ->assertSuccessful();

    expect(Page::query()->find($page->id)?->title)->toBe('Restored');
});

it('reports problems and refuses to import', function (): void {
    $path = config()->string('wireup.transfer_path').'/newer.zip';
    File::ensureDirectoryExists(dirname($path));

    $site = [];
    foreach (SiteBundle::TABLES as $table) {
        $site[$table] = [];
    }

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString(SiteBundle::MANIFEST_ENTRY, (string) json_encode([
        'format' => SiteBundle::FORMAT,
        'migrations' => SiteBundle::current()->appliedMigrations() + 3,
        'exported_at' => now()->toIso8601String(),
        'app_version' => 'v2.0.0',
    ]));
    $zip->addFromString(SiteBundle::SITE_ENTRY, (string) json_encode($site));
    $zip->close();

    $this->artisan('wireup:import', ['path' => $path, '--force' => true])
        ->expectsOutputToContain('v2.0.0')
        ->expectsOutputToContain('newer install')
        ->assertFailed();
});

it('credits imported content to the given owner', function (): void {
    $stranger = User::factory()->create();
    $owner = User::factory()->create();
    $page = Page::factory()->create(['status' => ContentStatus::PUBLISHED]);
    DB::table('pages')->where('id', $page->id)->update(['created_by' => $stranger->id]);

    $bundle = exportedBundle();

    $this->artisan('wireup:import', ['path' => $bundle, '--force' => true, '--owner' => (string) $owner->id])
        ->assertSuccessful();

    expect(DB::table('pages')->where('id', $page->id)->value('created_by'))->toBe($owner->id);
});

it('falls back to the first user when no owner is given', function (): void {
    $first = User::factory()->create();
    $stranger = User::factory()->create();
    $page = Page::factory()->create(['status' => ContentStatus::PUBLISHED]);
    DB::table('pages')->where('id', $page->id)->update(['created_by' => $stranger->id]);

    $bundle = exportedBundle();

    $this->artisan('wireup:import', ['path' => $bundle, '--force' => true])->assertSuccessful();

    expect(DB::table('pages')->where('id', $page->id)->value('created_by'))->toBe($first->id);
});

it('shows the exported version as unknown when the bundle has none', function (): void {
    $bundle = exportedBundle();

    $this->artisan('wireup:import', ['path' => $bundle, '--dry-run' => true])
        ->expectsOutputToContain('unknown')
        ->assertSuccessful();
});
