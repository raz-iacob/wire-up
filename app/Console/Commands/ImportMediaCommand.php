<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\StoreMediaFileAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Description('Import a local file into the media library')]
#[Signature('wireup:media:import {path* : One or more files to import} {--alt= : Alt text for the imported file}')]
final class ImportMediaCommand extends Command
{
    public function handle(StoreMediaFileAction $action): int
    {
        /** @var array<int, string> $paths */
        $paths = $this->argument('path');
        $alt = $this->option('alt');
        $failed = false;

        foreach ($paths as $path) {
            $resolved = realpath($path);

            if ($resolved === false || ! is_file($resolved)) {
                $this->components->error("No file at \"{$path}\".");
                $failed = true;

                continue;
            }

            try {
                $media = $action->handle($resolved, basename($resolved), [
                    'alt_text' => is_string($alt) && $alt !== '' ? $alt : null,
                ]);
            } catch (Throwable $throwable) {
                $this->components->error(basename($resolved).': '.$throwable->getMessage());
                $failed = true;

                continue;
            }

            $this->components->twoColumnDetail($media->source, 'id '.$media->id);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
