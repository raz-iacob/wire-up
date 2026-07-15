<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\GetSettingsTool;
use App\Mcp\Tools\ImportMediaFromUrlTool;
use App\Mcp\Tools\ImportPexelsMediaTool;
use App\Mcp\Tools\ListMediaTool;
use App\Mcp\Tools\SearchPexelsTool;
use App\Mcp\Tools\UpdateDesignTool;
use App\Mcp\Tools\UpdateIdentityTool;
use App\Models\Media;
use App\Models\Page;
use App\Models\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function onePixelPng(): string
{
    return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
}

beforeEach(function (): void {
    Storage::fake(config()->string('filesystems.media'));
});

it('advertises the media and settings tools with their names and schemas', function (): void {
    $advertised = collect([
        ListMediaTool::class,
        ImportMediaFromUrlTool::class,
        SearchPexelsTool::class,
        ImportPexelsMediaTool::class,
        GetSettingsTool::class,
        UpdateDesignTool::class,
        UpdateIdentityTool::class,
    ])->map(fn (string $tool): array => resolve($tool)->toArray());

    expect($advertised->pluck('name')->all())->toBe([
        'list-media', 'import-media-from-url', 'search-pexels', 'import-pexels-media', 'get-settings', 'update-design', 'update-identity',
    ])
        ->and($advertised[1]['inputSchema']['required'])->toBe(['url'])
        ->and($advertised[2]['inputSchema']['required'])->toBe(['query'])
        ->and($advertised[3]['inputSchema']['required'])->toBe(['item'])
        ->and($advertised[5]['inputSchema']['properties']['theme']['enum'])->toContain('custom');
});

it('imports an image from a url into the media library', function (): void {
    Http::fake(['cdn.example.com/*' => Http::response(onePixelPng())]);

    $response = WireUpServer::tool(ImportMediaFromUrlTool::class, [
        'url' => 'https://cdn.example.com/photos/team-photo.png',
        'alt' => 'The team',
    ]);

    $response->assertOk()
        ->assertSee('"type":"image"')
        ->assertSee('The team')
        ->assertSee('team-photo');

    $media = Media::query()->latest('id')->firstOrFail();

    expect($media->type)->toBe(MediaType::IMAGE)
        ->and($media->mime_type)->toBe('image/png')
        ->and($media->width)->toBe(1)
        ->and($media->height)->toBe(1)
        ->and($media->metadata['origin_url'] ?? null)->toBe('https://cdn.example.com/photos/team-photo.png');

    Storage::disk(config()->string('filesystems.media'))->assertExists($media->source);
});

it('returns the existing media item when the same file is imported twice', function (): void {
    Http::fake(['cdn.example.com/*' => Http::response(onePixelPng())]);

    WireUpServer::tool(ImportMediaFromUrlTool::class, ['url' => 'https://cdn.example.com/a.png'])->assertOk();
    WireUpServer::tool(ImportMediaFromUrlTool::class, ['url' => 'https://cdn.example.com/b.png'])->assertOk();

    expect(Media::query()->count())->toBe(1);
});

it('imports a pdf from a url as a document', function (): void {
    Http::fake(['cdn.example.com/*' => Http::response("%PDF-1.4\n%%EOF")]);

    WireUpServer::tool(ImportMediaFromUrlTool::class, ['url' => 'https://cdn.example.com/brochure.pdf'])
        ->assertOk()
        ->assertSee('"type":"document"');

    expect(Media::query()->latest('id')->firstOrFail()->type)->toBe(MediaType::DOCUMENT);
});

it('rejects unsupported file types with a friendly error', function (): void {
    Http::fake(['cdn.example.com/*' => Http::response('<html>not an image</html>')]);

    WireUpServer::tool(ImportMediaFromUrlTool::class, ['url' => 'https://cdn.example.com/page.html'])
        ->assertHasErrors()
        ->assertSee('Unsupported file type');
});

