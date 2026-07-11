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

#[Name('update-page-blocks')]
#[Description('Replace the full set of content blocks on a page. Blocks omitted from the list are deleted — include the ids of existing blocks (from get-page) to keep or update them; blocks without an id are created.')]
final class UpdatePageBlocksTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'page' => ['required', 'integer'],
                ...Pages::blockRules(),
                'blocks' => ['present', 'array'],
            ],
            [
                'page.required' => 'Pass the page id. Use list-pages to find it.',
                'page.integer' => 'The page id must be an integer. Use list-pages to find it.',
                'blocks.present' => 'Pass the full list of blocks the page should have. An empty list removes every block.',
                ...Pages::blockMessages(),
            ],
        );

        $page = Page::query()->with(['slugs', 'translations'])->find($validated['page']);

        if ($page === null) {
            return Response::error("No page with id {$validated['page']}. Use list-pages to see the available pages.");
        }

        $page->updateBlocks($validated['blocks']);

        return Pages::json([
            'page' => Pages::summary($page),
            'blocks' => count($validated['blocks']),
        ]);
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

            'blocks' => $schema->array()
                ->items($schema->object())
                ->description('The complete ordered block list: [{"id": <existing id, omit for new>, "type": "<block key>", "content": {...}}]. See the block-types resource for content shapes.')
                ->required(),
        ];
    }
}
