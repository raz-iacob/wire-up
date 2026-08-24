<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\Pages;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Number;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use SplFileInfo;

#[Name('list-import-files')]
#[Description('List the files sitting in the server import folder, ready to be brought into the media library with upload-media. Use this first instead of guessing a file name. Nested folders are included.')]
final class ListImportFilesTool extends Tool
{
    private const int MAX_FILES = 200;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'extension' => ['sometimes', 'string', 'max:20'],
        ]);

        $root = $this->importRoot();
        $extension = isset($validated['extension'])
            ? mb_strtolower(mb_ltrim($validated['extension'], '.'))
            : null;

        $files = collect(File::allFiles($root))
            ->reject(fn (SplFileInfo $file): bool => str_starts_with($file->getFilename(), '.'))
            ->filter(fn (SplFileInfo $file): bool => $extension === null || mb_strtolower($file->getExtension()) === $extension)
            ->sortBy(fn (SplFileInfo $file): string => $this->relativePath($file, $root))
            ->values();

        $total = $files->count();

        $listed = $files->take(self::MAX_FILES)->map(fn (SplFileInfo $file): array => [
            'path' => $this->relativePath($file, $root),
            'size' => Number::fileSize($file->getSize(), precision: 1),
            'bytes' => $file->getSize(),
        ])->all();

        return Pages::json([
            'folder' => $root,
            'total' => $total,
            'files' => $listed,
            ...($total > self::MAX_FILES
                ? ['truncated' => 'Showing the first '.self::MAX_FILES." of {$total} files. Filter by extension to narrow the list."]
                : []),
            'hint' => $total === 0
                ? 'The import folder is empty. Put files there, or use import-media-from-url or import-pexels-media instead.'
                : 'Pass a "path" from this list to upload-media to bring one into the media library.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'extension' => $schema->string()
                ->description('Only list files with this extension, e.g. "png". The leading dot is optional.'),
        ];
    }

    private function importRoot(): string
    {
        $root = config()->string('wireup.media_import_path');

        File::ensureDirectoryExists($root);

        return (string) realpath($root);
    }

    private function relativePath(SplFileInfo $file, string $root): string
    {
        return mb_ltrim(str_replace($root, '', $file->getPathname()), DIRECTORY_SEPARATOR);
    }
}