it('rejects empty and oversized downloads', function (): void {
    Http::fake([
        'cdn.example.com/empty' => Http::response(''),
        'cdn.example.com/huge' => Http::response(str_repeat('a', 20 * 1024 * 1024 + 1)),
    ]);

    WireUpServer::tool(ImportMediaFromUrlTool::class, ['url' => 'https://cdn.example.com/empty'])
        ->assertHasErrors()
        ->assertSee('empty response');

    WireUpServer::tool(ImportMediaFromUrlTool::class, ['url' => 'https://cdn.example.com/huge'])
        ->assertHasErrors()
        ->assertSee('20 MB');
});

it('reports unreachable urls with a friendly error', function (): void {
    Http::fake(['cdn.example.com/*' => Http::response('gone', 404)]);

    WireUpServer::tool(ImportMediaFromUrlTool::class, ['url' => 'https://cdn.example.com/missing.png'])
        ->assertHasErrors()
        ->assertSee('Could not fetch');
});

it('validates the import url', function (): void {
    WireUpServer::tool(ImportMediaFromUrlTool::class, ['url' => 'not-a-url'])
        ->assertHasErrors()
        ->assertSee('valid http(s) URL');
});

it('refuses to import from internal or private addresses (SSRF guard)', function (string $url): void {
    Http::fake();

    WireUpServer::tool(ImportMediaFromUrlTool::class, ['url' => $url])
        ->assertHasErrors()
        ->assertSee('public internet addresses');

    Http::assertNothingSent();
})->with([
    'loopback' => 'http://127.0.0.1/x.png',
    'localhost name' => 'http://localhost/x.png',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
    'private range' => 'http://10.0.0.5/x.png',
    'private range 192' => 'http://192.168.1.1/x.png',
    'ipv6 loopback' => 'http://[::1]/x.png',
]);

it('blocks a redirect that points at an internal address', function (): void {
    Http::fake([
        'redirect-source.invalid/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/x.png']),
        '*' => Http::response(onePixelPng()),
    ]);

    WireUpServer::tool(ImportMediaFromUrlTool::class, ['url' => 'https://redirect-source.invalid/go'])
        ->assertHasErrors()
        ->assertSee('public internet addresses');
});

it('lists media with an optional type filter', function (): void {
    $image = Media::factory()->create(['type' => MediaType::IMAGE, 'filename' => 'hero-shot.jpg', 'thumbnail' => null, 'duration' => null]);
    Media::factory()->create(['type' => MediaType::DOCUMENT, 'filename' => 'specs.pdf', 'thumbnail' => null, 'duration' => null]);

    WireUpServer::tool(ListMediaTool::class)
        ->assertOk()
        ->assertSee('hero-shot.jpg')
        ->assertSee('specs.pdf');

    WireUpServer::tool(ListMediaTool::class, ['type' => 'image', 'limit' => 10])
        ->assertOk()
        ->assertSee('hero-shot.jpg')
        ->assertSee('"id":'.$image->id)
        ->assertDontSee('specs.pdf');

    WireUpServer::tool(ListMediaTool::class, ['type' => 'hologram'])
        ->assertHasErrors()
        ->assertSee('Type must be one of');
});

it('requires a pexels api key before searching', function (): void {
    config()->set('services.pexels.key');

    WireUpServer::tool(SearchPexelsTool::class, ['query' => 'cats'])
        ->assertHasErrors()
        ->assertSee('Pexels is not configured');
});

it('searches pexels photos and videos', function (): void {
    config()->set('services.pexels.key', 'test-key');

    Http::fake([
        'api.pexels.com/v1/*' => Http::response(['photos' => [[
            'id' => 123,
            'width' => 4000,
            'height' => 3000,
            'photographer' => 'Jane Doe',
            'alt' => 'A cat',
            'src' => ['original' => 'https://images.pexels.com/photos/123/original.jpeg'],
        ]], 'next_page' => 'https://api.pexels.com/v1/search?page=2']),
        'api.pexels.com/videos/*' => Http::response(['videos' => [], 'next_page' => null]),
    ]);

    WireUpServer::tool(SearchPexelsTool::class, ['query' => 'cats'])
        ->assertOk()
        ->assertSee('Jane Doe')
        ->assertSee('download_url')
        ->assertSee('import-pexels-media');

    WireUpServer::tool(SearchPexelsTool::class, ['query' => 'cats', 'type' => 'videos', 'page' => 2])
        ->assertOk()
        ->assertSee('"results":[]');

    WireUpServer::tool(SearchPexelsTool::class, [])
        ->assertHasErrors()
        ->assertSee('Pass a search query');
});

