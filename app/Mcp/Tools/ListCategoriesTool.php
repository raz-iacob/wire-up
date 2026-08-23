<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\Pages;
use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list-categories')]
#[Description('List every category with its id and how many pages and records use it. Categories are global and shared across all content types. Use the ids with create-record, update-record, or a collection block filtering by category.')]
final class ListCategoriesTool extends Tool
{
    public function handle(Request $request): Response
    {
        $categories = Category::query()
            ->with('translations')
            ->withCount(['pages', 'records'])
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'pages' => $category->pages_count,
                'records' => $category->records_count,
            ])
            ->sortBy('name')
            ->values()
            ->all();

        return Pages::json([
            'categories' => $categories,
            'hint' => $categories === []
                ? 'No categories yet. Create one with create-category.'
                : 'Attach a category with create-record or update-record "categories".',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
