<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Jobs\RunSiteImport;
use App\Models\Page;
use App\Services\SiteBundle;
use App\Services\SiteExporter;
use App\Services\SiteImporter;
use App\Services\TransferService;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('wireup.transfer_path', storage_path('framework/testing/job-'.Str::random(8)));
    Storage::fake(config()->string('filesystems.media'));
});

afterEach(function (): void {
    File::deleteDirectory(config()->string('wireup.transfer_path'));
});

function jobBundle(string $name = 'job.zip'): string
{
    $path = config()->string('wireup.transfer_path').'/'.$name;
    SiteExporter::current()->export($path);

    return $path;
}

function runImportJob(string $path, ?int $ownerId = null, bool $deleteBundle = false): void
{
    new RunSiteImport($path, $ownerId, $deleteBundle)->handle(
        resolve(SiteImporter::class),
        resolve(TransferService::class),
    );
}

it('imports and reports a summary when an import is pending', function (): void {
    $page = Page::factory()->create(['title' => 'From The Job', 'status' => ContentStatus::PUBLISHED]);
    $bundle = jobBundle();

    Page::query()->whereKey($page->id)->delete();

    $transfers = resolve(TransferService::class);
    $transfers->markPending('job.zip');

    runImportJob($bundle);

    $state = $transfers->state();

    expect($state['status'])->toBe('finished')
        ->and($state['bundle'])->toBe('job.zip')
        ->and($state['output'])->toContain('pages')
        ->and($state['output'])->toContain('media files')
        ->and(Page::query()->find($page->id)?->title)->toBe('From The Job');
});

it('does nothing unless an import is pending', function (): void {
    $page = Page::factory()->create(['title' => 'Untouched', 'status' => ContentStatus::PUBLISHED]);
    $bundle = jobBundle();

    Page::query()->whereKey($page->id)->delete();

    runImportJob($bundle);

    expect(resolve(TransferService::class)->state()['status'])->toBe('idle')
        ->and(Page::query()->find($page->id))->toBeNull();
});

it('records the problems and leaves the site alone when a bundle is rejected', function (): void {
    $page = Page::factory()->create(['title' => 'Survives', 'status' => ContentStatus::PUBLISHED]);

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
        'migrations' => SiteBundle::current()->appliedMigrations() + 2,
    ]));
    $zip->addFromString(SiteBundle::SITE_ENTRY, (string) json_encode($site));
    $zip->close();

    $transfers = resolve(TransferService::class);
    $transfers->markPending('newer.zip');

    runImportJob($path);

    expect($transfers->state()['status'])->toBe('failed')
        ->and((string) $transfers->state()['output'])->toContain('newer install')
        ->and(Page::query()->find($page->id)?->title)->toBe('Survives');
});

it('deletes a staged bundle after importing it', function (): void {
    $bundle = jobBundle('staged.zip');

    resolve(TransferService::class)->markPending('staged.zip');

    runImportJob($bundle, null, deleteBundle: true);

    expect(File::exists($bundle))->toBeFalse();
});

it('deletes a staged bundle after rejecting it', function (): void {
    $path = config()->string('wireup.transfer_path').'/bad.zip';
    File::ensureDirectoryExists(dirname($path));

    $site = [];
    foreach (SiteBundle::TABLES as $table) {
        $site[$table] = [];
    }

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString(SiteBundle::MANIFEST_ENTRY, (string) json_encode(['format' => 99]));
    $zip->addFromString(SiteBundle::SITE_ENTRY, (string) json_encode($site));
    $zip->close();

    resolve(TransferService::class)->markPending('bad.zip');

    runImportJob($path, null, deleteBundle: true);

    expect(File::exists($path))->toBeFalse()
        ->and(resolve(TransferService::class)->state()['status'])->toBe('failed');
});

it('keeps a bundle it was not asked to delete', function (): void {
    $bundle = jobBundle('kept.zip');

    resolve(TransferService::class)->markPending('kept.zip');

    runImportJob($bundle);

    expect(File::exists($bundle))->toBeTrue();
});

it('records the failure when the job throws', function (): void {
    $bundle = jobBundle('boom.zip');

    resolve(TransferService::class)->markPending('boom.zip');

    new RunSiteImport($bundle, null, deleteBundle: true)->failed(new RuntimeException('Queue exploded'));

    expect(resolve(TransferService::class)->state())->toMatchArray(['status' => 'failed', 'output' => 'Queue exploded'])
        ->and(File::exists($bundle))->toBeFalse();
});

it('records an unknown failure when no exception is given', function (): void {
    resolve(TransferService::class)->markPending('gone.zip');

    new RunSiteImport('/tmp/gone.zip', null)->failed(null);

    expect(resolve(TransferService::class)->state())->toMatchArray(['status' => 'failed', 'bundle' => 'gone.zip', 'output' => '']);
});

it('runs alone, once, with a long timeout', function (): void {
    $job = new RunSiteImport('/tmp/a.zip', null);

    expect($job->uniqueId())->toBe('wireup-import');

    $reflection = new ReflectionClass(RunSiteImport::class);

    expect($reflection->getAttributes(Timeout::class))->toHaveCount(1)
        ->and($reflection->getAttributes(Tries::class))->toHaveCount(1)
        ->and($reflection->getAttributes(Timeout::class)[0]->newInstance()->timeout)->toBe(3600);
});
