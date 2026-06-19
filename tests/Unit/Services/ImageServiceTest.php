<?php

declare(strict_types=1);

use App\Services\ImageService;
use Illuminate\Http\Response;
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

it('returns correct headers and content in response', function (): void {
    $service = ImageService::make('test-image.jpg')->applyOptions(['w' => 100, 'h' => 100, 'fm' => 'png', 'q' => 90]);

    $response = $service->response(3600);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->headers->get('Content-Type'))->toBe('image/png')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=3600');
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

    $response = $service->response();

    expect($response->getStatusCode())->toBe(200);
});

it('returns jpeg for unknown format in response', function (): void {
    $service = ImageService::make('test-image.jpg')->applyOptions(['fm' => 'foo']);

    $response = $service->response(3600);

    expect($response->headers->get('Content-Type'))->toBe('image/jpeg');
});

it('returns gif for gif format in response', function (): void {
    $service = ImageService::make('test-image.jpg')->applyOptions(['fm' => 'gif']);

    $response = $service->response(3600);

    expect($response->headers->get('Content-Type'))->toBe('image/gif');
});

it('returns webp for webp format in response', function (): void {
    $service = ImageService::make('test-image.jpg')->applyOptions(['fm' => 'webp', 'q' => 75]);

    $response = $service->response(3600);

    expect($response->headers->get('Content-Type'))->toBe('image/webp');
});