it('imports a pexels result into the media library', function (): void {
    Http::fake(['images.pexels.com/*' => Http::response(onePixelPng())]);

    $response = WireUpServer::tool(ImportPexelsMediaTool::class, ['item' => [
        'id' => 123,
        'type' => 'image',
        'download_url' => 'https://images.pexels.com/photos/123/original.png',
        'photographer' => 'Jane Doe',
        'extension' => 'png',
        'mime_type' => 'image/png',
        'alt' => 'A cat',
    ]]);

    $response->assertOk()->assertSee('"type":"image"');

    $media = Media::query()->latest('id')->firstOrFail();

    expect($media->metadata['source'] ?? null)->toBe('pexels')
        ->and($media->metadata['photographer'] ?? null)->toBe('Jane Doe');
});

it('rejects pexels items without a download url and reports download failures', function (): void {
    WireUpServer::tool(ImportPexelsMediaTool::class, ['item' => ['id' => 5]])
        ->assertHasErrors()
        ->assertSee('missing its download_url');

    Http::fake(['images.pexels.com/*' => Http::response('nope', 500)]);

    WireUpServer::tool(ImportPexelsMediaTool::class, ['item' => [
        'id' => 6,
        'download_url' => 'https://images.pexels.com/photos/6/original.jpg',
    ]])
        ->assertHasErrors()
        ->assertSee('Could not download');
});

it('returns identity, design, locales, and valid options from get-settings', function (): void {
    Settings::set(['title' => ['en' => 'Acme Studio'], 'description' => ['en' => 'We make things.']]);

    WireUpServer::tool(GetSettingsTool::class)
        ->assertOk()
        ->assertSee('Acme Studio')
        ->assertSee('We make things.')
        ->assertSee('"themes"')
        ->assertSee('"custom"')
        ->assertSee('color_slots')
        ->assertSee('"default":"en"');
});

it('normalizes a plain string site title in get-settings', function (): void {
    Settings::set(['title' => 'Plain Title']);

    WireUpServer::tool(GetSettingsTool::class)
        ->assertOk()
        ->assertSee('{"en":"Plain Title"}');
});

it('updates design tokens and reports the new design', function (): void {
    $response = WireUpServer::tool(UpdateDesignTool::class, [
        'theme' => array_keys(config()->array('theme.presets'))[0],
        'radius' => array_keys(config()->array('theme.radii'))[0],
        'header_sticky' => true,
        'custom_css' => '.hero { border: 0; }',
    ]);

    $response->assertOk()->assertSee('Design tokens apply site-wide immediately.');

    expect(Settings::get('radius'))->toBe(array_keys(config()->array('theme.radii'))[0])
        ->and(Settings::get('header_sticky'))->toBeTrue();
});

it('requires at least one design setting', function (): void {
    WireUpServer::tool(UpdateDesignTool::class, [])
        ->assertHasErrors()
        ->assertSee('Pass at least one design setting');
});

it('applies a full custom palette', function (): void {
    $slots = array_keys(config()->array('theme.slots'));
    $colors = array_fill_keys($slots, '#112233');

    WireUpServer::tool(UpdateDesignTool::class, ['theme' => 'custom', 'colors' => $colors])
        ->assertOk();

    expect(Settings::get('theme'))->toBe('custom')
        ->and(Settings::get('colors'))->toBe($colors);
});

it('rejects incomplete or unknown custom colors', function (): void {
    $slots = array_keys(config()->array('theme.slots'));

    WireUpServer::tool(UpdateDesignTool::class, ['theme' => 'custom', 'colors' => [$slots[0] => '#112233']])
        ->assertHasErrors()
        ->assertSee('needs a color for every slot');

    WireUpServer::tool(UpdateDesignTool::class, ['colors' => ['sparkle' => '#112233']])
        ->assertHasErrors()
        ->assertSee('Unknown color slot');

    WireUpServer::tool(UpdateDesignTool::class, ['colors' => [$slots[0] => 'blue']])
        ->assertHasErrors()
        ->assertSee('6-digit hex');
});

