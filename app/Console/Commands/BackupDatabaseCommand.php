<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DatabaseDumper;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Description('Back up the database to storage/app/backups')]
#[Signature('wireup:backup')]
final class BackupDatabaseCommand extends Command
{
    public function handle(DatabaseDumper $dumper): int
    {
        $directory = config()->string('wireup.backup_path');
        File::ensureDirectoryExists($directory);

        $path = $directory.'/db-backup-'.now()->format('Y-m-d-His').'.'.$dumper->extension();

        $error = $dumper->dump($path);

        if ($error !== null) {
            $this->components->error($error);

            return self::FAILURE;
        }

        $this->prune($directory);

        $this->components->info("Database backed up to {$path}");

        return self::SUCCESS;
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
