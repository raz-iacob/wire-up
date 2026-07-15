<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\ReadWebpageAction;
use App\Mcp\Support\Pages;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('read-webpage')]
#[Description(<<<'MD'
Read an existing website so you can recreate it in Wire-Up. Fetches the given URL and the
same-domain pages it links to (up to max_pages) and returns each page's title, meta
description, readable content (as markdown), image URLs (with alt text), and navigation
links. Typical flow: read-webpage → create-page with blocks for each page →
import-media-from-url for the images you want → update-menu from the navigation links.
MD)]
final class ReadWebpageTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'url' => ['required', 'url:http,https'],
                'max_pages' => ['sometimes', 'integer', 'min:1', 'max:20'],
            ],
            [
                'url.required' => 'Pass the URL of the site to read.',
                'url.url' => 'The URL must be a valid http(s) URL.',
            ],
        );

        try {
            $result = resolve(ReadWebpageAction::class)->handle($validated['url'], $validated['max_pages'] ?? 8);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return Response::error($invalidArgumentException->getMessage());
        }

        if ($result['pages'] === []) {
            return Response::error("Could not read {$validated['url']}. Check that it is publicly reachable and returns HTML.");
        }

        return Pages::json($result);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()
                ->description('The public http(s) URL of the site or page to read.')
                ->required(),

            'max_pages' => $schema->integer()
                ->description('Maximum number of same-domain pages to crawl (1-20). Defaults to 8.')
                ->default(8),
        ];
    }
}
