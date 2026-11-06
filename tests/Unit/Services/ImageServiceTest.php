<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Models\Media;
use App\Services\ImageService;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(config('filesystems.media'));
    Storage::disk(config('filesystems.media'))->put('test-image.jpg', file_get_contents(__DIR__.'/../../Fixtures/test-image.jpg'));
});

it('creates instance and sets source file', function (): void {
    $service = ImageService::make('test-image.jpg');

    expect($service)->toBeInstanceOf(ImageService::class);
});

it('returns base64 image as placeholder', function (): void {
    $placeholder = ImageService::placeholder();

    expect($placeholder)->toStartWith('data:image/png;base64,');
});

it('throws 404 for missing file in setSourceFile', function (): void {
    Storage::fake();

    expect(fn (): ImageService => ImageService::make('missing.jpg'))->toThrow(Exception::class);
});

it('throws 404 when the source file is not a readable image', function (): void {
    Storage::disk(config('filesystems.media'))->put('not-an-image.jpg', 'this is plain text, not an image');

    expect(fn (): ImageService => ImageService::make('not-an-image.jpg'))->toThrow(Exception::class);
});

it('parses and applies options from string', function (): void {
    $service = ImageService::make('test-image.jpg')->applyOptionsString('w=100,h=100,crop=10-10-20-20,q=80,fm=webp');

    expect($service)->toBeInstanceOf(ImageService::class);
});

it('parses crop string correctly', function (): void {
    $service = ImageService::make('test-image.jpg');

    $crop = $service->parseCrop('100,200,10,20');

    expect($crop)->toEqual([
        'width' => 100,
        'height' => 200,
        'offset_x' => 10,
        'offset_y' => 20,
    ]);
});

it('applies crop and scale options', function (): void {
    $service = ImageService::make('test-image.jpg');

    $service->applyOptions([
        'w' => 100,
        'h' => 100,
        'crop' => '10,10,20,20',
    ]);

    expect($service)->toBeInstanceOf(ImageService::class);
});

it('returns the encoded image bytes with their mime type', function (): void {
    [$contents, $mime] = ImageService::make('test-image.jpg')
        ->applyOptions(['w' => 100, 'h' => 100, 'fm' => 'png', 'q' => 90])
        ->encoded();

    expect($mime)->toBe('image/png')
        ->and($contents)->not->toBeEmpty();
});

it('handles invalid option string in applyOptionsString', function (): void {
    $service = ImageService::make('test-image.jpg')->applyOptionsString('invalidoption');

    expect($service)->toBeInstanceOf(ImageService::class);
});

it('handles parseCrop when given invalid input', function (string $input): void {
    $service = ImageService::make('test-image.jpg');
    $result = $service->parseCrop($input);

    expect($result)->toBeNull();
})->with([
    'invalid string',
    '-2,0,0,0',
    '100,200,300',
    '0,100,0,0',
]);

it('accepts crop dimensions larger than the output cap', function (): void {
    $service = ImageService::make('test-image.jpg');

    $crop = $service->parseCrop('5059-3372-255-56');

    expect($crop)->toEqual([
        'width' => 5059,
        'height' => 3372,
        'offset_x' => 255,
        'offset_y' => 56,
    ]);
});

it('clamps an oversized crop to the image bounds without distortion', function (): void {
    $service = ImageService::make('test-image.jpg')
        ->applyOptions(['crop' => '5059-3372-255-56']);

    [$contents] = $service->encoded();

    expect($contents)->not->toBeEmpty();
});

it('falls back to the format picked from the source when the requested one is unknown', function (): void {
    [, $mime] = ImageService::make('test-image.jpg')->applyOptions(['fm' => 'foo'])->encoded();

    expect($mime)->toBe('image/webp');
});

it('encodes as gif for the gif format', function (): void {
    [, $mime] = ImageService::make('test-image.jpg')->applyOptions(['fm' => 'gif'])->encoded();

    expect($mime)->toBe('image/gif');
});

it('encodes as webp for the webp format', function (): void {
    [, $mime] = ImageService::make('test-image.jpg')->applyOptions(['fm' => 'webp', 'q' => 75])->encoded();

    expect($mime)->toBe('image/webp');
});

it('picks webp for photos and transparent sources and jpeg for anything else', function (string $path, string $expected): void {
    expect(ImageService::formatFor($path))->toBe($expected);
})->with([
    'png' => ['media/logo.png', 'webp'],
    'uppercase png' => ['media/LOGO.PNG', 'webp'],
    'webp' => ['media/cutout.webp', 'webp'],
    'jpeg' => ['media/photo.jpg', 'webp'],
    'jpeg long extension' => ['media/photo.jpeg', 'webp'],
    'gif' => ['media/animation.gif', 'jpg'],
    'no extension' => ['media/photo', 'jpg'],
]);

it('keeps the transparency of a png source when no format is requested', function (): void {
    Storage::disk(config('filesystems.media'))->put('transparent.png', file_get_contents(__DIR__.'/../../Fixtures/transparent.png'));

    [$contents, $mime] = ImageService::make('transparent.png')->applyOptions(['w' => 20])->encoded();

    $image = imagecreatefromstring($contents);
    expect($mime)->toBe('image/webp')
        ->and(imagecolorsforindex($image, imagecolorat($image, 0, 0))['alpha'])->toBe(127)
        ->and(imagecolorsforindex($image, imagecolorat($image, 10, 10))['alpha'])->toBe(0);
});

it('still encodes a png source as jpeg when jpeg is requested', function (): void {
    Storage::disk(config('filesystems.media'))->put('transparent.png', file_get_contents(__DIR__.'/../../Fixtures/transparent.png'));

    [, $mime] = ImageService::make('transparent.png')->applyOptions(['fm' => 'jpg'])->encoded();

    expect($mime)->toBe('image/jpeg');
});

it('lists each srcset width once, smallest first', function (): void {
    expect(ImageService::srcset([800, 400, 800], fn (int $width): string => "img-{$width}"))->toBe('img-400 400w, img-800 800w');
});

it('stops a srcset at the width of the image itself', function (): void {
    expect(ImageService::srcset([400, 800, 1200], fn (int $width): string => "img-{$width}", 700))->toBe('img-400 400w, img-700 700w')
        ->and(ImageService::srcset([400, 800], fn (int $width): string => "img-{$width}", 2000))->toBe('img-400 400w, img-800 800w');
});

it('builds a srcset for a library image, stopping at its original width', function (): void {
    $media = Media::factory()->create(['type' => MediaType::IMAGE, 'source' => 'media/pic.jpg', 'width' => 1000]);

    expect(ImageService::mediaSrcset($media, [480, 800, 1600]))
        ->toContain('480w')
        ->toContain('1000w')
        ->not->toContain('1600w');
});
