<?php

declare(strict_types=1);

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
        ->and($contents)->not->toBe('');
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

    expect($contents)->not->toBe('');
});

it('encodes as jpeg for an unknown format', function (): void {
    [, $mime] = ImageService::make('test-image.jpg')->applyOptions(['fm' => 'foo'])->encoded();

    expect($mime)->toBe('image/jpeg');
});

it('encodes as gif for the gif format', function (): void {
    [, $mime] = ImageService::make('test-image.jpg')->applyOptions(['fm' => 'gif'])->encoded();

    expect($mime)->toBe('image/gif');
});

it('encodes as webp for the webp format', function (): void {
    [, $mime] = ImageService::make('test-image.jpg')->applyOptions(['fm' => 'webp', 'q' => 75])->encoded();

    expect($mime)->toBe('image/webp');
});
