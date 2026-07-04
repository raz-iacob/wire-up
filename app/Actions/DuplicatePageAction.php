<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ContentStatus;
use App\Models\Page;
use Illuminate\Support\Facades\DB;

final readonly class DuplicatePageAction
{
    public function handle(Page $page, ?string $title = null): Page
    {
        return DB::transaction(function () use ($page, $title): Page {
            $page->loadMissing(['blocks', 'media', 'categories', 'translations']);

            $titles = collect($page->translationsFor('title'))
                ->map(fn (string $body): string => $body !== '' ? 'Copy of '.$body : $body)
                ->all();

            if ($title !== null) {
                $titles[app()->getLocale()] = $title;
            }

            $copy = Page::query()->create([
                'title' => $titles,
                'description' => $page->translationsFor('description'),
                'metadata' => $page->metadata,
                'status' => ContentStatus::DRAFT,
                'published_at' => null,
            ]);

            foreach ($page->blocks as $block) {
                $copy->blocks()->create([
                    'type' => $block->type->value,
                    'position' => $block->position,
                    'content' => $block->content ?? [],
                ]);
            }

            foreach ($page->media as $media) {
                $copy->media()->attach($media->id, [
                    'role' => $media->pivot->role,
                    'locale' => $media->pivot->locale,
                    'position' => $media->pivot->position,
                    'crop' => $media->pivot->crop,
                    'metadata' => $media->pivot->metadata,
                ]);
            }

            $copy->categories()->sync($page->categories->pluck('id')->all());

            $copy->load('translations');
            $copy->setSlugs();

            return $copy;
        });
    }
}
