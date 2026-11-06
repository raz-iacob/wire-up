<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DatabaseDumper;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Description('Replace the database with a database backup from storage/app/backups, such as the one taken before an update')]
#[Signature('wireup:backup:restore-database {file : The backup file name, e.g. db-backup-2026-11-06-120000.sqlite} {--force : Skip the confirmation prompt}')]
final class RestoreDatabaseBackupCommand extends Command
{
    public function handle(DatabaseDumper $dumper): int
    {
        $file = basename((string) $this->argument('file'));
        $path = config()->string('wireup.backup_path').'/'.$file;

        if (! str_starts_with($file, 'db-backup-') || ! str_ends_with($file, '.'.$dumper->extension()) || ! File::isFile($path)) {
            $this->components->error("There is no {$dumper->driver()} database backup called {$file} in ".config()->string('wireup.backup_path').'.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Replace this site's database with {$file}?")) {
            $this->components->info('Nothing was restored.');

            return self::SUCCESS;
        }

        $error = $dumper->restore($path);

        if ($error !== null) {
            $this->components->error($error);

            return self::FAILURE;
        }

        $this->components->info("Restored the database from {$file}.");

        return self::SUCCESS;
    }
}
