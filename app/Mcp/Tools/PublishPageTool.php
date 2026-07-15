<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\UpdatePageAction;
use App\Ai\Contracts\RequiresConfirmation;
use App\Enums\ContentStatus;
use App\Mcp\Support\Pages;
use App\Models\Page;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('publish-page')]
#[Description('Publish a page so it is publicly visible, or set it back to draft.')]
final class PublishPageTool extends Tool implements RequiresConfirmation
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'page' => ['required', 'integer'],
                'status' => ['nullable', 'string', 'in:published,draft'],
            ],
            [
                'page.required' => 'Pass the page id. Use list-pages to find it.',
                'page.integer' => 'The page id must be an integer. Use list-pages to find it.',
                'status.in' => 'Status must be "published" or "draft".',
            ],
        );

        $page = Page::query()->with(['slugs', 'translations'])->find($validated['page']);

        if ($page === null) {
            return Response::error("No page with id {$validated['page']}. Use list-pages to see the available pages.");
        }

        $status = ($validated['status'] ?? 'published') === 'published'
            ? ContentStatus::PUBLISHED
            : ContentStatus::DRAFT;

        new UpdatePageAction()->handle($page, ['status' => $status]);

        return Pages::json(['page' => Pages::summary($page->refresh())]);
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

            'status' => $schema->string()
                ->enum(['published', 'draft'])
                ->description('The publication status to set.')
                ->default('published'),
        ];
    }
}
