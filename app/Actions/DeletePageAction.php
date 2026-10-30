<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Models\Page;
use App\Services\ActivityLog;
use Illuminate\Support\Facades\DB;

final readonly class DeletePageAction
{
    public function handle(Page $page): void
    {
        DB::transaction(function () use ($page): void {
            ActivityLog::record(ActivityEvent::PAGE_DELETED, $page);

            $page->slugs()->delete();
            $page->translations()->delete();
            $page->delete();
        });
    }
}
