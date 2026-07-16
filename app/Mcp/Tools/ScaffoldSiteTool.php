<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\CreatePageAction;
use App\Actions\UpdateSettingsAction;
use App\Mcp\Support\Pages;
use App\Models\Page;
use App\Services\SettingsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('scaffold-site')]
#[Description('Scaffold a site skeleton in one call: create (or reuse, by title) the given pages as blank drafts, wire them into the header and footer navigation, and set the homepage. Fill each page with update-page-blocks afterwards, then publish-page. Sets (replaces) the header and footer menus for the default locale.')]
final class ScaffoldSiteTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'pages' => ['required', 'array', 'min:1', 'max:20'],
                'pages.*.title' => ['required', 'string', 'min:3', 'max:255'],
                'pages.*.description' => ['nullable', 'string', 'max:160'],
                'pages.*.homepage' => ['boolean'],
                'pages.*.nav' => ['sometimes', 'string', 'in:header,footer,both,none'],
            ],
            [
                'pages.required' => 'Pass at least one page to scaffold.',
                'pages.min' => 'Pass at least one page to scaffold.',
                'pages.max' => 'Scaffold at most 20 pages at once.',
                'pages.*.title.required' => 'Every page needs a title.',
                'pages.*.title.min' => 'Each page title must be at least 3 characters.',
                'pages.*.title.max' => 'A page title may not be longer than 255 characters.',
                'pages.*.description.max' => 'A page description may not be longer than 160 characters.',
                'pages.*.nav.in' => 'nav must be one of: header, footer, both, none.',
            ],
        );

        /** @var array<int, array<string, mixed>> $definitions */
        $definitions = array_values($validated['pages']);

        $titles = array_map(fn (array $definition): string => (string) $definition['title'], $definitions);
        if (count($titles) !== count(array_unique($titles))) {
            return Response::error('Page titles must be unique within one scaffold call.');
        }

        $homepages = array_filter($definitions, fn (array $definition): bool => (bool) ($definition['homepage'] ?? false));
        if (count($homepages) > 1) {
            return Response::error('Only one page can be the homepage.');
        }

        $locale = app()->getLocale();
        $header = [];
        $footer = [];
        $homepageId = null;
        $pages = [];

        foreach ($definitions as $definition) {
            $title = (string) $definition['title'];
            $existing = $this->findByTitle($title, $locale);
            $created = ! $existing instanceof Page;

            $page = $existing ?? new CreatePageAction()->handle([
                'title' => $title,
                'description' => (string) ($definition['description'] ?? ''),
            ]);

            $nav = is_string($definition['nav'] ?? null) ? $definition['nav'] : 'header';

            if (in_array($nav, ['header', 'both'], true)) {
                $header[] = $this->menuItem($page);
            }

            if (in_array($nav, ['footer', 'both'], true)) {
                $footer[] = $this->menuItem($page);
            }

            if ((bool) ($definition['homepage'] ?? false)) {
                $homepageId = $page->id;
            }

            $pages[] = ['created' => $created, 'nav' => $nav, ...Pages::summary($page->refresh())];
        }

        $menus = SettingsService::current()->allMenus();
        foreach ($menus as $i => $menu) {
            if ($menu['key'] === 'header') {
                $menus[$i]['items'][$locale] = $header;
            }
            if ($menu['key'] === 'footer') {
                $menus[$i]['items'][$locale] = $footer;
            }
        }

        $settings = ['menus' => $menus];
        if ($homepageId !== null) {
            $settings['home_page_id'] = $homepageId;
        }

        new UpdateSettingsAction()->handle($settings);

        return Pages::json([
            'pages' => $pages,
            'header_nav' => array_column($header, 'label'),
            'footer_nav' => array_column($footer, 'label'),
            'homepage_id' => $homepageId,
            'hint' => 'Pages are blank drafts. Fill each one with update-page-blocks (see the block-types resource), then publish-page. Navigation links and the homepage resolve once the pages are published.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'pages' => $schema->array()
                ->items($schema->object())
                ->description('The pages to scaffold, in order: [{"title": "About", "description": "optional meta description (max 160)", "homepage": false, "nav": "header"|"footer"|"both"|"none"}]. Each page is created as a blank draft; "nav" adds it to the header and/or footer menu (default "header"). Set "homepage": true on exactly one page. A page whose title already exists is reused, not duplicated.')
                ->required(),
        ];
    }

    private function findByTitle(string $title, string $locale): ?Page
    {
        return Page::query()
            ->whereHas('translations', function (Builder $query) use ($title, $locale): void {
                $query->where('key', 'title')
                    ->where('locale', $locale)
                    ->where('body', $title);
            })
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function menuItem(Page $page): array
    {
        return [
            'type' => 'page',
            'appearance' => 'link',
            'target' => '_self',
            'label' => $page->title,
            'page_id' => $page->id,
            'url' => '',
            'icon' => '',
            'icon_name' => '',
            'icon_svg' => '',
            'badge' => '',
            'badgeColor' => 'zinc',
        ];
    }
}
