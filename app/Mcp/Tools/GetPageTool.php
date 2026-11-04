<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\Pages;
use App\Models\Page;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get-page')]
#[Description('Get a page: metadata, slugs, layout, and its ordered blocks with their ids, types, and content JSON. Pass "outline": true for a short map instead — each block\'s id, type, anchor, heading and item ids — to find what to change before fetching everything.')]
final class GetPageTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            ['page' => ['required', 'integer'], 'outline' => ['boolean']],
            [
                'page.required' => 'Pass "page": the page id. Use list-pages to find it.',
                'page.integer' => '"page" must be the page id, a whole number. Use list-pages to find it.',
                'outline.boolean' => '"outline" must be true or false.',
            ],
        );

        $page = Page::query()->with(['slugs', 'translations', 'blocks'])->find($validated['page']);

        if ($page === null) {
            return Response::error("No page with id {$validated['page']}. Use list-pages to see the available pages.");
        }

        return Pages::json(['page' => Pages::detailed($page, (bool) ($validated['outline'] ?? false))]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()
                ->description('The page id, as returned by list-pages or create-page.')
                ->required(),

            'outline' => $schema->boolean()
                ->description('true returns each block\'s id, type, anchor, heading and item ids and titles instead of its full content. Defaults to false.'),
        ];
    }
}
