<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Page;
use Illuminate\Support\Facades\DB;

final readonly class DeletePageAction
{
    /**
     * Execute the action.
     */
    public function handle(Page $page): void
    {
        DB::transaction(function () use ($page): void {
            $page->slugs()->delete();
            $page->translations()->delete();
            $page->delete();
        });
    }
}
