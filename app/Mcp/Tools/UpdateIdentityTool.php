<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\UpdateSettingsAction;
use App\Enums\MediaType;
use App\Mcp\Support\Pages;
use App\Mcp\Support\SiteSettings;
use App\Models\Media;
use App\Models\Page;
use App\Services\MediaItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update-identity')]
#[Description('Update the site identity: title, tagline (meta description), the default share image and which page is the homepage. Text applies to the default locale; other locales are left untouched.')]
final class UpdateIdentityTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'title' => ['sometimes', 'string', 'min:3', 'max:120'],
                'description' => ['sometimes', 'string', 'max:255'],
                'home_page' => ['sometimes', 'integer'],
                'default_share_image' => ['sometimes', 'nullable', 'integer'],
            ],
            [
                'home_page.integer' => '"home_page" must be a page id, a whole number. Use list-pages to find it.',
                'default_share_image.integer' => '"default_share_image" must be a media id, a whole number, or null to remove it. Use list-media to find it.',
                'title.min' => 'The site title must be at least 3 characters.',
                'title.max' => 'The site title may not exceed 120 characters.',
                'description.max' => 'The tagline may not exceed 255 characters.',
            ],
        );

        if ($validated === []) {
            return Response::error('Pass at least one of: title, description, default_share_image, home_page.');
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

        if (array_key_exists('default_share_image', $validated)) {
            $shareImage = $validated['default_share_image'] === null ? null : Media::query()->find($validated['default_share_image']);

            if ($validated['default_share_image'] !== null && $shareImage?->type !== MediaType::IMAGE) {
                return Response::error($shareImage === null
                    ? "No media with id {$validated['default_share_image']}. Use list-media, or import an image with upload-media or import-media-from-url first."
                    : "Media {$shareImage->id} is a {$shareImage->type->value}; the share image must be an image.");
            }

            $values['default_og_image'] = $shareImage === null ? null : MediaItem::fromMedia($shareImage);
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

            'default_share_image' => $schema->integer()
                ->description('Media id of the image shown when a page without its own share image is linked on social media or in chat apps. 1200×630 works best. Pass null to remove it.'),
        ];
    }
}
