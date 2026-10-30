<?php

declare(strict_types=1);

use App\Enums\ActivityEvent;
use App\Jobs\RunBackup;
use App\Models\Activity;
use App\Models\Role;
use App\Models\Settings;
use App\Models\User;
use App\Services\BackupService;
use App\Services\DatabaseDumper;
use App\Services\SiteBundle;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

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
        ->assertSee('Waiting for the queue worker')
        ->call('backUpNow')
        ->assertDispatched('toast-show');

    Queue::assertPushed(RunBackup::class, fn (RunBackup $job): bool => $job->userId === auth()->id());
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
    new RunBackup()->handle($backups);
    expect($backups->state()['status'])->toBe('idle');

    $backups->markPending();
    expect(fn () => new RunBackup()->handle($backups))->toThrow(RuntimeException::class, 'The database dump came out empty')
        ->and($backups->state()['status'])->toBe('running')
        ->and(new RunBackup()->uniqueId())->toBe('wireup-backup');
});
