<?php

declare(strict_types=1);

use App\Console\Commands\BackupDatabaseCommand;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

function fileBackedSqliteDatabase(): string
{
    $database = config()->string('wireup.backup_path').'/source.sqlite';
    File::ensureDirectoryExists(dirname($database));

    $pdo = new PDO('sqlite:'.$database);
    $pdo->exec('create table samples (name text)');
    $pdo->exec("insert into samples values ('wire-up')");

    config()->set('database.connections.sqlite_file', ['driver' => 'sqlite', 'database' => $database]);
    config()->set('database.default', 'sqlite_file');

    return $database;
}

beforeEach(function (): void {
    config()->set('wireup.backup_path', storage_path('framework/testing/backups-'.Str::random(8)));
});

afterEach(function (): void {
    File::deleteDirectory(config()->string('wireup.backup_path'));
});

it('backs up a sqlite database into the backup directory', function (): void {
    fileBackedSqliteDatabase();

    $this->artisan(BackupDatabaseCommand::class)
        ->expectsOutputToContain('Database backed up to')
        ->assertExitCode(0);

    $backups = File::glob(config()->string('wireup.backup_path').'/db-backup-*.sqlite');

    expect($backups)->toHaveCount(1);

    $copy = new PDO('sqlite:'.$backups[0]);

    expect($copy->query('select count(*) from samples')->fetchColumn())->toEqual(1);
});

it('refuses to back up an in-memory sqlite database', function (): void {
    $this->artisan(BackupDatabaseCommand::class)
        ->expectsOutputToContain('in-memory')
        ->assertExitCode(1);
});

it('fails with the sqlite error when the target cannot be written', function (): void {
    $this->travelTo(now());

    fileBackedSqliteDatabase();

    File::put(config()->string('wireup.backup_path').'/db-backup-'.now()->format('Y-m-d-His').'.sqlite', 'occupied');

    $this->artisan(BackupDatabaseCommand::class)->assertExitCode(1);
});

it('prunes old backups beyond the retention limit', function (): void {
    fileBackedSqliteDatabase();

    foreach (range(1, 5) as $day) {
        File::put(config()->string('wireup.backup_path')."/db-backup-2020-01-0{$day}-000000.sql", '');
    }

    $this->artisan(BackupDatabaseCommand::class)->assertExitCode(0);

    $backups = File::glob(config()->string('wireup.backup_path').'/db-backup-*');

    expect($backups)->toHaveCount(5)
        ->and(File::exists(config()->string('wireup.backup_path').'/db-backup-2020-01-01-000000.sql'))->toBeFalse();
});

it('backs up a mysql database through mysqldump', function (): void {
    Process::fake();

    config()->set('database.default', 'mysql');
    config()->set('database.connections.mysql.password', 'secret');

    $this->artisan(BackupDatabaseCommand::class)->assertExitCode(0);

    Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command)
        && $process->command[0] === 'mysqldump'
        && in_array('--single-transaction', $process->command, true)
        && Str::startsWith($process->command[4], '--result-file='.config()->string('wireup.backup_path'))
        && $process->environment === ['MYSQL_PWD' => 'secret']);
});

it('backs up a postgres database through pg_dump', function (): void {
    Process::fake();

    config()->set('database.default', 'pgsql');
    config()->set('database.connections.pgsql.password', 'secret');

    $this->artisan(BackupDatabaseCommand::class)->assertExitCode(0);

    Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command)
        && $process->command[0] === 'pg_dump'
        && in_array('--no-owner', $process->command, true)
        && $process->environment === ['PGPASSWORD' => 'secret']);
});

it('fails with the dump output when the dump tool errors', function (): void {
    Process::fake(['*mysqldump*' => Process::result(errorOutput: 'dump boom', exitCode: 1)]);

    config()->set('database.default', 'mysql');

    $this->artisan(BackupDatabaseCommand::class)
        ->expectsOutputToContain('dump boom')
        ->assertExitCode(1);
});

it('fails for a database driver it cannot dump', function (): void {
    config()->set('database.default', 'sqlsrv');
    config()->set('database.connections.sqlsrv.driver', 'sqlsrv');

    $this->artisan(BackupDatabaseCommand::class)
        ->expectsOutputToContain('Back up the database manually')
        ->assertExitCode(1);
});
