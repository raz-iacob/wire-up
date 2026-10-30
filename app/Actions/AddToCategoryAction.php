<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\Page;
use App\Models\Record;

final readonly class AddToCategoryAction
{
    public function handle(Page|Record $content, Category $category): bool
    {
        $categoryIds = $content->categories()->pluck('categories.id')->map(intval(...))->all();

        if (in_array($category->id, $categoryIds, true)) {
            return false;
        }

        $status = $content->computed_status;

        $attributes = [
            'categories' => [...$categoryIds, $category->id],
            'status' => $status,
            ...($status === ContentStatus::SCHEDULED ? ['published_at' => $content->published_at] : []),
        ];

        if ($content instanceof Page) {
            new UpdatePageAction()->handle($content, $attributes);
        } else {
            new UpdateRecordAction()->handle($content, $attributes);
        }

        return true;
    }
}
