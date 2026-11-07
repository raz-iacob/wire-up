<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Jobs\CopyBackupOffsite;
use App\Jobs\RunBackup;
use App\Models\Activity;
use App\Models\Role;
use App\Models\Settings;
use App\Models\User;
use App\Services\BackupService;
use App\Services\DatabaseDumper;
use App\Services\OffsiteBackupService;
use App\Services\SettingsService;
use App\Services\SiteBundle;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\FakeOffsiteStorage;

beforeEach(function (): void {
    config()->set('wireup.backup_path', storage_path('framework/testing/backup-screen-'.Str::random(8)));
    File::ensureDirectoryExists(config()->string('wireup.backup_path'));
});

afterEach(function (): void {
    File::deleteDirectory(config()->string('wireup.backup_path'));
});

function backupFile(string $name, string $contents = 'zip'): string
{
    $path = config()->string('wireup.backup_path').'/'.$name;
    File::put($path, $contents);

    return $path;
}

function backupsViewer(): User
{
    $role = Role::query()->firstOrCreate(['key' => 'backup-viewer'], ['name' => 'Viewer', 'abilities' => ['settings.view'], 'bypass' => false, 'is_protected' => false]);

    return User::factory()->role($role)->create(['active' => true]);
}

it('lists the backups with their type and size, linked from the settings sidebar', function (): void {
    backupFile('backup-2026-10-28-030000-automatic.zip', str_repeat('a', 2048));
    backupFile('backup-2026-10-29-101500-manual.zip');
    backupFile('db-backup-2026-10-27-120000.sql');

    $this->actingAsAdmin()
        ->get(route('admin.settings-backups'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.settings-backups')
        ->assertSee(route('admin.settings-backups'))
        ->assertSeeInOrder(['Oct 29, 2026 10:15', 'Manual', 'Oct 28, 2026 03:00', 'Automatic', 'Oct 27, 2026 12:00', 'Before update'])
        ->assertSee(route('admin.backups-download', ['file' => 'backup-2026-10-28-030000-automatic.zip']))
        ->assertSee('Backups use');
});

it('says when there are no backups yet and keeps automatic backups off', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-backups')
        ->assertSet('enabled', false)
        ->assertSee('No backups yet.')
        ->assertDontSee('How often');
});

it('saves the automatic backup settings', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-backups')
        ->set('enabled', true)
        ->assertSee('How often')
        ->set('frequency', 'weekly')
        ->set('keep', 3)
        ->set('includeFiles', false)
        ->call('update')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show');

    expect(Settings::get('backups_enabled'))->toBeTrue()
        ->and(Settings::get('backup_frequency'))->toBe('weekly')
        ->and(Settings::get('backups_to_keep'))->toBe(3)
        ->and(Settings::get('backup_include_files'))->toBeFalse();
});

it('rejects a schedule or count it does not know', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-backups')
        ->set('frequency', 'hourly')
        ->set('keep', 0)
        ->call('update')
        ->assertHasErrors(['frequency', 'keep']);
});

it('queues a backup on request and refuses a second one while it runs', function (): void {
    Queue::fake();
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-backups')
        ->call('backUpNow')
        ->assertSee('Waiting for the scheduler to start the backup')
        ->call('backUpNow')
        ->assertDispatched('toast-show');

    Queue::assertPushedOn('system', RunBackup::class, fn (RunBackup $job): bool => $job->userId === auth()->id());
    expect(resolve(BackupService::class)->state()['status'])->toBe('pending');
});

it('shows the finished, failed and stalled states and lets them be dismissed', function (): void {
    $this->actingAsAdmin();
    $backups = resolve(BackupService::class);

    $backups->markFinished('backup-2026-10-29-101500-manual.zip');
    $component = Livewire::test('pages::admin.settings-backups')
        ->assertSee('Backup ready')
        ->assertSee('backup-2026-10-29-101500-manual.zip');

    $backups->markFailed('disk full');
    $component->call('refreshState')->assertSee('The backup failed')->assertSee('disk full');

    $backups->markRunning();
    $this->travel(2)->hours();
    $component->call('refreshState')->assertSee('stopped responding');

    $component->call('dismissState')->assertDontSee('stopped responding');
    expect($backups->state()['status'])->toBe('idle');
});

it('deletes a backup after confirmation but never a pre-update dump', function (): void {
    $this->actingAsAdmin();
    $archive = backupFile('backup-2026-10-29-101500-manual.zip');
    $dump = backupFile('db-backup-2026-10-27-120000.sql');

    Livewire::test('pages::admin.settings-backups')
        ->call('confirmDelete', 'backup-2026-10-29-101500-manual.zip')
        ->assertSet('deleteFile', 'backup-2026-10-29-101500-manual.zip')
        ->call('delete')
        ->assertSet('deleteFile', null)
        ->call('confirmDelete', 'db-backup-2026-10-27-120000.sql')
        ->call('delete')
        ->assertDispatched('toast-show');

    expect(File::exists($archive))->toBeFalse()
        ->and(File::exists($dump))->toBeTrue()
        ->and(Activity::query()->sole()->only(['event', 'subject_label']))->toBe(['event' => ActivityEvent::BACKUP_DELETED, 'subject_label' => 'backup-2026-10-29-101500-manual.zip']);
});

