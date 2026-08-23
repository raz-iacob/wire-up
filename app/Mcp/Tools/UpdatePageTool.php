<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\MediaType;
use App\Mcp\Support\Pages;
use App\Models\Media;
use App\Models\Page;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update-page')]
#[Description('Update an existing page\'s title, meta description, web address, SEO settings and layout — hiding the header or footer, a background, per-page CSS, and which sidebar menus show beside the content. Only the keys you pass are changed; blocks and publication status are left as they are — use update-page-blocks for content and publish-page for publishing.')]
final class UpdatePageTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'page' => ['required', 'integer'],
                'title' => ['nullable', 'string', 'min:3', 'max:255'],
                'description' => ['nullable', 'string', 'max:160'],
                'slug' => ['nullable', 'string', 'min:3', 'max:255', 'regex:/^[a-z0-9-]+$/'],
                'noindex' => ['boolean'],
                'og_image' => ['nullable', 'integer', 'exists:media,id'],
                'layout' => ['sometimes', 'array'],
                'layout.hideHeader' => ['boolean'],
                'layout.hideFooter' => ['boolean'],
                'layout.backgroundFixed' => ['boolean'],
                'layout.backgroundColor' => ['nullable', 'string', 'max:30'],
                'layout.backgroundImage' => ['nullable', 'integer', 'exists:media,id'],
                'layout.customCss' => ['nullable', 'string', 'max:50000'],
                'layout.sidebar' => ['sometimes', 'array'],
                'layout.sidebar.menus' => ['array', 'max:3'],
                'layout.sidebar.menus.*' => ['string', 'max:255'],
            ],
            [
                'page.required' => 'Pass the page id. Use list-pages to find it.',
                'page.integer' => 'The page id must be an integer. Use list-pages to find it.',
                'title.min' => 'The page title must be at least 3 characters.',
                'title.max' => 'The page title may not be longer than 255 characters.',
                'description.max' => 'The description may not be longer than 160 characters — it is used as the meta description.',
                'slug.min' => 'The web address must be at least 3 characters.',
                'slug.regex' => 'The web address can only use lowercase letters, numbers and hyphens.',
                'og_image.exists' => 'That media id does not exist. Use list-media or import-media-from-url first.',
                'layout.backgroundImage.exists' => 'That background media id does not exist. Use list-media, upload-media or import-media-from-url first.',
                'layout.sidebar.menus.max' => 'A page may show at most 3 sidebar menus.',
            ],
        );

        $page = Page::query()->with(['slugs', 'translations', 'media'])->find($validated['page']);

        if ($page === null) {
            return Response::error("No page with id {$validated['page']}. Use list-pages to see the available pages.");
        }

        $locale = app()->getLocale();
        $attributes = [];

        if (isset($validated['title'])) {
            $duplicate = $this->findByTitle($validated['title'], $locale, $page);

            if ($duplicate instanceof Page) {
                return Response::error("Another page is already titled \"{$validated['title']}\" (id {$duplicate->id}). Choose a different title.");
            }

            $attributes['title'] = [...$page->translationsFor('title'), $locale => $validated['title']];
        }

        if (array_key_exists('description', $validated)) {
            $attributes['description'] = [...$page->translationsFor('description'), $locale => (string) $validated['description']];
        }

        if (isset($validated['slug'])) {
            $attributes['slugs'] = [$locale => $validated['slug']];
        }

        $metadata = $page->metadata ?? [];
        $metadataChanged = false;

        if (array_key_exists('noindex', $validated)) {
            $metadata['noindex'] = (bool) $validated['noindex'];
            $metadataChanged = true;
        }

        if (array_key_exists('layout', $validated)) {
            /** @var array<string, mixed> $layoutInput */
            $layoutInput = (array) $validated['layout'];
            $background = null;

            if (($layoutInput['backgroundImage'] ?? null) !== null) {
                $media = Media::query()->findOrFail($layoutInput['backgroundImage']);

                if ($media->type !== MediaType::IMAGE) {
                    return Response::error("Media id {$media->id} is not an image, so it cannot be a page background. Use list-media to find an image.");
                }

                $background = ['source' => $media->source, 'crop' => []];
            }

            $metadata['layout'] = $this->mergedLayout($page, $layoutInput, $background);
            $metadataChanged = true;
        }

        if ($metadataChanged) {
            $attributes['metadata'] = $metadata;
        }

        if (array_key_exists('og_image', $validated)) {
            $mediaId = $validated['og_image'];
            $attributes['og_image'] = [$locale => []];

            if ($mediaId !== null) {
                $media = Media::query()->findOrFail($mediaId);

                if ($media->type !== MediaType::IMAGE) {
                    return Response::error("Media id {$mediaId} is not an image. Use list-media to find an image, or import-media-from-url to add one.");
                }

                $attributes['og_image'] = [$locale => [$this->ogImageItem($media)]];
            }
        }

        if ($attributes === []) {
            return Response::error('Pass at least one of title, description, slug, noindex, og_image or layout to change.');
        }

        Pages::update($page, $attributes);

        return Pages::json([
            'page' => Pages::meta($page->refresh()),
            'hint' => 'Blocks and publication status were not touched. Use update-page-blocks for content and publish-page to publish.',
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

            'title' => $schema->string()
                ->description('A new title. The web address is kept — pass "slug" as well to change it.'),

            'description' => $schema->string()
                ->description('A new meta description (max 160 characters). Pass an empty string to clear it.'),

            'slug' => $schema->string()
                ->description('A new web address for the page, without slashes: lowercase letters, numbers and hyphens only. A number is appended if it is already taken.'),

            'noindex' => $schema->boolean()
                ->description('Keep this page out of search engines and the sitemap.'),

            'og_image' => $schema->integer()
                ->description('Media id of the social sharing image, as returned by list-media or import-media-from-url. Pass null to remove it.'),

            'layout' => $schema->object()
                ->description('Per-page layout overrides. Only the keys you pass are changed: {"hideHeader": bool, "hideFooter": bool, "backgroundColor": "#0b1220" or null, "backgroundImage": <media id> or null, "backgroundFixed": bool, "customCss": "css applied to this page only", "sidebar": {"menus": ["<menu key>"]}}. Sidebar menu keys come from get-menus or create-menu, and render the site sidebar layout beside the page content.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $layout
     * @param  array{source: string, crop: array<string, mixed>}|null  $background
     * @return array<string, mixed>
     */
    private function mergedLayout(Page $page, array $layout, ?array $background): array
    {
        /** @var array<string, mixed> $current */
        $current = is_array($page->metadata['layout'] ?? null) ? $page->metadata['layout'] : [];

        foreach (['hideHeader', 'hideFooter', 'backgroundFixed'] as $flag) {
            if (array_key_exists($flag, $layout)) {
                $current[$flag] = (bool) $layout[$flag];
            }
        }

        if (array_key_exists('backgroundColor', $layout)) {
            $color = mb_trim((string) $layout['backgroundColor']);
            $current['backgroundColor'] = $color !== '' ? $color : null;
        }

        if (array_key_exists('backgroundImage', $layout)) {
            $current['backgroundImage'] = $background;
        }

        if (array_key_exists('customCss', $layout)) {
            $current['customCss'] = Page::sanitizeCustomCss((string) $layout['customCss']);
        }

        if (is_array($layout['sidebar'] ?? null) && array_key_exists('menus', $layout['sidebar'])) {
            $current['sidebar'] = Page::normalizeSidebar($layout['sidebar']);
        }

        return $current;
    }

    /**
     * @return array<string, mixed>
     */
    private function ogImageItem(Media $media): array
    {
        $crop = [
            'crop_w' => $media->width ?? 1200,
            'crop_h' => $media->height ?? 630,
            'crop_x' => 0,
            'crop_y' => 0,
        ];

        return ['id' => $media->id, 'crop' => ['desktop' => $crop, 'mobile' => $crop]];
    }

    private function findByTitle(string $title, string $locale, Page $except): ?Page
    {
        return Page::query()
            ->whereKeyNot($except->id)
            ->whereHas('translations', function (Builder $query) use ($title, $locale): void {
                $query->where('key', 'title')
                    ->where('locale', $locale)
                    ->where('body', $title);
            })
            ->first();
    }
}
