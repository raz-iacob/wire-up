<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

beforeEach(function (): void {
    Storage::fake();
    Storage::put('test-image.jpg', file_get_contents(__DIR__.'/../Fixtures/test-image.jpg'));
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
