<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Ai\Contracts\RequiresConfirmation;
use App\Mcp\Support\Pages;
use App\Models\Media;
use App\Services\MediaUsageService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('delete-media')]
#[Description('Permanently delete a file from the media library, including its stored file and cached image sizes. Refused while the file is still used by a page, record or setting — the error lists what uses it, so you can clear those references first. This cannot be undone.')]
final class DeleteMediaTool extends Tool implements RequiresConfirmation
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            ['media' => ['required', 'integer']],
            ['media.required' => 'Pass the media id. Use list-media to find it.'],
        );

        $media = Media::query()->find($validated['media']);

        if ($media === null) {
            return Response::error("No media with id {$validated['media']}. Use list-media to see the library.");
        }

        $filename = $media->filename;

        if (! $media->delete()) {
            $labels = resolve(MediaUsageService::class)->labels($media);

            return Response::error("\"{$filename}\" is still in use by: ".implode(', ', $labels).'. Remove those references first, then delete it.');
        }

        return Pages::json([
            'deleted' => ['id' => $validated['media'], 'filename' => $filename],
            'hint' => 'The file and its cached sizes are gone. Any block still pointing at its source path will render without an image.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'media' => $schema->integer()
                ->required()
                ->description('The id of the media item to delete, as returned by list-media.'),
        ];
    }
}
