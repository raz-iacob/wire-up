<?php

declare(strict_types=1);

use App\Models\Block;

it('returns localized text with fallback to the default locale', function (): void {
    $block = Block::factory()->create([
        'content' => ['heading' => ['en' => 'Hello', 'fr' => 'Bonjour']],
    ]);

    expect($block->text('heading'))->toBe('Hello')
        ->and($block->text('heading', 'fr'))->toBe('Bonjour')
        ->and($block->text('heading', 'de'))->toBe('Hello')
        ->and($block->text('missing'))->toBeEmpty();
});

it('resolves a cta linked to an auth page', function (): void {
    config()->set('site.allow_registration', true);

    $block = Block::factory()->create([
        'content' => ['cta' => ['link' => ['type' => 'page', 'value' => 'auth:register']]],
    ]);

    expect($block->ctaUrl('cta'))->toBe(route('register'));
});

it('returns null for a cta linked to a disabled auth page', function (): void {
    config()->set('site.allow_registration', false);

    $block = Block::factory()->create([
        'content' => ['cta' => ['link' => ['type' => 'page', 'value' => 'auth:register']]],
    ]);

    expect($block->ctaUrl('cta'))->toBeNull();
});

it('builds an image url from the stored source', function (): void {
    $block = Block::factory()->create([
        'content' => ['image' => ['source' => 'uploads/pic.jpg', 'crop' => []]],
    ]);

    $url = $block->imageUrl('image');

    expect($url)->toContain('uploads/pic.jpg')
        ->toContain('w=1200');
});

it('requests webp for a png image so its transparency survives, and jpeg otherwise', function (string $source, string $format): void {
    $block = Block::factory()->create([
        'content' => ['image' => ['source' => $source, 'crop' => []]],
    ]);

    expect($block->imageUrl('image'))->toContain("fm={$format}");
})->with([
    'png' => ['media/logo.png', 'webp'],
    'jpeg' => ['media/photo.jpg', 'jpg'],
]);

it('uses the requested format over the one picked from the source', function (): void {
    $block = Block::factory()->create([
        'content' => ['image' => ['source' => 'media/logo.png', 'crop' => []]],
    ]);

    expect($block->imageUrl('image', ['fm' => 'png']))->toContain('fm=png')
        ->not->toContain('fm=webp');
});

it('includes a height option when one is given', function (): void {
    $block = Block::factory()->create([
        'content' => ['image' => ['source' => 'a.jpg', 'crop' => []]],
    ]);

    expect($block->imageUrl('image', ['h' => 600]))->toContain('h=600');
});

it('applies a stored crop to the image url', function (): void {
    $block = Block::factory()->create([
        'content' => ['image' => ['source' => 'a.jpg', 'crop' => [
            'default' => ['crop_w' => 800, 'crop_h' => 600, 'crop_x' => 10, 'crop_y' => 20],
        ]]],
    ]);

    expect($block->imageUrl('image'))->toContain('crop=800-600-10-20');
});

it('returns null when there is no image', function (): void {
    $block = Block::factory()->create(['content' => []]);

    expect($block->imageUrl('image'))->toBeNull();
});

it('resolves the image alt text, preferring caption then metadata then filename', function (): void {
    $withCaption = Block::factory()->create([
        'content' => ['image' => ['source' => 'a.jpg', 'alt_text' => 'fallback', 'metadata' => ['caption' => 'A caption', 'alt' => 'Preferred']]],
    ]);
    $withMeta = Block::factory()->create([
        'content' => ['image' => ['source' => 'a.jpg', 'alt_text' => 'fallback', 'metadata' => ['alt' => 'Preferred']]],
    ]);
    $withAltText = Block::factory()->create([
        'content' => ['image' => ['source' => 'a.jpg', 'alt_text' => 'Fallback alt']],
    ]);

    expect($withCaption->imageAlt('image'))->toBe('A caption')
        ->and($withMeta->imageAlt('image'))->toBe('Preferred')
        ->and($withAltText->imageAlt('image'))->toBe('Fallback alt');
});

it('returns an empty alt when there is no image', function (): void {
    $block = Block::factory()->create(['content' => []]);

    expect($block->imageAlt('image'))->toBeEmpty();
});

it('detects video items by mime type', function (): void {
    $block = Block::factory()->create([
        'content' => ['media' => [
            ['source' => 'media/clip.mp4', 'mime_type' => 'video/mp4'],
            ['source' => 'media/pic.jpg', 'mime_type' => 'image/jpeg'],
        ]],
    ]);

    expect($block->isVideo('media.0'))->toBeTrue()
        ->and($block->isVideo('media.1'))->toBeFalse();
});

