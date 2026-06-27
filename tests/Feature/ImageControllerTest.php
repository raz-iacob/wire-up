<?php

declare(strict_types=1);

use App\Models\Settings;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

beforeEach(function (): void {
    Storage::fake(config('filesystems.media'));
    Storage::disk(config('filesystems.media'))->put('test-image.jpg', file_get_contents(__DIR__.'/../Fixtures/test-image.jpg'));
});

it('can grab an image from storage and apply optional formatting', function (): void {

    $response = $this->get(route('image.show', [
        'options' => 'w=50,h=50,crop=10-10-20-20,q=80,fm=webp',
        'path' => 'test-image.jpg',
    ]));

    $response->assertOk();

    $this->assertSame('image/webp', $response->headers->get('Content-Type'));

    $manager = new ImageManager(new GdDriver);
    $image = $manager->read($response->getContent());

    expect($image->width())->toBeLessThanOrEqual(50)
        ->and($image->height())->toBeLessThanOrEqual(50)
        ->and($image->origin()->mediaType())->toBe('image/webp');
});

it('serves svg files verbatim with a locked-down content security policy', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>';
    Storage::disk(config('filesystems.media'))->put('logo.svg', $svg);

    $response = $this->get(route('image.show', [
        'options' => 'w=350,h=200',
        'path' => 'logo.svg',
    ]));

    $response->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('image/svg+xml')
        ->and($response->getContent())->toBe($svg)
        ->and($response->headers->get('Content-Security-Policy'))->toContain('sandbox')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('does not tag images for robots by default', function (): void {
    $this->get(route('image.show', [
        'options' => 'w=50,h=50,q=80,fm=webp',
        'path' => 'test-image.jpg',
    ]))
        ->assertOk()
        ->assertHeaderMissing('X-Robots-Tag');
});

it('adds the X-Robots-Tag noindex header when search engines are discouraged', function (): void {
    Settings::set(['noindex' => true]);

    $response = $this->get(route('image.show', [
        'options' => 'w=50,h=50,q=80,fm=webp',
        'path' => 'test-image.jpg',
    ]));

    $response->assertOk();

    expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
});

it('adds the X-Robots-Tag noindex header to svg responses when discouraged', function (): void {
    Settings::set(['noindex' => true]);
    Storage::disk(config('filesystems.media'))->put('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

    $response = $this->get(route('image.show', [
        'options' => 'w=350,h=200',
        'path' => 'logo.svg',
    ]));

    $response->assertOk();

    expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
});

it('returns 404 for a missing svg file', function (): void {
    $this->get(route('image.show', [
        'options' => 'w=350,h=200',
        'path' => 'missing.svg',
    ]))->assertNotFound();
});

it('limits access to the image endpoint', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    foreach (range(1, 5) as $i) {
        $response = $this->get(route('image.show', [
            'options' => 'w=100,h=200,crop=10-10-20-20,q=80,fm=webp',
            'path' => 'test-image.jpg',
        ]), ['REMOTE_ADDR' => '203.0.113.42']);

        if ($i < 3) {
            $response->assertOk();
        } else {
            $response->assertNotFound();
        }
    }
});
