<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;

final class CleanTempUploadsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'wireup:clean-temp {--older-than=24 : Delete files older than X hours}';

    /**
     * @var string
     */
    protected $description = 'Clean up Livewire temporary file uploads';

    public function handle(): int
    {
        $olderThanHours = (int) $this->option('older-than');
        $cutoffTime = now()->subHours($olderThanHours);

        $tempPath = storage_path('app/private/livewire-tmp');

        if (! File::exists($tempPath)) {
            $this->info('No Livewire temp directory found.');

            return self::SUCCESS;
        }

        $files = File::allFiles($tempPath);
        $deletedCount = 0;
        $totalSize = 0;

        foreach ($files as $file) {
            $fileModifiedTime = Date::createFromTimestamp($file->getMTime());

            if ($fileModifiedTime->lt($cutoffTime)) {
                $fileSize = $file->getSize();

                if (File::delete($file->getPathname())) {
                    $deletedCount++;
                    $totalSize += $fileSize;
                    $this->line("Deleted: {$file->getFilename()}");
                }
            }
        }

        if ($deletedCount === 0) {
            $this->info('No temporary files older than '.$olderThanHours.' hours found.');
        } else {
            $totalSizeMB = round($totalSize / 1024 / 1024, 2);
            $this->info("Deleted {$deletedCount} files, freed {$totalSizeMB} MB of disk space.");
        }

        return self::SUCCESS;
    }
}
