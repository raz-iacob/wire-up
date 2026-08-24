<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Page;
use App\Models\Record;
use App\Services\PageScreenshot;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use RuntimeException;

#[Name('render-page')]
#[Description('Screenshot a page, record or site path in a headless browser so you can see how it actually looks. Pass one of page, record or path. Drafts are rendered too, through a short-lived signed preview link. Requires a headless browser on the server; the error explains how to install one if it is missing.')]
final class RenderPageTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'page' => ['sometimes', 'integer'],
                'record' => ['sometimes', 'integer'],
                'path' => ['sometimes', 'string', 'max:255', 'regex:#^/#'],
                'viewport' => ['sometimes', 'string', 'in:desktop,tablet,mobile'],
                'full_page' => ['sometimes', 'boolean'],
            ],
            [
                'path.regex' => 'The path must start with a slash, e.g. "/about".',
                'viewport.in' => 'Viewport must be "desktop", "tablet" or "mobile".',
            ],
        );

        try {
            $png = resolve(PageScreenshot::class)->capture(
                $this->resolveUrl($validated),
                $validated['viewport'] ?? 'desktop',
                (bool) ($validated['full_page'] ?? false),
            );
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::image($png);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()
                ->description('Page id to render, as returned by list-pages. A draft is rendered through a short-lived preview link.'),
            'record' => $schema->integer()
                ->description('Record id to render, as returned by list-records. A draft is rendered through a short-lived preview link.'),
            'path' => $schema->string()
                ->description('A site-relative path to render instead, starting with a slash, e.g. "/" or "/guides".'),
            'viewport' => $schema->string()
                ->enum(['desktop', 'tablet', 'mobile'])
                ->description('Screen size to render at. Defaults to desktop.'),
            'full_page' => $schema->boolean()
                ->description('Capture the entire scrollable page instead of just the visible area.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveUrl(array $validated): string
    {
        $given = array_filter(
            ['page', 'record', 'path'],
            fn (string $key): bool => isset($validated[$key]),
        );

        throw_if(count($given) !== 1, InvalidArgumentException::class, 'Pass exactly one of "page", "record" or "path" to render.');

        if (isset($validated['path'])) {
            return url($validated['path']);
        }

        if (isset($validated['page'])) {
            $page = Page::query()->with('slugs')->find($validated['page']);

            throw_if($page === null, InvalidArgumentException::class, "No page with id {$validated['page']}. Use list-pages to see the available pages.");

            throw_if($page->getSlug() === '', InvalidArgumentException::class, "Page {$page->id} has no web address yet, so there is nothing to render. Give it a title and save it first.");

            return $page->isLiveInLocale() ? $page->getUrl() : $page->previewUrl();
        }

        $record = Record::query()->with(['recordType', 'slugs'])->find($validated['record']);

        throw_if($record === null, InvalidArgumentException::class, "No record with id {$validated['record']}. Use list-records to see the available records.");

        throw_if($record->getSlug() === '', InvalidArgumentException::class, "Record {$record->id} has no web address yet, so there is nothing to render. Give it a title and save it first.");

        return $record->isLiveInLocale() ? $record->getUrl() : $record->previewUrl();
    }
}
