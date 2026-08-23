<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\CreateCategoryAction;
use App\Mcp\Support\Pages;
use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create-category')]
#[Description('Create a category for grouping pages and records. Categories are global and shared across every content type. Returns the category id to pass to create-record, update-record, or a collection block filtering by category. A category with the same name is reused rather than duplicated.')]
final class CreateCategoryTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            ['name' => ['required', 'string', 'max:255']],
            ['name.required' => 'Pass a name for the category, such as "Guides".'],
        );

        $locale = app()->getLocale();
        $name = mb_trim($validated['name']);

        $existing = Category::query()
            ->with('translations')
            ->get()
            ->first(fn (Category $category): bool => mb_strtolower($category->name) === mb_strtolower($name));

        if ($existing instanceof Category) {
            return Pages::json([
                'category' => ['id' => $existing->id, 'name' => $existing->name, 'created' => false],
                'hint' => 'A category with that name already exists, so it was reused.',
            ]);
        }

        $category = resolve(CreateCategoryAction::class)->handle(['name' => [$locale => $name]]);

        return Pages::json([
            'category' => ['id' => $category->id, 'name' => $name, 'created' => true],
            'hint' => 'Attach it with create-record or update-record "categories", or filter a collection block by it.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->required()
                ->description('The category name, e.g. "Guides". Reused if a category with that name already exists.'),
        ];
    }
}
