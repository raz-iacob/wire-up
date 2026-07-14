<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ImageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Prune the transformed image cache down to its configured size cap')]
#[Signature('wireup:prune-image-cache')]
final class PruneImageCacheCommand extends Command
{
    public function handle(): int
    {
        $deleted = ImageService::pruneCache(config()->integer('media.transform_cache_max_megabytes'));

        $this->components->info($deleted === 0
            ? 'Image cache is within its size cap.'
            : "Pruned {$deleted} cached image variants.");

        return self::SUCCESS;
    }
}
