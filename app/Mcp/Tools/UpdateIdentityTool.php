<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\UpdateSettingsAction;
use App\Mcp\Support\Pages;
use App\Mcp\Support\SiteSettings;
use App\Models\Page;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update-identity')]
#[Description('Update the site identity: title, tagline (meta description), and which page is the homepage. Text applies to the default locale; other locales are left untouched.')]
final class UpdateIdentityTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'title' => ['sometimes', 'string', 'min:3', 'max:120'],
                'description' => ['sometimes', 'string', 'max:255'],
                'home_page' => ['sometimes', 'integer'],
            ],
            [
                'title.min' => 'The site title must be at least 3 characters.',
                'title.max' => 'The site title may not exceed 120 characters.',
                'description.max' => 'The tagline may not exceed 255 characters.',
            ],
        );

        if ($validated === []) {
            return Response::error('Pass at least one of: title, description, home_page.');
        }

        $default = resolve('localization')->getDefaultLocale();
        $values = [];

        if (isset($validated['title'])) {
            $values['title'] = [...SiteSettings::identity()['title'], $default => $validated['title']];
        }

        if (isset($validated['description'])) {
            $values['description'] = [...SiteSettings::identity()['description'], $default => $validated['description']];
        }

        if (isset($validated['home_page'])) {
            $page = Page::query()->find($validated['home_page']);

            if ($page === null) {
                return Response::error("No page with id {$validated['home_page']}. Use list-pages to see the available pages.");
            }

            $values['home_page_id'] = $page->id;
        }

        new UpdateSettingsAction()->handle($values);

        return Pages::json(['identity' => SiteSettings::identity()]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('The site title, shown in the header and used across SEO tags.'),

            'description' => $schema->string()
                ->description('The site tagline, used as the default meta description.'),

            'home_page' => $schema->integer()
                ->description('Id of the page to serve at the site root.'),
        ];
    }
}