it('shows the screen to people who may only look, without the buttons', function (): void {
    backupFile('backup-2026-10-29-101500-manual.zip');

    $this->actingAs(backupsViewer())
        ->get(route('admin.settings-backups'))
        ->assertOk()
        ->assertDontSee('Back up now')
        ->assertDontSee(route('admin.backups-download', ['file' => 'backup-2026-10-29-101500-manual.zip']));

    Livewire::actingAs(backupsViewer())
        ->test('pages::admin.settings-backups')
        ->call('backUpNow')
        ->assertForbidden();
});

it('downloads a backup only for people who may change settings, and only real backup files', function (): void {
    backupFile('backup-2026-10-29-101500-manual.zip', 'archive-bytes');
    backupFile('notes.txt', 'secret');

    $this->actingAsAdmin()
        ->get(route('admin.backups-download', ['file' => 'backup-2026-10-29-101500-manual.zip']))
        ->assertOk()
        ->assertDownload('backup-2026-10-29-101500-manual.zip');

    $this->get(route('admin.backups-download', ['file' => 'notes.txt']))->assertNotFound();
    $this->get(route('admin.backups-download', ['file' => 'backup-2026-10-30-000000-manual.zip']))->assertNotFound();

    $this->actingAs(backupsViewer())
        ->get(route('admin.backups-download', ['file' => 'backup-2026-10-29-101500-manual.zip']))
        ->assertForbidden();
});

it('runs a queued backup and records the result', function (): void {
    $backups = new BackupService(new DatabaseDumper, new SiteBundle, fn (string $directory): float => 1.0e12);
    app()->instance(BackupService::class, $backups);
    config()->set('database.default', 'mysql');
    Process::fake();

    $backups->markPending();
    new RunBackup()->failed(new RuntimeException('worker died'));
    expect($backups->state())->toMatchArray(['status' => 'failed', 'output' => 'worker died']);

    $backups->clearState();
    new RunBackup()->handle($backups, resolve(OffsiteBackupService::class));
    expect($backups->state()['status'])->toBe('idle');

    $backups->markPending();
    expect(fn () => new RunBackup()->handle($backups, resolve(OffsiteBackupService::class)))->toThrow(RuntimeException::class, 'The database dump came out empty')
        ->and($backups->state()['status'])->toBe('running')
        ->and(new RunBackup()->uniqueId())->toBe('wireup-backup');
});

it('points to off-site backups until they are connected', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-backups')
        ->assertSee('connect off-site backups')
        ->assertSee(route('admin.settings-integrations'))
        ->assertDontSee('Off-site copies to keep');
});

it('shows which backups are also off-site and lists copies that are only off-site', function (): void {
    $disk = FakeOffsiteStorage::connect();
    $disk->put('example.com/backup-2026-10-29-101500-manual.zip', 'zip');
    $disk->put('example.com/backup-2026-10-20-030000-automatic.zip', 'older zip');
    backupFile('backup-2026-10-29-101500-manual.zip');
    backupFile('backup-2026-10-30-030000-automatic.zip');

    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-backups')
        ->assertSee('Every backup is also copied to site-backups/example.com.')
        ->assertSee('Also copied off-site')
        ->assertSeeInOrder(['Oct 30, 2026 03:00', 'Oct 29, 2026 10:15', 'Oct 20, 2026 03:00', 'Off-site only'])
        ->assertSee(route('admin.backups-offsite-download', ['file' => 'backup-2026-10-20-030000-automatic.zip']))
        ->assertDontSee(route('admin.backups-download', ['file' => 'backup-2026-10-20-030000-automatic.zip']))
        ->assertSee('wireup:backup:fetch');
});

it('saves how many off-site copies to keep', function (): void {
    FakeOffsiteStorage::connect();
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-backups')
        ->assertSet('offsiteKeep', 14)
        ->set('offsiteKeep', 101)
        ->call('update')
        ->assertHasErrors('offsiteKeep')
        ->set('offsiteKeep', 30)
        ->call('update')
        ->assertHasNoErrors();

    expect(Settings::get('offsite_backups_to_keep'))->toBe(30)
        ->and(resolve(SettingsService::class)->offsiteBackupsToKeep())->toBe(30);
});

it('says when off-site storage cannot be reached', function (): void {
    FakeOffsiteStorage::connectRefusing();
    backupFile('backup-2026-10-29-101500-manual.zip');
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-backups')
        ->assertSee('Off-site storage could not be reached')
        ->assertSee('Oct 29, 2026 10:15')
        ->assertDontSee('Also copied off-site');
});

it('shows a failed off-site copy and copies it again on request', function (): void {
    Queue::fake();
    FakeOffsiteStorage::connect();
    resolve(OffsiteBackupService::class)->markFailed('backup-2026-10-29-101500-manual.zip', 'AccessDenied: Access Denied');
    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.settings-backups')
        ->assertSee('Copying backup-2026-10-29-101500-manual.zip off-site failed')
        ->assertSee('AccessDenied: Access Denied')
        ->call('retryOffsite')
        ->assertSee('Copying backup-2026-10-29-101500-manual.zip off-site…');

    Queue::assertPushedOn('system', CopyBackupOffsite::class, fn (CopyBackupOffsite $job): bool => $job->file === 'backup-2026-10-29-101500-manual.zip');

    resolve(OffsiteBackupService::class)->markFailed('backup-2026-10-29-101500-manual.zip', 'Still denied');
    $component->call('refreshState')->call('dismissOffsite')->assertDontSee('Still denied');

    expect(resolve(OffsiteBackupService::class)->state())->toBeNull();

    $component->call('retryOffsite');

    Queue::assertPushed(CopyBackupOffsite::class, 1);
});
