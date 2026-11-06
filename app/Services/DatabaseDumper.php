<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PDO;
use PDOException;
use SQLite3;
use Throwable;

final class DatabaseDumper
{
    public function connection(): string
    {
        return config()->string('database.default');
    }

    public function driver(): string
    {
        return (string) config("database.connections.{$this->connection()}.driver");
    }

    public function extension(): string
    {
        return $this->driver() === 'sqlite' ? 'sqlite' : 'sql';
    }

    public function dump(string $path): ?string
    {
        $existed = File::exists($path);

        $error = match ($this->driver()) {
            'sqlite' => $this->dumpSqlite($path),
            'mysql', 'mariadb' => $this->dumpMysql($path),
            'pgsql' => $this->dumpPgsql($path),
            default => "Automatic backups support MySQL, MariaDB, PostgreSQL and SQLite — not \"{$this->driver()}\". Back up the database manually.",
        };

        if ($error !== null && ! $existed) {
            File::delete($path);
        }

        return $error;
    }

    public function restore(string $path): ?string
    {
        $client = match ($this->driver()) {
            'mysql', 'mariadb' => 'mysql',
            'pgsql' => 'psql',
            default => null,
        };

        if ($client !== null) {
            if (Process::run([$client, '--version'])->failed()) {
                return "Restoring the database needs the {$client} command on the server.";
            }

            $wipe = Process::path(base_path())->timeout(600)->run([PHP_BINARY, 'artisan', 'db:wipe', '--force', '--drop-views']);

            if ($wipe->failed()) {
                return mb_trim($wipe->output()."\n".$wipe->errorOutput());
            }
        }

        return $this->load($path);
    }

    public function load(string $path): ?string
    {
        return match ($this->driver()) {
            'sqlite' => $this->loadSqlite($path),
            'mysql', 'mariadb' => $this->loadMysql($path),
            'pgsql' => $this->loadPgsql($path),
            default => "Restoring supports MySQL, MariaDB, PostgreSQL and SQLite — not \"{$this->driver()}\". Restore the database manually.",
        };
    }

    private function dumpSqlite(string $path): ?string
    {
        $database = $this->setting('database');

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

    private function dumpMysql(string $path): ?string
    {
        $result = Process::env(['MYSQL_PWD' => $this->setting('password')])
            ->timeout(600)
            ->run([
                'mysqldump',
                '--single-transaction',
                '--quick',
                '--no-tablespaces',
                '--result-file='.$path,
                '--host='.($this->setting('host') ?: '127.0.0.1'),
                '--port='.($this->setting('port') ?: '3306'),
                '--user='.$this->setting('username'),
                $this->setting('database'),
            ]);

        return $result->successful() ? null : mb_trim($result->output()."\n".$result->errorOutput());
    }

    private function dumpPgsql(string $path): ?string
    {
        $result = Process::env(['PGPASSWORD' => $this->setting('password')])
            ->timeout(600)
            ->run([
                'pg_dump',
                '--no-owner',
                '--file='.$path,
                '--host='.($this->setting('host') ?: '127.0.0.1'),
                '--port='.($this->setting('port') ?: '5432'),
                '--username='.$this->setting('username'),
                $this->setting('database'),
            ]);

        return $result->successful() ? null : mb_trim($result->output()."\n".$result->errorOutput());
    }

    private function loadSqlite(string $path): ?string
    {
        $database = $this->setting('database');

        if ($database === '' || $database === ':memory:') {
            return 'The sqlite database is in-memory; there is nothing to restore into.';
        }

        DB::purge($this->connection());

        try {
            $backup = new SQLite3($path, SQLITE3_OPEN_READONLY);
            $live = new SQLite3($database);
            $live->busyTimeout(5000);

            $copied = $backup->backup($live);

            $backup->close();
            $live->close();
        } catch (Throwable $throwable) {
            return $throwable->getMessage();
        }

        return $copied ? null : "The backup could not be copied over {$database}.";
    }

    private function loadMysql(string $path): ?string
    {
        $result = Process::env(['MYSQL_PWD' => $this->setting('password')])
            ->input(fopen($path, 'rb') ?: '')
            ->timeout(1800)
            ->run([
                'mysql',
                '--host='.($this->setting('host') ?: '127.0.0.1'),
                '--port='.($this->setting('port') ?: '3306'),
                '--user='.$this->setting('username'),
                $this->setting('database'),
            ]);

        return $result->successful() ? null : mb_trim($result->output()."\n".$result->errorOutput());
    }

    private function loadPgsql(string $path): ?string
    {
        $result = Process::env(['PGPASSWORD' => $this->setting('password')])
            ->timeout(1800)
            ->run([
                'psql',
                '--quiet',
                '--set=ON_ERROR_STOP=1',
                '--file='.$path,
                '--host='.($this->setting('host') ?: '127.0.0.1'),
                '--port='.($this->setting('port') ?: '5432'),
                '--username='.$this->setting('username'),
                $this->setting('database'),
            ]);

        return $result->successful() ? null : mb_trim($result->output()."\n".$result->errorOutput());
    }

    private function setting(string $key): string
    {
        return (string) config("database.connections.{$this->connection()}.{$key}");
    }
}
