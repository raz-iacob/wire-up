<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\PageCache;
use Illuminate\Support\Facades\DB;

trait ClearsPageCache
{
    protected static function bootClearsPageCache(): void
    {
        static::saved(function (): void {
            DB::afterCommit(fn () => resolve(PageCache::class)->flush());
        });

        static::deleted(function (): void {
            DB::afterCommit(fn () => resolve(PageCache::class)->flush());
        });
    }
}
