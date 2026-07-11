<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\ImportPexelsMediaAction;
use App\Mcp\Support\MediaPayload;
use App\Mcp\Support\Pages;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('import-pexels-media')]
#[Description('Import a search-pexels result into the media library. Photographer attribution is stored with the item. Importing the same result twice returns the existing item.')]
final class ImportPexelsMediaTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            ['item' => ['required', 'array']],
            [
                'item.required' => 'Pass a result object from search-pexels as "item".',
                'item.array' => 'The item must be a result object from search-pexels, passed verbatim.',
            ],
        );

        if (blank($validated['item']['download_url'] ?? null)) {
            return Response::error('The item is missing its download_url. Pass a result object from search-pexels verbatim.');
        }

        try {
            $media = resolve(ImportPexelsMediaAction::class)->handle($validated['item']);
        } catch (ConnectionException|RequestException) {
            return Response::error('Could not download the file from Pexels. Try again or pick another result.');
        }

        return Pages::json([
            'media' => MediaPayload::summary($media),
            'hint' => 'Reference it in block content as {"source": "<source>", "metadata": {"alt": "..."}}; use the id for settings such as logos.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'item' => $schema->object()
                ->description('A single result object from search-pexels, passed verbatim.')
                ->required(),
        ];
    }
}
