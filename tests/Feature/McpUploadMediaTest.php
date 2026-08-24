<?php

declare(strict_types=1);

use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\ListImportFilesTool;
use App\Mcp\Tools\UploadMediaTool;
use App\Models\Media;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(config()->string('filesystems.media'));

    $this->importRoot = storage_path('framework/testing/import-'.bin2hex(random_bytes(5)));
    File::ensureDirectoryExists($this->importRoot);
    config()->set('wireup.media_import_path', $this->importRoot);
});

afterEach(function (): void {
    File::deleteDirectory($this->importRoot);
});

function importable(string $name, string $contents = 'x'): string
{
    $path = test()->importRoot.'/'.$name;
    File::ensureDirectoryExists(dirname($path));
    file_put_contents($path, $contents);

    return $path;
}

it('imports a file named inside the import folder', function (): void {
    importable('brand-logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect width="2" height="2"/></svg>');

    WireUpServer::tool(UploadMediaTool::class, ['path' => 'brand-logo.svg', 'alt' => 'Our logo'])
        ->assertOk()
        ->assertSee('brand-logo.svg');

    $media = Media::query()->sole();

    expect($media->mime_type)->toBe('image/svg+xml')
        ->and($media->alt_text)->toBe('Our logo');
});

it('imports a file from a nested name inside the import folder', function (): void {
    importable('brand/mark.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    WireUpServer::tool(UploadMediaTool::class, ['path' => 'brand/mark.svg'])->assertOk();

    expect(Media::query()->sole()->filename)->toBe('mark.svg');
});

it('refuses a path that escapes the import folder', function (string $path): void {
    file_put_contents(storage_path('framework/testing/outside-secret.txt'), 'secret');

    WireUpServer::tool(UploadMediaTool::class, ['path' => $path])
        ->assertHasErrors();

    expect(Media::query()->count())->toBe(0);
})->with([
    'traversal' => ['../outside-secret.txt'],
    'absolute outside' => ['/etc/hosts'],
]);

it('reports the import folder when the file is missing', function (): void {
    WireUpServer::tool(UploadMediaTool::class, ['path' => 'nope.png'])
        ->assertHasErrors()
        ->assertSee('No importable file at "nope.png"')
        ->assertSee($this->importRoot);
});

it('requires a path', function (): void {
    WireUpServer::tool(UploadMediaTool::class, [])
        ->assertHasErrors(['Pass the name of a file inside the import folder.']);
});

it('returns the existing item when the same file is imported twice', function (): void {
    importable('twice.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    WireUpServer::tool(UploadMediaTool::class, ['path' => 'twice.svg'])->assertOk();
    WireUpServer::tool(UploadMediaTool::class, ['path' => 'twice.svg'])->assertOk();

    expect(Media::query()->count())->toBe(1);
});

it('surfaces a conversion failure as a tool error', function (): void {
    importable('broken.heic', 'not an image');

    WireUpServer::tool(UploadMediaTool::class, ['path' => 'broken.heic'])
        ->assertHasErrors(['The image could not be converted. Upload it as a JPG or PNG instead.']);
});

it('advertises upload-media with its schema', function (): void {
    $tool = resolve(UploadMediaTool::class)->toArray();

    expect($tool['name'])->toBe('upload-media')
        ->and($tool['inputSchema']['required'])->toBe(['path'])
        ->and($tool['inputSchema']['properties'])->toHaveKeys(['path', 'alt']);
});

it('lists the files waiting in the import folder, nested ones included', function (): void {
    importable('logo.svg');
    importable('brand/hero.png');
    importable('.hidden-file');

    WireUpServer::tool(ListImportFilesTool::class, [])
        ->assertOk()
        ->assertSee('logo.svg')
        ->assertSee('brand/hero.png')
        ->assertDontSee('hidden-file');
});

it('filters the import listing by extension with or without a leading dot', function (string $extension): void {
    importable('logo.svg');
    importable('photo.png');

    WireUpServer::tool(ListImportFilesTool::class, ['extension' => $extension])
        ->assertOk()
        ->assertSee('photo.png')
        ->assertDontSee('logo.svg');
})->with(['png', '.png', 'PNG']);

it('says the import folder is empty rather than returning a bare list', function (): void {
    WireUpServer::tool(ListImportFilesTool::class, [])
        ->assertOk()
        ->assertSee('The import folder is empty');
});

it('caps a very large import folder and says how many it left out', function (): void {
    foreach (range(1, 205) as $index) {
        importable(sprintf('file-%03d.txt', $index));
    }

    WireUpServer::tool(ListImportFilesTool::class, [])
        ->assertOk()
        ->assertSee('Showing the first 200 of 205 files')
        ->assertSee('file-001.txt')
        ->assertDontSee('file-205.txt');
});

it('advertises the optional extension filter in its schema', function (): void {
    $schema = (new ListImportFilesTool)->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('extension');
});
