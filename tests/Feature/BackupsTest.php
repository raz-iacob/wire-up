<?php

declare(strict_types=1);

use App\Console\Commands\CreateBackupCommand;
use App\Console\Commands\RestoreBackupCommand;
use App\Enums\ActivityEvent;
use App\Jobs\RunBackup;
use App\Models\Activity;
use App\Notifications\BackupFailed;
use App\Services\BackupService;
use App\Services\DatabaseDumper;
use App\Services\SiteBundle;
use App\Services\SlackWebhookChannel;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\RequestException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function backupSourceDatabase(): string
{
    $database = storage_path('framework/testing/backup-source-'.Str::random(8).'.sqlite');
    File::ensureDirectoryExists(dirname($database));
    $migrations = DB::table('migrations')->orderBy('id')->get();

    $pdo = new PDO('sqlite:'.$database);
    $pdo->exec('create table migrations (id integer primary key autoincrement, migration varchar not null, batch integer not null)');
    $insert = $pdo->prepare('insert into migrations (migration, batch) values (?, ?)');

    foreach ($migrations as $migration) {
        $insert->execute([$migration->migration, $migration->batch]);
    }

    $pdo->exec('create table samples (name text)');
    $pdo->exec("insert into samples values ('original')");

    config()->set('database.connections.sqlite_backup', ['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => false]);
    config()->set('database.default', 'sqlite_backup');

    (require database_path('migrations/'.basename((string) collect(glob(database_path('migrations/*_create_activities_table.php')))->first())))->up();

    return $database;
}

/**
 * @param  array<string, mixed>  $manifest
 * @param  array<string, string>  $entries
 */
function handmadeBackup(string $name, array $manifest, array $entries = []): string
{
    $path = config()->string('wireup.backup_path').'/'.$name;
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);

    if ($manifest !== []) {
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    foreach ($entries as $entry => $contents) {
        $zip->addFromString($entry, $contents);
    }

    $zip->close();

    return $path;
}

/**
 * @return array<int, string>
 */
function archiveEntries(string $path): array
{
    $zip = new ZipArchive;
    $zip->open($path);
    $entries = [];

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entries[] = (string) $zip->getNameIndex($index);
    }

    $zip->close();
    sort($entries);

    return $entries;
}

beforeEach(function (): void {
    $this->defaultConnection = config()->string('database.default');
    config()->set('wireup.backup_path', storage_path('framework/testing/backups-'.Str::random(8)));
    config()->set('app.maintenance', ['driver' => 'cache', 'store' => 'array']);
});

afterEach(function (): void {
    config()->set('database.default', $this->defaultConnection);
    File::deleteDirectory(config()->string('wireup.backup_path'));
    File::delete(File::glob(storage_path('framework/testing/backup-source-*.sqlite')));
});

it('backs up the database and uploaded files into one archive', function (): void {
    Storage::fake(config('filesystems.media'));
    Storage::disk(config('filesystems.media'))->put('media/photo.jpg', 'photo');
    Storage::disk(config('filesystems.media'))->put('users/avatar.png', 'avatar');
    backupSourceDatabase();

    $archive = resolve(BackupService::class)->create(automatic: false);

    expect(basename($archive))->toMatch('/^backup-\d{4}-\d{2}-\d{2}-\d{6}-manual\.zip$/')
        ->and(archiveEntries($archive))->toBe(['database.sqlite', 'files/media/photo.jpg', 'files/users/avatar.png', 'manifest.json'])
        ->and(File::glob(config()->string('wireup.backup_path').'/.backup-*'))->toBeEmpty();

    $zip = new ZipArchive;
    $zip->open($archive);
    $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
    $copy = config()->string('wireup.backup_path').'/copy.sqlite';
    File::put($copy, (string) $zip->getFromName('database.sqlite'));
    $zip->close();

    expect($manifest)->toMatchArray(['format' => 1, 'database' => 'sqlite', 'kind' => 'manual', 'files' => ['count' => 2, 'bytes' => 11]])
        ->and(new PDO('sqlite:'.$copy)->query('select name from samples')->fetchColumn())->toBe('original');
});

it('leaves the uploaded files out when that is switched off or they are not on a local disk', function (): void {
    Storage::fake(config('filesystems.media'));
    Storage::disk(config('filesystems.media'))->put('media/photo.jpg', 'photo');
    backupSourceDatabase();
    $backups = resolve(BackupService::class);

    config()->set('site.backup_include_files', false);
    expect(archiveEntries($backups->create(automatic: false)))->toBe(['database.sqlite', 'manifest.json']);

    $this->travel(1)->seconds();
    config()->set('site.backup_include_files', true);
    config()->set('filesystems.media', 's3');

    expect(archiveEntries($backups->create(automatic: false)))->toBe(['database.sqlite', 'manifest.json'])
        ->and($backups->uploadsRoot())->toBeNull();
});

it('backs up private documents and restores them to the private disk', function (): void {
    Storage::fake(config('filesystems.media'));
    Storage::fake(config('filesystems.private_media'));
    $private = Storage::disk(config('filesystems.private_media'));
    $private->put('media/contract.pdf', 'signed');
    $private->put('livewire-tmp/upload.tmp', 'scratch');
    backupSourceDatabase();

    $archive = resolve(BackupService::class)->create(automatic: false);

    expect(archiveEntries($archive))->toBe(['database.sqlite', 'manifest.json', 'private-files/media/contract.pdf']);

    $private->put('media/contract.pdf', 'changed');

    $this->artisan(RestoreBackupCommand::class, ['file' => basename($archive), '--force' => true])
        ->assertExitCode(0);

    expect($private->get('media/contract.pdf'))->toBe('signed');
});

it('leaves private documents out when the private disk is not local', function (): void {
    config()->set('filesystems.private_media', 's3');

    expect(resolve(BackupService::class)->privateUploadsRoot())->toBeNull();
});

it('keeps only the newest automatic backups and leaves manual and pre-update ones alone', function (): void {
    backupSourceDatabase();
    config()->set('site.backups_to_keep', 2);
    $directory = config()->string('wireup.backup_path');
    File::ensureDirectoryExists($directory);

    foreach (['backup-2020-01-01-000000-automatic.zip', 'backup-2020-01-02-000000-automatic.zip', 'backup-2020-01-03-000000-automatic.zip', 'backup-2020-01-01-000000-manual.zip', 'db-backup-2020-01-01-000000.sql'] as $file) {
        File::put("{$directory}/{$file}", 'x');
    }

    $archive = resolve(BackupService::class)->create(automatic: true);

    expect(array_column(resolve(BackupService::class)->all(), 'file'))->toBe([
        basename($archive),
        'backup-2020-01-03-000000-automatic.zip',
        'db-backup-2020-01-01-000000.sql',
        'backup-2020-01-01-000000-manual.zip',
    ]);
});

it('refuses to start when the disk has no room for another backup', function (): void {
    backupSourceDatabase();
    File::ensureDirectoryExists(config()->string('wireup.backup_path'));
    File::put(config()->string('wireup.backup_path').'/backup-2020-01-01-000000-manual.zip', str_repeat('x', 1000));
    app()->instance(BackupService::class, new BackupService(new DatabaseDumper, new SiteBundle, fn (string $directory): float => 100.0));

    expect(fn (): string => resolve(BackupService::class)->create(automatic: false))
        ->toThrow(RuntimeException::class, 'There is not enough free disk space for another backup')
        ->and(resolve(BackupService::class)->all())->toHaveCount(1);
});

it('leaves no half-made archive behind when the database cannot be dumped', function (): void {
    config()->set('database.default', 'mysql');
    Process::fake();

    expect(fn (): string => resolve(BackupService::class)->create(automatic: false))
        ->toThrow(RuntimeException::class, 'The database dump came out empty')
        ->and(File::files(config()->string('wireup.backup_path')))->toBeEmpty();
});

it('stops with what the dump tool says when the dump fails', function (): void {
    config()->set('database.default', 'mysql');
    Process::fake(['*mysqldump*' => Process::result(errorOutput: 'access denied', exitCode: 1)]);

    expect(fn (): string => resolve(BackupService::class)->create(automatic: false))
        ->toThrow(RuntimeException::class, 'access denied')
        ->and(File::files(config()->string('wireup.backup_path')))->toBeEmpty();
});

it('fails clearly when the archive cannot be created', function (): void {
    backupSourceDatabase();
    $this->freezeTime();
    File::ensureDirectoryExists(config()->string('wireup.backup_path').'/backup-'.now()->format('Y-m-d-His').'-manual.zip');

    expect(fn (): string => resolve(BackupService::class)->create(automatic: false))
        ->toThrow(RuntimeException::class, 'The backup file could not be created');
});

it('lists backups newest first and only hands out real backup files', function (): void {
    $backups = resolve(BackupService::class);
    expect($backups->all())->toBeEmpty()->and($backups->lastAutomatic())->toBeNull();

    $directory = config()->string('wireup.backup_path');
    File::ensureDirectoryExists($directory);
    File::put("{$directory}/backup-2026-01-02-030000-automatic.zip", 'aa');
    File::put("{$directory}/backup-2026-01-03-120000-manual.zip", 'bbb');
    File::put("{$directory}/db-backup-2026-01-01-000000.sqlite", 'c');
    File::put("{$directory}/notes.txt", 'ignored');

    expect($backups->all())->toMatchArray([
        ['file' => 'backup-2026-01-03-120000-manual.zip', 'kind' => 'manual', 'bytes' => 3, 'created_at' => CarbonImmutable::parse('2026-01-03 12:00:00')],
        ['file' => 'backup-2026-01-02-030000-automatic.zip', 'kind' => 'automatic', 'bytes' => 2, 'created_at' => CarbonImmutable::parse('2026-01-02 03:00:00')],
        ['file' => 'db-backup-2026-01-01-000000.sqlite', 'kind' => 'update', 'bytes' => 1, 'created_at' => CarbonImmutable::parse('2026-01-01 00:00:00')],
    ])
        ->and($backups->totalBytes())->toBe(6)
        ->and($backups->lastAutomatic()?->toDateTimeString())->toBe('2026-01-02 03:00:00')
        ->and($backups->find('notes.txt'))->toBeNull()
        ->and($backups->find('../backup-2026-01-02-030000-automatic.zip'))->toBeNull()
        ->and($backups->find('backup-2026-01-02-030000-automatic.zip'))->toBe("{$directory}/backup-2026-01-02-030000-automatic.zip")
        ->and($backups->delete('db-backup-2026-01-01-000000.sqlite'))->toBeFalse()
        ->and($backups->delete('backup-2026-01-03-120000-manual.zip'))->toBeTrue()
        ->and($backups->find('backup-2026-01-03-120000-manual.zip'))->toBeNull()
        ->and($backups->freeBytes())->toBeInt()
        ->and(new BackupService(new DatabaseDumper, new SiteBundle, fn (string $path): false => false)->freeBytes())->toBeNull();
});

it('deletes a half-written dump when the dump tool fails, but never a file that was already there', function (): void {
    config()->set('database.default', 'mysql');
    $directory = config()->string('wireup.backup_path');
    File::ensureDirectoryExists($directory);
    Process::fake(function (PendingProcess $process) use ($directory) {
        File::put("{$directory}/partial.sql", 'half');

        return Process::result(errorOutput: 'connection lost', exitCode: 1);
    });

    expect(new DatabaseDumper()->dump("{$directory}/partial.sql"))->toBe('connection lost')
        ->and(File::exists("{$directory}/partial.sql"))->toBeFalse();

    File::put("{$directory}/kept.sql", 'mine');

    expect(new DatabaseDumper()->dump("{$directory}/kept.sql"))->toBe('connection lost')
        ->and(File::exists("{$directory}/kept.sql"))->toBeTrue();
});

it('loads a dump back into each kind of database', function (): void {
    $source = backupSourceDatabase();
    $dump = config()->string('wireup.backup_path').'/restore.sqlite';
    File::ensureDirectoryExists(dirname($dump));
    File::copy($source, $dump);
    DB::connection('sqlite_backup')->table('samples')->update(['name' => 'changed']);

    expect(new DatabaseDumper()->load($dump))->toBeNull()
        ->and(DB::connection('sqlite_backup')->table('samples')->value('name'))->toBe('original');

    config()->set('database.connections.sqlite_backup.database', ':memory:');
    expect(new DatabaseDumper()->load($dump))->toContain('in-memory');

    Process::fake();
    config()->set('database.default', 'mysql');
    config()->set('database.connections.mysql.password', 'secret');
    expect(new DatabaseDumper()->load($dump))->toBeNull();
    Process::assertRan(fn (PendingProcess $process): bool => $process->command[0] === 'mysql' && $process->environment === ['MYSQL_PWD' => 'secret']);

    config()->set('database.default', 'pgsql');
    expect(new DatabaseDumper()->load($dump))->toBeNull();
    Process::assertRan(fn (PendingProcess $process): bool => $process->command[0] === 'psql' && in_array('--file='.$dump, $process->command, true));

    config()->set('database.default', 'sqlsrv');
    config()->set('database.connections.sqlsrv.driver', 'sqlsrv');
    expect(new DatabaseDumper()->load($dump))->toContain('Restore the database manually');
});

it('reports what the database tool says when loading fails', function (string $driver, string $tool): void {
    config()->set('database.default', $driver);
    Process::fake(["*{$tool}*" => Process::result(errorOutput: 'syntax error', exitCode: 1)]);
    $dump = config()->string('wireup.backup_path').'/restore.sql';
    File::ensureDirectoryExists(dirname($dump));
    File::put($dump, 'select 1;');

    expect(new DatabaseDumper()->load($dump))->toBe('syntax error');
})->with([['mysql', 'mysql'], ['pgsql', 'psql']]);

it('does nothing on schedule while automatic backups are off', function (): void {
    backupSourceDatabase();

    $this->artisan(CreateBackupCommand::class, ['--scheduled' => true])->assertExitCode(0);

    expect(resolve(BackupService::class)->all())->toBeEmpty();
});

it('makes automatic backups on schedule once switched on, at most weekly when asked', function (): void {
    backupSourceDatabase();
    config()->set('site.backups_enabled', true);

    $this->artisan(CreateBackupCommand::class, ['--scheduled' => true])->assertExitCode(0);
    expect(resolve(BackupService::class)->all())->toHaveCount(1);

    config()->set('site.backup_frequency', 'weekly');
    $this->travel(1)->day();
    $this->artisan(CreateBackupCommand::class, ['--scheduled' => true])->assertExitCode(0);
    expect(resolve(BackupService::class)->all())->toHaveCount(1);

    $this->travel(6)->days();
    $this->artisan(CreateBackupCommand::class, ['--scheduled' => true])->assertExitCode(0);
    expect(array_column(resolve(BackupService::class)->all(), 'kind'))->toBe(['automatic', 'automatic']);
});

it('makes a manual backup from the command line', function (): void {
    backupSourceDatabase();

    $this->artisan(CreateBackupCommand::class)
        ->expectsOutputToContain('Backed up to')
        ->assertExitCode(0);

    expect(array_column(resolve(BackupService::class)->all(), 'kind'))->toBe(['manual'])
        ->and(Activity::query()->sole()->only(['event', 'subject_label']))->toBe(['event' => ActivityEvent::BACKUP_CREATED, 'subject_label' => resolve(BackupService::class)->all()[0]['file']]);
});

it('tells the owner by email and Slack when an automatic backup fails', function (): void {
    Notification::fake();
    config()->set('site.backups_enabled', true);
    config()->set('site.contact_email', 'owner@example.com');
    config()->set('services.slack.webhook_url', 'https://hooks.slack.com/services/T/B/x');
    config()->set('database.default', 'sqlsrv');
    config()->set('database.connections.sqlsrv.driver', 'sqlsrv');

    $this->artisan(CreateBackupCommand::class, ['--scheduled' => true])
        ->expectsOutputToContain('Back up the database manually')
        ->assertExitCode(1);

    Notification::assertSentOnDemand(BackupFailed::class, fn (BackupFailed $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes === ['mail' => 'owner@example.com', SlackWebhookChannel::class => 'https://hooks.slack.com/services/T/B/x']
        && str_contains($notification->reason, 'sqlsrv'));
});

it('sends no alert for a failed manual backup or when nobody can be told', function (): void {
    Notification::fake();
    config()->set('site.backups_enabled', true);
    config()->set('site.contact_email', 'owner@example.com');
    config()->set('database.default', 'sqlsrv');
    config()->set('database.connections.sqlsrv.driver', 'sqlsrv');

    $this->artisan(CreateBackupCommand::class)->assertExitCode(1);

    config()->set('site.contact_email', '');
    $this->artisan(CreateBackupCommand::class, ['--scheduled' => true])->assertExitCode(1);

    Notification::assertNothingSent();
});

it('logs a backup that fails from the command line', function (): void {
    backupSourceDatabase();
    File::ensureDirectoryExists(config()->string('wireup.backup_path'));
    File::put(config()->string('wireup.backup_path').'/backup-2020-01-01-000000-manual.zip', str_repeat('x', 1000));
    app()->instance(BackupService::class, new BackupService(new DatabaseDumper, new SiteBundle, fn (string $directory): float => 100.0));

    $this->artisan(CreateBackupCommand::class)->assertExitCode(1);

    $activity = Activity::query()->sole();

    expect($activity->event)->toBe(ActivityEvent::BACKUP_FAILED)
        ->and($activity->properties['reason'])->toContain('There is not enough free disk space for another backup');
});

it('reports an alert that cannot be delivered instead of failing again', function (): void {
    Exceptions::fake();
    Http::fake(['hooks.slack.com/*' => Http::response('no', 500)]);
    config()->set('site.backups_enabled', true);
    config()->set('site.contact_email', '');
    config()->set('services.slack.webhook_url', 'https://hooks.slack.com/services/T/B/x');
    config()->set('database.default', 'sqlsrv');
    config()->set('database.connections.sqlsrv.driver', 'sqlsrv');

    $this->artisan(CreateBackupCommand::class, ['--scheduled' => true])->assertExitCode(1);

    Exceptions::assertReported(RequestException::class);
});

it('schedules the automatic backup for three in the morning', function (): void {
    $this->artisan('schedule:list')->assertExitCode(0);

    $event = collect(resolve(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_ends_with((string) $event->command, 'wireup:backup:create --scheduled'));

    expect($event?->expression)->toBe('0 3 * * *');
});

it('restores the database and uploaded files from a backup', function (): void {
    Storage::fake(config('filesystems.media'));
    $disk = Storage::disk(config('filesystems.media'));
    $disk->put('media/photo.jpg', 'before');
    backupSourceDatabase();
    $archive = resolve(BackupService::class)->create(automatic: false);

    $zip = new ZipArchive;
    $zip->open($archive);
    $zip->addFromString('files/../escape.txt', 'nope');
    $zip->addEmptyDir('files/empty');
    $zip->close();

    DB::connection('sqlite_backup')->table('samples')->update(['name' => 'changed']);
    $disk->put('media/photo.jpg', 'after');

    $this->artisan(RestoreBackupCommand::class, ['file' => basename($archive), '--force' => true])
        ->expectsOutputToContain('the database and 1 uploaded files')
        ->assertExitCode(0);

    expect(DB::connection('sqlite_backup')->table('samples')->value('name'))->toBe('original')
        ->and($disk->get('media/photo.jpg'))->toBe('before')
        ->and(File::exists(dirname(mb_rtrim($disk->path(''), '/')).'/escape.txt'))->toBeFalse()
        ->and(app()->isDownForMaintenance())->toBeFalse();
});

it('asks before replacing anything', function (): void {
    backupSourceDatabase();
    $archive = resolve(BackupService::class)->create(automatic: false);

    $this->artisan(RestoreBackupCommand::class, ['file' => basename($archive)])
        ->expectsConfirmation('Replace this site\'s database and uploaded files with '.basename($archive).'?', 'no')
        ->expectsOutputToContain('Nothing was restored.')
        ->assertExitCode(0);
});

it('refuses a file that is missing, not an archive or not a Wire-Up backup', function (): void {
    File::ensureDirectoryExists(config()->string('wireup.backup_path'));
    File::put(config()->string('wireup.backup_path').'/db-backup-2026-01-01-000000.sql', 'x');
    handmadeBackup('backup-2026-01-01-000000-manual.zip', [], ['readme.txt' => 'not a backup']);

    $this->artisan(RestoreBackupCommand::class, ['file' => 'backup-2026-01-02-000000-manual.zip', '--force' => true])
        ->expectsOutputToContain('There is no backup called')
        ->assertExitCode(1);

    $this->artisan(RestoreBackupCommand::class, ['file' => 'db-backup-2026-01-01-000000.sql', '--force' => true])
        ->expectsOutputToContain('There is no backup called')
        ->assertExitCode(1);

    $this->artisan(RestoreBackupCommand::class, ['file' => 'backup-2026-01-01-000000-manual.zip', '--force' => true])
        ->expectsOutputToContain('is not a Wire-Up backup')
        ->assertExitCode(1);
});

it('refuses a backup from another database, a newer version or without a database', function (array $manifest, array $entries, string $message): void {
    File::ensureDirectoryExists(config()->string('wireup.backup_path'));
    handmadeBackup('backup-2026-01-01-000000-manual.zip', $manifest, $entries);

    $this->artisan(RestoreBackupCommand::class, ['file' => 'backup-2026-01-01-000000-manual.zip', '--force' => true])
        ->expectsOutputToContain($message)
        ->assertExitCode(1);
})->with([
    'another database' => [['database' => 'mysql'], ['database.sql' => ''], 'holds a mysql database'],
    'an unknown database' => [['database' => null], [], 'holds a different database'],
    'a newer version' => [['database' => 'sqlite', 'migrations' => 99999], ['database.sqlite' => ''], 'comes from a newer version'],
    'no database' => [['database' => 'sqlite', 'migrations' => 1], [], 'has no database in it'],
]);

it('keeps the site in maintenance mode when the database cannot be loaded', function (): void {
    config()->set('database.default', 'mysql');
    Process::fake(['*mysql*' => Process::result(errorOutput: 'access denied', exitCode: 1)]);
    File::ensureDirectoryExists(config()->string('wireup.backup_path'));
    handmadeBackup('backup-2026-01-01-000000-manual.zip', ['database' => 'mysql'], ['database.sql' => 'select 1;']);

    $this->artisan(RestoreBackupCommand::class, ['file' => 'backup-2026-01-01-000000-manual.zip', '--force' => true])
        ->expectsOutputToContain('access denied')
        ->expectsOutputToContain('still in maintenance mode')
        ->assertExitCode(1);

    expect(app()->isDownForMaintenance())->toBeTrue();

    $this->artisan('up');
});

it('keeps the site in maintenance mode when the database cannot be unpacked', function (): void {
    backupSourceDatabase();
    $archive = resolve(BackupService::class)->create(automatic: false);
    File::ensureDirectoryExists(config()->string('wireup.backup_path').'/.restore.sqlite');

    $this->artisan(RestoreBackupCommand::class, ['file' => basename($archive), '--force' => true])
        ->expectsOutputToContain('still in maintenance mode')
        ->assertExitCode(1);

    expect(app()->isDownForMaintenance())->toBeTrue();

    $this->artisan('up');
});

it('runs a queued backup through to a finished archive', function (): void {
    backupSourceDatabase();
    $backups = resolve(BackupService::class);
    $backups->markPending();

    new RunBackup(42)->handle($backups);

    expect($backups->state()['status'])->toBe('finished')
        ->and($backups->state()['file'])->toBe($backups->all()[0]['file'])
        ->and($backups->all()[0]['kind'])->toBe('manual')
        ->and(Activity::query()->sole()->only(['event', 'user_id', 'subject_label']))->toBe(['event' => ActivityEvent::BACKUP_CREATED, 'user_id' => 42, 'subject_label' => $backups->all()[0]['file']]);
});
