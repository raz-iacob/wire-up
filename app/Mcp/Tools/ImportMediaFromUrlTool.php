<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\ImportMediaFromUrlAction;
use App\Mcp\Support\MediaPayload;
use App\Mcp\Support\Pages;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('import-media-from-url')]
#[Description('Download an image (JPEG, PNG, WebP, GIF) or PDF from a public URL into the media library. Returns the media id and the source path to reference in block content. Re-importing the same file returns the existing item.')]
final class ImportMediaFromUrlTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'url' => ['required', 'url:http,https'],
                'alt' => ['sometimes', 'string', 'max:255'],
            ],
            ['url.required' => 'Pass the public URL of the file to import.', 'url.url' => 'The URL must be a valid http(s) URL.'],
        );

        try {
            $media = resolve(ImportMediaFromUrlAction::class)->handle($validated['url'], $validated['alt'] ?? '');
        } catch (InvalidArgumentException $invalidArgumentException) {
            return Response::error($invalidArgumentException->getMessage());
        } catch (ConnectionException|RequestException) {
            return Response::error("Could not fetch {$validated['url']}. Check that the URL is publicly reachable.");
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
            'url' => $schema->string()
                ->description('Public http(s) URL of the image or PDF to import (max 20 MB).')
                ->required(),

            'alt' => $schema->string()
                ->description('Alt text stored with the media item.'),
        ];
    }
}
