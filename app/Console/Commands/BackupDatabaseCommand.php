<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PDO;
use PDOException;

#[Description('Back up the database to storage/app/backups')]
#[Signature('wireup:backup')]
final class BackupDatabaseCommand extends Command
{
    public function handle(): int
    {
        $connection = config()->string('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        $directory = config()->string('wireup.backup_path');
        File::ensureDirectoryExists($directory);

        $path = $directory.'/db-backup-'.now()->format('Y-m-d-His').($driver === 'sqlite' ? '.sqlite' : '.sql');

        $error = match ($driver) {
            'sqlite' => $this->backupSqlite($connection, $path),
            'mysql', 'mariadb' => $this->backupMysql($connection, $path),
            'pgsql' => $this->backupPgsql($connection, $path),
            default => "Automatic backups support MySQL, MariaDB, PostgreSQL and SQLite — not \"{$driver}\". Back up the database manually.",
        };

        if ($error !== null) {
            $this->components->error($error);

            return self::FAILURE;
        }

        $this->prune($directory);

        $this->components->info("Database backed up to {$path}");

        return self::SUCCESS;
    }

    private function backupSqlite(string $connection, string $path): ?string
    {
        $database = (string) config("database.connections.{$connection}.database");

        if ($database === '' || $database === ':memory:') {
            return 'The sqlite database is in-memory; there is nothing to back up.';
        }

        try {
            $pdo = new PDO('sqlite:'.$database);
            $pdo->exec('VACUUM INTO '.$pdo->quote($path));
        } catch (PDOException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    private function backupMysql(string $connection, string $path): ?string
    {
        $result = Process::env(['MYSQL_PWD' => (string) config("database.connections.{$connection}.password")])
            ->timeout(600)
            ->run([
                'mysqldump',
                '--single-transaction',
                '--quick',
                '--no-tablespaces',
                '--result-file='.$path,
                '--host='.((string) config("database.connections.{$connection}.host") ?: '127.0.0.1'),
                '--port='.((string) config("database.connections.{$connection}.port") ?: '3306'),
                '--user='.config("database.connections.{$connection}.username"),
                (string) config("database.connections.{$connection}.database"),
            ]);

        return $result->successful() ? null : mb_trim($result->output()."\n".$result->errorOutput());
    }

    private function backupPgsql(string $connection, string $path): ?string
    {
        $result = Process::env(['PGPASSWORD' => (string) config("database.connections.{$connection}.password")])
            ->timeout(600)
            ->run([
                'pg_dump',
                '--no-owner',
                '--file='.$path,
                '--host='.((string) config("database.connections.{$connection}.host") ?: '127.0.0.1'),
                '--port='.((string) config("database.connections.{$connection}.port") ?: '5432'),
                '--username='.config("database.connections.{$connection}.username"),
                (string) config("database.connections.{$connection}.database"),
            ]);

        return $result->successful() ? null : mb_trim($result->output()."\n".$result->errorOutput());
    }

    private function prune(string $directory): void
    {
        $backups = collect(File::glob($directory.'/db-backup-*'))->sort()->values();

        $expired = $backups->slice(0, max(0, $backups->count() - config()->integer('wireup.backups_to_keep')));

        foreach ($expired as $backup) {
            File::delete($backup);
        }
    }
}
