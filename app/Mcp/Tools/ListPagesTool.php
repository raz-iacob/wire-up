<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\Pages;
use App\Models\Page;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list-pages')]
#[Description('List every page with its id, title, slug, URL, publication status, and homepage flag.')]
final class ListPagesTool extends Tool
{
    public function handle(Request $request): Response
    {
        $pages = Page::query()
            ->with(['slugs', 'translations'])
            ->latest('updated_at')
            ->get()
            ->map(fn (Page $page): array => Pages::summary($page));

        return Pages::json(['pages' => $pages->all()]);
    }
}
