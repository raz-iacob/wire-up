<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Jobs\RunSiteImport;
use App\Models\Page;
use App\Models\Settings;
use App\Models\User;
use App\Services\SiteBundle;
use App\Services\SiteExporter;
use App\Services\TransferService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('wireup.transfer_path', storage_path('framework/testing/screen-'.Str::random(8)));
    Storage::fake(config()->string('filesystems.media'));
});

afterEach(function (): void {
    File::deleteDirectory(config()->string('wireup.transfer_path'));
});

function screenBundle(string $name = 'screen.zip'): string
{
    $path = config()->string('wireup.transfer_path').'/'.$name;
    SiteExporter::current()->export($path);

    return $path;
}

it('can render the export and import screen', function (): void {
    $this->actingAsAdmin()
        ->get(route('admin.settings-export-import'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.settings-export-import')
        ->assertSee('Export this site')
        ->assertSee('Import a site');
});

it('links the screen from the settings sidebar', function (): void {
    $this->actingAsAdmin()
        ->get(route('admin.settings-updates'))
        ->assertOk()
        ->assertSee('Export / Import')
        ->assertSee(route('admin.settings-export-import'));
});

it('redirects non-admins away', function (): void {
    $member = User::factory()->create(['active' => true, 'role' => 'member']);

    $this->actingAs($member)
        ->fromRoute('home')
        ->get(route('admin.settings-export-import'))
        ->assertRedirectToRoute('home');
});

it('streams a bundle download', function (): void {
    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.settings-export-import')->call('export');

    $response->assertFileDownloaded();

    expect(File::glob(config()->string('wireup.transfer_path').'/site-*.zip'))->toHaveCount(1);
});

it('includes secrets in the download when asked', function (): void {
    Settings::set(['pexels_api_key' => 'secret-key']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-export-import')
        ->set('withSecrets', true)
        ->call('export')
        ->assertFileDownloaded();

    $bundle = File::glob(config()->string('wireup.transfer_path').'/site-*.zip')[0];

    $zip = new ZipArchive;
    $zip->open($bundle);
    $site = (string) $zip->getFromName(SiteBundle::SITE_ENTRY);
    $zip->close();

    expect($site)->toContain('pexels_api_key');
});

it('shows what a bundle at a server path holds', function (): void {
    Page::factory()->create(['status' => ContentStatus::PUBLISHED]);
    $bundle = screenBundle();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-export-import')
        ->set('serverPath', $bundle)
        ->call('inspect')
        ->assertSet('inspection.problems', [])
        ->assertSee('Bundle contents')
        ->assertSee('Replace this site');
});

it('resolves a server path relative to the project root', function (): void {
    $bundle = screenBundle();
    $relative = Str::after($bundle, base_path().'/');

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-export-import')
        ->set('serverPath', $relative)
        ->call('inspect')
        ->assertSet('inspection.problems', []);
});

it('complains when no bundle is given', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-export-import')
        ->call('inspect')
        ->assertSet('inspection', null);
});

it('complains when the server path holds no bundle', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-export-import')
        ->set('serverPath', 'storage/app/transfers/not-here.zip')
        ->call('inspect')
        ->assertSet('inspection', null);
});

it('reports an unreadable bundle instead of failing', function (): void {
    $path = config()->string('wireup.transfer_path').'/rubbish.zip';
    File::ensureDirectoryExists(dirname($path));
    File::put($path, 'not a zip at all');

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-export-import')
        ->set('serverPath', $path)
        ->call('inspect')
        ->assertSet('inspection', null);
});

it('queues the import and marks it pending', function (): void {
    Queue::fake();

    $bundle = screenBundle();
    $admin = User::factory()->create(['active' => true, 'role' => 'admin']);

    Livewire::actingAs($admin)->test('pages::admin.settings-export-import')
        ->set('serverPath', $bundle)
        ->call('inspect')
        ->call('startImport')
        ->assertSet('inspection', null);

    Queue::assertPushed(RunSiteImport::class, fn (RunSiteImport $job): bool => $job->ownerId === $admin->id
        && $job->deleteBundle
        && str_contains($job->path, 'import-'));

    expect(resolve(TransferService::class)->state())->toMatchArray(['status' => 'pending']);
});

it('stages a copy of the bundle so the original is left alone', function (): void {
    Queue::fake();

    $bundle = screenBundle();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-export-import')
        ->set('serverPath', $bundle)
        ->call('startImport');

    expect(File::exists($bundle))->toBeTrue()
        ->and(File::glob(config()->string('wireup.transfer_path').'/import-*.zip'))->toHaveCount(1);
});

it('refuses to start a second import while one is running', function (): void {
    Queue::fake();

    $bundle = screenBundle();
    resolve(TransferService::class)->markRunning('other.zip', 'Replacing site content');

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-export-import')
        ->set('serverPath', $bundle)
        ->call('startImport');

    Queue::assertNothingPushed();
});

it('does not queue anything when the bundle path is unusable', function (): void {
    Queue::fake();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-export-import')
        ->set('serverPath', 'nope.zip')
        ->call('startImport');

    Queue::assertNothingPushed();

    expect(resolve(TransferService::class)->state()['status'])->toBe('idle');
});

it('shows progress while an import runs', function (): void {
    resolve(TransferService::class)->markRunning('site.zip', 'Replacing site content');

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-export-import')
        ->assertSee('Importing site.zip')
        ->assertSee('Replacing site content')
        ->assertDontSee('Check bundle');
});

it('shows the waiting message before the worker picks the import up', function (): void {
    resolve(TransferService::class)->markPending('site.zip');

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-export-import')
        ->assertSee('Waiting for the queue worker');
});

it('shows the outcome of a finished import and dismisses it', function (): void {
    resolve(TransferService::class)->markFinished('site.zip', '4 pages, 12 records');

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-export-import')
        ->assertSee('Imported site.zip')
        ->assertSee('4 pages, 12 records')
        ->call('dismissState')
        ->assertDontSee('Imported site.zip');

    expect(resolve(TransferService::class)->state()['status'])->toBe('idle');
});

it('shows a failed import with its output', function (): void {
    resolve(TransferService::class)->markFailed('site.zip', 'The bundle is missing manifest.json.');

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-export-import')
        ->assertSee('Importing site.zip failed')
        ->assertSee('The bundle is missing manifest.json.');
});

it('warns when an import stops responding', function (): void {
    resolve(TransferService::class)->markPending('site.zip');

    $this->travel(11)->minutes();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-export-import')
        ->assertSee('stopped responding');
});

it('clears a stale inspection when the state is refreshed', function (): void {
    $bundle = screenBundle();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-export-import')
        ->set('serverPath', $bundle)
        ->call('inspect')
        ->assertSet('inspection.problems', [])
        ->call('refreshState')
        ->assertSet('inspection', null)
        ->assertDontSee('Bundle contents');
});

it('caps the upload size to what the server accepts', function (): void {
    $this->actingAsAdmin();

    $limit = Livewire::test('pages::admin.settings-export-import')->instance()->maxUploadKilobytes();

    expect($limit)->toBeGreaterThan(0);
});
