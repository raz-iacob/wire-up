<?php

declare(strict_types=1);

use App\Models\Settings;
use App\Services\ImageService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

beforeEach(function (): void {
    Storage::fake(config('filesystems.media'));
    Storage::disk(config('filesystems.media'))->put('test-image.jpg', file_get_contents(__DIR__.'/../Fixtures/test-image.jpg'));
    File::deleteDirectory(storage_path('framework/images'));
});

afterEach(function (): void {
    File::deleteDirectory(storage_path('framework/images'));
});

it('can grab an image from storage and apply optional formatting', function (): void {
    $response = $this->get(ImageService::url('w=50,h=50,crop=10-10-20-20,q=80,fm=webp', 'test-image.jpg'));

    $response->assertOk();

    $this->assertSame('image/webp', $response->headers->get('Content-Type'));

    $manager = new ImageManager(new GdDriver);
    $image = $manager->decodeBinary($response->getFile()->getContent());

    expect($image->width())->toBeLessThanOrEqual(50)
        ->and($image->height())->toBeLessThanOrEqual(50)
        ->and($image->origin()->mediaType())->toBe('image/webp');
});

it('rejects requests without a valid signature', function (): void {
    $this->get(route('image.show', ['options' => 'w=50', 'path' => 'test-image.jpg']))
        ->assertNotFound();

    $this->get(ImageService::url('w=50', 'test-image.jpg').'tampered')
        ->assertNotFound();
});

it('serves unsigned requests for admins so the admin previews keep working', function (): void {
    $this->actingAsAdmin();

    $this->get(route('image.show', ['options' => 'w=50', 'path' => 'test-image.jpg']))
        ->assertOk();
});

it('caches the transformed output and serves repeats from the cache', function (): void {
    $url = ImageService::url('w=50,q=80,fm=webp', 'test-image.jpg');

    expect(File::isDirectory(storage_path('framework/images')))->toBeFalse();

    $this->get($url)->assertOk();

    $cached = File::allFiles(storage_path('framework/images'));

    expect($cached)->toHaveCount(1)
        ->and($cached[0]->getExtension())->toBe('webp');

    $this->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');

    expect(File::allFiles(storage_path('framework/images')))->toHaveCount(1);
});

it('serves svg files verbatim with a locked-down content security policy', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>';
    Storage::disk(config('filesystems.media'))->put('logo.svg', $svg);

    $response = $this->get(ImageService::url('w=350,h=200', 'logo.svg'));

    $response->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('image/svg+xml')
        ->and($response->getContent())->toBe($svg)
        ->and($response->headers->get('Content-Security-Policy'))->toContain('sandbox')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('does not tag images for robots by default', function (): void {
    $this->get(ImageService::url('w=50,h=50,q=80,fm=webp', 'test-image.jpg'))
        ->assertOk()
        ->assertHeaderMissing('X-Robots-Tag');
});

it('adds the X-Robots-Tag noindex header when search engines are discouraged', function (): void {
    Settings::set(['noindex' => true]);

    $response = $this->get(ImageService::url('w=50,h=50,q=80,fm=webp', 'test-image.jpg'));

    $response->assertOk();

    expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
});

it('adds the X-Robots-Tag noindex header to svg responses when discouraged', function (): void {
    Settings::set(['noindex' => true]);
    Storage::disk(config('filesystems.media'))->put('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    $response = $this->get(ImageService::url('w=350,h=200', 'logo.svg'));

    $response->assertOk();

    expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
});

it('returns 404 for a missing svg file', function (): void {
    $this->get(ImageService::url('w=350,h=200', 'missing.svg'))
        ->assertNotFound();
});

it('rate limits transforms but never cached responses', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $cachedUrl = ImageService::url('w=40', 'test-image.jpg');
    $this->get($cachedUrl, ['REMOTE_ADDR' => '203.0.113.42'])->assertOk();

    foreach (range(41, 70) as $width) {
        $response = $this->get(ImageService::url("w={$width}", 'test-image.jpg'), ['REMOTE_ADDR' => '203.0.113.42']);

        $width < 70 ? $response->assertOk() : $response->assertTooManyRequests();
    }

    $this->get($cachedUrl, ['REMOTE_ADDR' => '203.0.113.42'])->assertOk();
});

it('serves png and gif variants with their mime types', function (): void {
    $this->get(ImageService::url('w=30,fm=png', 'test-image.jpg'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    $this->get(ImageService::url('w=30,fm=gif', 'test-image.jpg'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/gif');
});
