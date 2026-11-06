<?php

declare(strict_types=1);

use App\Console\Commands\RestoreDatabaseBackupCommand;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('wireup.backup_path', storage_path('framework/testing/restore-database-'.Str::random(8)));
    File::ensureDirectoryExists(config()->string('wireup.backup_path'));
});

afterEach(function (): void {
    File::deleteDirectory(config()->string('wireup.backup_path'));
});

function liveSqliteDatabaseWithBackup(): void
{
    $directory = config()->string('wireup.backup_path');
    $database = $directory.'/live.sqlite';
    File::put($database, '');

    config()->set('database.connections.sqlite_live', ['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'journal_mode' => 'wal']);
    config()->set('database.default', 'sqlite_live');

    DB::statement('create table samples (name text)');
    DB::table('samples')->insert(['name' => 'before the update']);
    DB::statement('VACUUM INTO '.DB::getPdo()->quote($directory.'/db-backup-2026-11-06-120000-before-v1.0.0.sqlite'));

    DB::table('samples')->update(['name' => 'half migrated']);
    DB::statement('create table added_by_the_update (id integer)');
}

it('puts a sqlite database back the way it was when the backup was taken', function (): void {
    liveSqliteDatabaseWithBackup();

    $this->artisan(RestoreDatabaseBackupCommand::class, ['file' => 'db-backup-2026-11-06-120000-before-v1.0.0.sqlite', '--force' => true])
        ->expectsOutputToContain('Restored the database from db-backup-2026-11-06-120000-before-v1.0.0.sqlite.')
        ->assertExitCode(0);

    expect(DB::table('samples')->value('name'))->toBe('before the update')
        ->and(DB::getSchemaBuilder()->hasTable('added_by_the_update'))->toBeFalse();
});

it('asks before replacing the database', function (): void {
    liveSqliteDatabaseWithBackup();

    $this->artisan(RestoreDatabaseBackupCommand::class, ['file' => 'db-backup-2026-11-06-120000-before-v1.0.0.sqlite'])
        ->expectsConfirmation("Replace this site's database with db-backup-2026-11-06-120000-before-v1.0.0.sqlite?", 'no')
        ->expectsOutputToContain('Nothing was restored.')
        ->assertExitCode(0);

    expect(DB::table('samples')->value('name'))->toBe('half migrated');
});

it('refuses anything that is not a database backup for this database', function (string $file): void {
    File::put(config()->string('wireup.backup_path').'/db-backup-2026-11-06-120000.sql', 'select 1;');
    File::put(config()->string('wireup.backup_path').'/backup-2026-11-06-120000-manual.zip', 'zip');

    $this->artisan(RestoreDatabaseBackupCommand::class, ['file' => $file, '--force' => true])
        ->expectsOutputToContain('There is no sqlite database backup called')
        ->assertExitCode(1);
})->with([
    'missing' => 'db-backup-2020-01-01-000000.sqlite',
    'another database' => 'db-backup-2026-11-06-120000.sql',
    'a full backup' => 'backup-2026-11-06-120000-manual.zip',
]);

it('clears the database before loading a mysql or postgres backup into it', function (string $driver, string $client): void {
    Process::fake();
    config()->set('database.default', $driver);
    File::put(config()->string('wireup.backup_path').'/db-backup-2026-11-06-120000.sql', 'select 1;');

    $this->artisan(RestoreDatabaseBackupCommand::class, ['file' => 'db-backup-2026-11-06-120000.sql', '--force' => true])
        ->assertExitCode(0);

    Process::assertRanInOrder([
        [$client, '--version'],
        [PHP_BINARY, 'artisan', 'db:wipe', '--force', '--drop-views'],
        fn (PendingProcess $process): bool => is_array($process->command) && $process->command[0] === $client,
    ]);
})->with([['mysql', 'mysql'], ['pgsql', 'psql']]);

it('leaves the database alone when the database client is missing', function (): void {
    Process::fake(['*--version*' => Process::result(errorOutput: 'mysql: not found', exitCode: 127)]);
    config()->set('database.default', 'mysql');
    File::put(config()->string('wireup.backup_path').'/db-backup-2026-11-06-120000.sql', 'select 1;');

    $this->artisan(RestoreDatabaseBackupCommand::class, ['file' => 'db-backup-2026-11-06-120000.sql', '--force' => true])
        ->expectsOutputToContain('Restoring the database needs the mysql command on the server.')
        ->assertExitCode(1);

    Process::assertDidntRun(fn (PendingProcess $process): bool => in_array('db:wipe', (array) $process->command, true));
});

it('does not load the backup when the database cannot be cleared', function (): void {
    Process::fake([
        '*db:wipe*' => Process::result(errorOutput: 'wipe boom', exitCode: 1),
        '*' => Process::result(),
    ]);
    config()->set('database.default', 'mysql');
    File::put(config()->string('wireup.backup_path').'/db-backup-2026-11-06-120000.sql', 'select 1;');

    $this->artisan(RestoreDatabaseBackupCommand::class, ['file' => 'db-backup-2026-11-06-120000.sql', '--force' => true])
        ->expectsOutputToContain('wipe boom')
        ->assertExitCode(1);

    Process::assertRanTimes(fn (PendingProcess $process): bool => is_array($process->command) && $process->command[0] === 'mysql', 1);
});
