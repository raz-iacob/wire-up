<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\Pages;
use App\Services\PexelsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('search-pexels')]
#[Description('Search the Pexels stock library for photos or videos. Pass a result object verbatim to import-pexels-media to add it to the media library.')]
final class SearchPexelsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $pexels = resolve(PexelsService::class);

        if (! $pexels->configured()) {
            return Response::error('Pexels is not configured. Add a Pexels API key under Settings → Integrations in the admin first.');
        }

        $validated = $request->validate(
            [
                'query' => ['required', 'string', 'max:100'],
                'type' => ['sometimes', 'string', 'in:photos,videos'],
                'page' => ['sometimes', 'integer', 'min:1'],
            ],
            ['query.required' => 'Pass a search query, e.g. "mountain landscape".', 'type.in' => 'Type must be "photos" or "videos".'],
        );

        $page = (int) ($validated['page'] ?? 1);

        $results = ($validated['type'] ?? 'photos') === 'videos'
            ? $pexels->searchVideos($validated['query'], $page, 12)
            : $pexels->searchPhotos($validated['query'], $page, 12);

        return Pages::json([
            ...$results,
            'hint' => 'Pass one result object verbatim as the "item" argument of import-pexels-media.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('What to search for, e.g. "mountain landscape".')
                ->required(),

            'type' => $schema->string()
                ->enum(['photos', 'videos'])
                ->description('Search for photos or videos.')
                ->default('photos'),

            'page' => $schema->integer()
                ->description('Results page, starting at 1.')
                ->default(1),
        ];
    }
}
