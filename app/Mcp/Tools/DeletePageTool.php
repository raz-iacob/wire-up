<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\DeletePageAction;
use App\Ai\Contracts\RequiresConfirmation;
use App\Mcp\Support\Pages;
use App\Models\Page;
use App\Models\Settings;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('delete-page')]
#[Description('Permanently delete a page along with its blocks, web addresses and translations. This cannot be undone — prefer publish-page with status "draft" to take a page offline while keeping it. The homepage cannot be deleted; point the homepage at another page first.')]
final class DeletePageTool extends Tool implements RequiresConfirmation
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            ['page' => ['required', 'integer']],
            ['page.required' => 'Pass the page id. Use list-pages to find it.'],
        );

        $page = Page::query()->with('slugs')->find($validated['page']);

        if ($page === null) {
            return Response::error("No page with id {$validated['page']}. Use list-pages to see the available pages.");
        }

        if ((int) Settings::get('home_page_id') === $page->id) {
            return Response::error("Page {$page->id} is the homepage and cannot be deleted. Set another page as the homepage first, then delete this one.");
        }

        $title = $page->title;

        resolve(DeletePageAction::class)->handle($page);

        return Pages::json([
            'deleted' => ['id' => $validated['page'], 'title' => $title],
            'hint' => 'The page, its blocks and its web addresses are gone. Remove any menu items that pointed at it with update-menu.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()
                ->required()
                ->description('The id of the page to delete, as returned by list-pages.'),
        ];
    }
}
