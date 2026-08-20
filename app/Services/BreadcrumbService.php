<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;
use App\Models\Record;

final class BreadcrumbService
{
    public static function current(): self
    {
        return new self;
    }

    /**
     * @return array<int, array{label: string, url: ?string}>
     */
    public function trail(Page|Record $content, bool $withHome = true, string $homeLabel = ''): array
    {
        if ($this->isHome($content)) {
            return [];
        }

        $crumbs = [];

        if ($withHome) {
            $crumbs[] = [
                'label' => $homeLabel !== '' ? $homeLabel : __('Home'),
                'url' => route('home'),
            ];
        }

        if ($content instanceof Record) {
            $crumbs[] = $this->recordTypeCrumb($content);
        }

        $crumbs[] = ['label' => $content->title, 'url' => null];

        return $crumbs;
    }

    /**
     * @return array<int, array{'@type': string, position: int, name: string, item?: string}>
     */
    public function schemaItems(Page|Record $content, string $currentUrl): array
    {
        $items = [];

        foreach ($this->trail($content) as $index => $crumb) {
            $item = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['label'],
            ];

            $url = $crumb['url'] ?? $currentUrl;

            if ($url !== '') {
                $item['item'] = $url;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return array{label: string, url: ?string}
     */
    private function recordTypeCrumb(Record $record): array
    {
        $landing = Page::query()
            ->published()
            ->with(['translations', 'slugs'])
            ->forSlug($record->recordType->slug_prefix)
            ->first();

        if ($landing instanceof Page) {
            return ['label' => $landing->title, 'url' => $landing->getUrl()];
        }

        return ['label' => $record->recordType->name, 'url' => null];
    }

    private function isHome(Page|Record $content): bool
    {
        return $content instanceof Page
            && $content->id === SettingsService::current()->homePageId();
    }
}
