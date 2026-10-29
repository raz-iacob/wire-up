<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BackupService;
use App\Services\DatabaseDumper;
use App\Services\SiteBundle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

#[Description('Restore the database and uploaded files from a backup in storage/app/backups')]
#[Signature('wireup:backup:restore {file : The backup file name, e.g. backup-2026-10-29-030000-automatic.zip} {--force : Skip the confirmation prompt}')]
final class RestoreBackupCommand extends Command
{
    public function handle(BackupService $backups, DatabaseDumper $dumper, SiteBundle $bundle): int
    {
        $file = basename((string) $this->argument('file'));
        $path = str_ends_with($file, '.zip') ? $backups->find($file) : null;

        if ($path === null) {
            $this->components->error("There is no backup called {$file} in {$backups->directory()}.");

            return self::FAILURE;
        }

        $zip = new ZipArchive;
        $manifest = $zip->open($path) === true ? json_decode((string) $zip->getFromName('manifest.json'), true) : null;

        if (! is_array($manifest)) {
            $this->components->error("{$file} is not a Wire-Up backup.");

            return self::FAILURE;
        }

        $problem = $this->problem($manifest, $zip, $dumper, $bundle);

        if ($problem !== null) {
            $this->components->error($problem);

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Replace this site's database and uploaded files with {$file}?")) {
            $this->components->info('Nothing was restored.');

            return self::SUCCESS;
        }

        $this->call('down');

        $error = $this->restoreDatabase($zip, $dumper, $backups->directory());

        if ($error !== null) {
            $this->components->error($error);
            $this->components->error('The site is still in maintenance mode. Fix the problem, then run: php artisan up');

            return self::FAILURE;
        }

        $files = $this->restoreFiles($zip);
        $zip->close();

        $this->call('migrate', ['--force' => true]);
        $this->call('cache:clear');
        $this->call('up');

        $this->components->info("Restored {$file}: the database and {$files} uploaded files.");

        return self::SUCCESS;
    }

    /**
     * @param  array<mixed>  $manifest
     */
    private function problem(array $manifest, ZipArchive $zip, DatabaseDumper $dumper, SiteBundle $bundle): ?string
    {
        if (($manifest['database'] ?? null) !== $dumper->driver()) {
            return 'This backup holds a '.(is_string($manifest['database'] ?? null) ? $manifest['database'] : 'different').' database, but this site runs '.$dumper->driver().'. Restore it by hand.';
        }

        if (is_int($manifest['migrations'] ?? null) && $manifest['migrations'] > $bundle->appliedMigrations()) {
            return 'This backup comes from a newer version of Wire-Up. Update this site first, then restore it.';
        }

        if ($zip->locateName('database.'.$dumper->extension()) === false) {
            return 'This backup has no database in it.';
        }

        return null;
    }

    private function restoreDatabase(ZipArchive $zip, DatabaseDumper $dumper, string $directory): ?string
    {
        $database = $directory.'/.restore.'.$dumper->extension();

        try {
            File::put($database, $zip->getStream('database.'.$dumper->extension()) ?: '');

            return $dumper->load($database);
        } catch (Throwable $exception) {
            return $exception->getMessage();
        } finally {
            File::delete($database);
        }
    }

    private function restoreFiles(ZipArchive $zip): int
    {
        $disk = Storage::disk(config()->string('filesystems.media'));
        $restored = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            $relative = mb_substr($name, mb_strlen('files/'));

            if (! str_starts_with($name, 'files/') || str_ends_with($name, '/') || ! SiteBundle::isSafeRelativePath($relative)) {
                continue;
            }

            $stream = $zip->getStream($name);

            if ($stream !== false) {
                $disk->writeStream($relative, $stream);
                $restored++;
            }
        }

        return $restored;
    }
}
