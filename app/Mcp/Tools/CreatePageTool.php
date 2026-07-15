<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\CreatePageAction;
use App\Actions\UpdatePageAction;
use App\Enums\ContentStatus;
use App\Mcp\Support\Pages;
use App\Models\Page;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create-page')]
#[Description('Create a page with an optional initial set of content blocks. Pages are created as drafts unless publish is true. Returns the new page with its URL.')]
final class CreatePageTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'title' => ['required', 'string', 'min:3', 'max:255'],
                'description' => ['nullable', 'string', 'max:160'],
                'publish' => ['boolean'],
                ...Pages::blockRules(),
            ],
            [
                'title.required' => 'Give the page a title.',
                'title.min' => 'The page title must be at least 3 characters.',
                'title.max' => 'The page title may not be longer than 255 characters.',
                'description.max' => 'The description may not be longer than 160 characters — it is used as the meta description.',
                ...Pages::blockMessages(),
            ],
        );

        $existing = Page::query()
            ->whereHas('translations', function (Builder $query) use ($validated): void {
                $query->where('key', 'title')
                    ->where('locale', app()->getLocale())
                    ->where('body', $validated['title']);
            })
            ->first();

        if ($existing !== null) {
            return Response::error("A page titled \"{$validated['title']}\" already exists (id {$existing->id}). Update it with update-page-blocks instead of creating a duplicate, or choose a different title.");
        }

        $page = new CreatePageAction()->handle([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? '',
        ]);

        if (($validated['blocks'] ?? []) !== []) {
            $page->updateBlocks($validated['blocks']);
        }

        if ($validated['publish'] ?? false) {
            new UpdatePageAction()->handle($page, ['status' => ContentStatus::PUBLISHED]);
        }

        return Pages::json([
            'page' => Pages::summary($page->refresh()),
            'hint' => 'Verify the content by fetching the url with an Accept: text/markdown header, then use publish-page when it looks right.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('The page title. The slug is derived from it automatically.')
                ->required(),

            'description' => $schema->string()
                ->description('Optional meta description (max 160 characters).'),

            'blocks' => $schema->array()
                ->items($schema->object())
                ->description('Ordered content blocks: [{"type": "<block key>", "content": {...}}]. See the block-types resource for the available types and content shapes.'),

            'publish' => $schema->boolean()
                ->description('Publish the page immediately instead of leaving it as a draft.')
                ->default(false),
        ];
    }
}
