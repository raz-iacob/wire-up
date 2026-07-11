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
#[Description('Get a page in full: metadata, slugs, layout, and its ordered blocks with their ids, types, and content JSON.')]
final class GetPageTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            ['page' => ['required', 'integer']],
            [
                'page.required' => 'Pass the page id. Use list-pages to find it.',
                'page.integer' => 'The page id must be an integer. Use list-pages to find it.',
            ],
        );

        $page = Page::query()->with(['slugs', 'translations', 'blocks'])->find($validated['page']);

        if ($page === null) {
            return Response::error("No page with id {$validated['page']}. Use list-pages to see the available pages.");
        }

        return Pages::json(['page' => Pages::detailed($page)]);
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
        ];
    }
}