it('sets a preset dark mode theme and reports it', function (): void {
    WireUpServer::tool(UpdateDesignTool::class, ['theme_dark' => 'midnight'])
        ->assertOk()
        ->assertSee('"theme_dark":"midnight"');

    expect(Settings::get('theme_dark'))->toBe('midnight');

    WireUpServer::tool(UpdateDesignTool::class, ['theme_dark' => 'none'])->assertOk();

    expect(Settings::get('theme_dark'))->toBe('none');
});

it('applies a full custom dark palette and rejects an incomplete one', function (): void {
    $slots = array_keys(config()->array('theme.slots'));
    $colors = array_fill_keys($slots, '#0a0b0c');

    WireUpServer::tool(UpdateDesignTool::class, ['theme_dark' => 'custom', 'colors_dark' => $colors])
        ->assertOk();

    expect(Settings::get('theme_dark'))->toBe('custom')
        ->and(Settings::get('colors_dark'))->toBe($colors);

    Settings::set(['colors_dark' => []]);

    WireUpServer::tool(UpdateDesignTool::class, ['theme_dark' => 'custom', 'colors_dark' => [$slots[0] => '#112233']])
        ->assertHasErrors()
        ->assertSee('custom dark theme');
});

it('requires a font name when using a custom font', function (): void {
    WireUpServer::tool(UpdateDesignTool::class, ['heading_font' => 'custom'])
        ->assertHasErrors()
        ->assertSee('Set heading_font_custom');

    WireUpServer::tool(UpdateDesignTool::class, ['heading_font' => 'custom', 'heading_font_custom' => 'Space Grotesk'])
        ->assertOk();

    expect(Settings::get('heading_font_custom'))->toBe('Space Grotesk');
});

it('rejects invalid design option values', function (): void {
    WireUpServer::tool(UpdateDesignTool::class, ['radius' => 'extra-wobbly'])
        ->assertHasErrors();
});

it('sets logos from media ids', function (): void {
    $logo = Media::factory()->create(['type' => MediaType::IMAGE, 'thumbnail' => null, 'duration' => null]);

    WireUpServer::tool(UpdateDesignTool::class, ['logo_header' => $logo->id])->assertOk();

    expect(Settings::get('logo_header'))->toBe(['id' => $logo->id, 'source' => $logo->source]);

    WireUpServer::tool(UpdateDesignTool::class, ['logo_footer' => 999999])
        ->assertHasErrors()
        ->assertSee('No media with id 999999');

    $pdf = Media::factory()->create(['type' => MediaType::DOCUMENT, 'thumbnail' => null, 'duration' => null]);

    WireUpServer::tool(UpdateDesignTool::class, ['logo_header' => $pdf->id])
        ->assertHasErrors()
        ->assertSee('logos must be images');
});

it('updates the site identity preserving other locales', function (): void {
    Settings::set(['title' => ['en' => 'Old Name', 'es' => 'Nombre Viejo'], 'description' => ['en' => 'Old tagline']]);

    WireUpServer::tool(UpdateIdentityTool::class, ['title' => 'New Name', 'description' => 'New tagline'])
        ->assertOk()
        ->assertSee('New Name');

    expect(Settings::get('title'))->toBe(['en' => 'New Name', 'es' => 'Nombre Viejo'])
        ->and(Settings::get('description'))->toBe(['en' => 'New tagline']);
});

it('sets the homepage by page id', function (): void {
    $page = Page::factory()->create(['title' => 'Landing']);

    WireUpServer::tool(UpdateIdentityTool::class, ['home_page' => $page->id])->assertOk();

    expect(Settings::get('home_page_id'))->toBe($page->id);

    WireUpServer::tool(UpdateIdentityTool::class, ['home_page' => 999999])
        ->assertHasErrors()
        ->assertSee('No page with id 999999');
});

it('requires at least one identity field and validates the title length', function (): void {
    WireUpServer::tool(UpdateIdentityTool::class, [])
        ->assertHasErrors()
        ->assertSee('Pass at least one of');

    WireUpServer::tool(UpdateIdentityTool::class, ['title' => 'ab'])
        ->assertHasErrors()
        ->assertSee('at least 3 characters');
});
