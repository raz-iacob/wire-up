<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\StoreMediaFileAction;
use App\Mcp\Support\MediaPayload;
use App\Mcp\Support\Pages;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Name('upload-media')]
#[Description('Import a file that is already on the server into the media library — images (including SVG and HEIC), video, audio and documents. Pass a file name inside the server import folder; the error message names the folder if you get the path wrong. Returns the media id and source path. Importing the same file twice returns the existing item.')]
final class UploadMediaTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'path' => ['required', 'string', 'max:1024'],
                'alt' => ['sometimes', 'string', 'max:255'],
            ],
            ['path.required' => 'Pass the name of a file inside the import folder.'],
        );

        $root = $this->importRoot();
        $resolved = $this->resolveWithin($validated['path'], $root);

        if ($resolved === null) {
            return Response::error("No importable file at \"{$validated['path']}\". Files must sit inside {$root} — put the file there, then pass its name.");
        }

        try {
            $media = resolve(StoreMediaFileAction::class)->handle($resolved, basename($resolved), [
                'alt_text' => $validated['alt'] ?? null,
                'metadata' => ['source' => 'import'],
            ]);
        } catch (Throwable $throwable) {
            return Response::error($throwable->getMessage());
        }

        return Pages::json([
            'media' => MediaPayload::summary($media),
            'hint' => 'Reference it in block content as {"source": "<source>", "metadata": {"alt": "..."}}; use the id for settings such as logos and the favicon.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()
                ->required()
                ->description('File name inside the server import folder, e.g. "logo.svg". A nested name like "brand/logo.svg" works; paths outside the folder are refused.'),
            'alt' => $schema->string()
                ->description('Alt text describing the file. Defaults to the file name.'),
        ];
    }

    private function importRoot(): string
    {
        $root = config()->string('wireup.media_import_path');

        File::ensureDirectoryExists($root);

        return (string) realpath($root);
    }

    private function resolveWithin(string $path, string $root): ?string
    {
        $candidate = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : $root.DIRECTORY_SEPARATOR.$path;
        $resolved = realpath($candidate);

        if ($resolved === false || ! is_file($resolved)) {
            return null;
        }

        return str_starts_with($resolved, $root.DIRECTORY_SEPARATOR) ? $resolved : null;
    }
}
