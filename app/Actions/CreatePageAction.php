<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ActivityEvent;
use App\Enums\ContentStatus;
use App\Models\Page;
use App\Services\ActivityLog;
use Illuminate\Support\Facades\DB;

final readonly class CreatePageAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): Page
    {
        return DB::transaction(function () use ($attributes): Page {
            $attributes['status'] ??= ContentStatus::DRAFT;
            $attributes['metadata'] = [
                ...($attributes['metadata'] ?? []),
                'published_locales' => $attributes['metadata']['published_locales']
                    ?? [resolve('localization')->getDefaultLocale()],
            ];

            $page = Page::query()->create($attributes);
            $page->setSlugs();

            ActivityLog::record(ActivityEvent::PAGE_CREATED, $page);

            return $page;
        });
    }
}