it('builds a poster url for images and video thumbnails, and null otherwise', function (): void {
    $block = Block::factory()->create([
        'content' => ['media' => [
            ['source' => 'media/pic.jpg', 'mime_type' => 'image/jpeg', 'crop' => []],
            ['source' => 'media/clip.mp4', 'mime_type' => 'video/mp4', 'thumbnail' => 'media/clip-thumb.jpg'],
            ['source' => 'media/silent.mp4', 'mime_type' => 'video/mp4'],
        ]],
    ]);

    expect($block->posterUrl('media.0'))->toContain('media/pic.jpg')
        ->and($block->posterUrl('media.1'))->toContain('media/clip-thumb.jpg')
        ->and($block->posterUrl('media.2'))->toBeNull();
});

it('resolves the public file url, or null when there is no source', function (): void {
    $block = Block::factory()->create([
        'content' => ['media' => [
            ['source' => 'media/clip.mp4', 'mime_type' => 'video/mp4'],
            ['mime_type' => 'video/mp4'],
        ]],
    ]);

    expect($block->fileUrl('media.0'))->toContain('media/clip.mp4')
        ->and($block->fileUrl('media.1'))->toBeNull();
});

it('parses youtube and vimeo urls into provider and id', function (string $url, ?array $expected): void {
    expect(Block::parseVideoUrl($url))->toBe($expected);
})->with([
    'watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', ['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ']],
    'short' => ['https://youtu.be/dQw4w9WgXcQ', ['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ']],
    'shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', ['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ']],
    'watch with params' => ['https://www.youtube.com/watch?list=abc&v=dQw4w9WgXcQ&t=10', ['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ']],
    'vimeo' => ['https://vimeo.com/123456789', ['provider' => 'vimeo', 'id' => '123456789']],
    'vimeo player' => ['https://player.vimeo.com/video/123456789', ['provider' => 'vimeo', 'id' => '123456789']],
    'direct mp4' => ['https://example.com/clip.mp4', null],
    'empty' => ['', null],
]);

it('resolves an uploaded video as a native source', function (): void {
    $block = Block::factory()->create([
        'content' => ['source' => 'upload', 'video' => ['source' => 'media/clip.mp4', 'mime_type' => 'video/mp4']],
    ]);

    $embed = $block->videoEmbed();

    expect($embed['kind'])->toBe('native')
        ->and($embed['src'])->toContain('media/clip.mp4');
});

it('resolves a youtube url as an iframe embed', function (): void {
    $block = Block::factory()->create([
        'content' => ['source' => 'url', 'url' => 'https://youtu.be/dQw4w9WgXcQ'],
    ]);

    expect($block->videoEmbed())->toBe(['kind' => 'iframe', 'provider' => 'youtube', 'id' => 'dQw4w9WgXcQ']);
});

it('resolves a direct video url as a native source', function (): void {
    $block = Block::factory()->create([
        'content' => ['source' => 'url', 'url' => 'https://example.com/clip.mp4'],
    ]);

    expect($block->videoEmbed())->toBe(['kind' => 'native', 'src' => 'https://example.com/clip.mp4']);
});

it('returns null when the video block has no source', function (): void {
    expect(Block::factory()->create(['content' => ['source' => 'upload', 'video' => null]])->videoEmbed())->toBeNull()
        ->and(Block::factory()->create(['content' => ['source' => 'url', 'url' => '']])->videoEmbed())->toBeNull();
});

it('reduces a single rich text field to plain text', function (): void {
    $block = Block::factory()->create([
        'content' => ['address' => ['en' => '<p><strong>Acme HQ</strong></p><p>123 Main St<br>Springfield</p>']],
    ]);

    expect($block->plainTextField('address'))->toBe('Acme HQ 123 Main St Springfield');
});

it('falls back to the default locale and to an empty string for plain text fields', function (): void {
    $block = Block::factory()->create([
        'content' => ['address' => ['en' => '<p>123 Main St</p>']],
    ]);

    expect($block->plainTextField('address', 'fr'))->toBe('123 Main St')
        ->and($block->plainTextField('missing'))->toBeEmpty();
});

it('harvests the address into the block plain text', function (): void {
    $block = Block::factory()->create([
        'content' => [
            'heading' => ['en' => '<p>Find us</p>'],
            'address' => ['en' => '<p>123 Main St<br>Springfield</p>'],
        ],
    ]);

    expect($block->plainText('en'))->toBe('Find us 123 Main St Springfield');
});
