<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\PageStatus;
use App\Models\Block;
use App\Models\Page;
use App\Services\BlockSchema;

/**
 * @param  array<string, mixed>  $content
 */
function schemaBlock(string $type, array $content): Block
{
    $page = Page::factory()->create([
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);

    return $page->blocks()->create(['type' => $type, 'position' => 0, 'content' => $content]);
}

function schemaBuilder(?string $fallbackImage = 'https://example.test/share.jpg'): BlockSchema
{
    return new BlockSchema('Page Name', 'https://example.test#organization', $fallbackImage);
}

it('builds a LocalBusiness node from a location block', function (): void {
    $block = schemaBlock(BlockType::LOCATION->value, [
        'name' => ['en' => 'Acme Clinic'],
        'address' => ['en' => "123 Main St\nSpringfield"],
        'phone' => '+1 555 0100',
        'email' => 'hi@acme.test',
        'map' => 'https://maps.example/x',
    ]);

    $nodes = schemaBuilder()->nodes($block, 'en');

    expect($nodes)->toHaveCount(1)
        ->and($nodes[0])->toMatchArray([
            '@type' => 'LocalBusiness',
            'name' => 'Acme Clinic',
            'telephone' => '+1 555 0100',
            'email' => 'hi@acme.test',
            'hasMap' => 'https://maps.example/x',
        ])
        ->and($nodes[0]['address'])->toContain('123 Main St');
});

it('skips a location block with no contact details', function (): void {
    $block = schemaBlock(BlockType::LOCATION->value, ['name' => ['en' => 'Acme']]);

    expect(schemaBuilder()->nodes($block, 'en'))->toBe([]);
});

it('builds Person nodes from a team block and skips nameless members', function (): void {
    $block = schemaBlock(BlockType::TEAM->value, ['items' => [
        ['id' => 'a', 'name' => ['en' => 'Jane Doe'], 'role' => ['en' => 'CEO'], 'bio' => ['en' => 'Leads the team.'], 'photo' => ['source' => 'uploads/jane.jpg'], 'socials' => ['linkedin' => 'https://linkedin.com/in/jane', 'email' => 'jane@acme.test', 'website' => '', 'x' => '', 'instagram' => '']],
        ['id' => 'b', 'name' => ['en' => ''], 'socials' => []],
        'not-an-array',
    ]]);

    $nodes = schemaBuilder()->nodes($block, 'en');

    expect($nodes)->toHaveCount(1)
        ->and($nodes[0])->toMatchArray([
            '@type' => 'Person',
            'name' => 'Jane Doe',
            'jobTitle' => 'CEO',
            'description' => 'Leads the team.',
            'email' => 'jane@acme.test',
        ])
        ->and($nodes[0]['image'])->toContain('jane.jpg')
        ->and($nodes[0]['sameAs'])->toBe(['https://linkedin.com/in/jane'])
        ->and($nodes[0]['worksFor'])->toBe(['@id' => 'https://example.test#organization']);
});

it('builds Offer nodes from a pricing block', function (): void {
    $block = schemaBlock(BlockType::PRICING->value, ['items' => [
        ['id' => 'a', 'name' => ['en' => 'Pro'], 'description' => ['en' => 'Best value'], 'price' => ['en' => '$99']],
        ['id' => 'b', 'name' => ['en' => 'Starter'], 'price' => ['en' => 'Free']],
        ['id' => 'c', 'name' => ['en' => ''], 'price' => ['en' => '$5']],
    ]]);

    $nodes = schemaBuilder()->nodes($block, 'en');

    expect($nodes)->toHaveCount(2)
        ->and($nodes[0])->toMatchArray(['@type' => 'Offer', 'name' => 'Pro', 'description' => 'Best value', 'price' => '99', 'priceCurrency' => 'USD'])
        ->and($nodes[1])->toMatchArray(['@type' => 'Offer', 'name' => 'Starter'])
        ->and($nodes[1])->not->toHaveKey('price');
});

it('parses prices best-effort', function (string $raw, ?string $price, ?string $currency): void {
    $block = schemaBlock(BlockType::PRICING->value, ['items' => [['id' => 'a', 'name' => ['en' => 'Plan'], 'price' => ['en' => $raw]]]]);

    $offer = schemaBuilder()->nodes($block, 'en')[0];

    if ($price === null) {
        expect($offer)->not->toHaveKey('price');
    } else {
        expect($offer['price'])->toBe($price)
            ->and($offer['priceCurrency'])->toBe($currency);
    }
})->with([
    'dollar' => ['$99', '99', 'USD'],
    'euro with thousands' => ['1,299.00 €', '1299.00', 'EUR'],
    'pound zero' => ['£0', '0', 'GBP'],
    'free' => ['Free', null, null],
    'no symbol' => ['99', null, null],
    'symbol without number' => ['$', null, null],
]);

it('returns no nodes when a block items field is not an array', function (): void {
    $block = schemaBlock(BlockType::PRICING->value, ['items' => 'nope']);

    expect(schemaBuilder()->nodes($block, 'en'))->toBe([]);
});

it('builds a VideoObject for an external video, falling back to the default share image', function (): void {
    $block = schemaBlock(BlockType::VIDEO->value, [
        'source' => 'url',
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'heading' => ['en' => 'Our story'],
        'intro' => ['en' => 'Watch this.'],
    ]);

    $node = schemaBuilder()->nodes($block, 'en')[0];

    expect($node)->toMatchArray([
        '@type' => 'VideoObject',
        'name' => 'Our story',
        'description' => 'Watch this.',
        'thumbnailUrl' => 'https://example.test/share.jpg',
        'embedUrl' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
    ])
        ->and($node['uploadDate'])->not->toBeEmpty();
});

it('builds a VideoObject for an uploaded video with a poster thumbnail', function (): void {
    $block = schemaBlock(BlockType::VIDEO->value, [
        'source' => 'upload',
        'video' => ['source' => 'uploads/clip.mp4', 'mime_type' => 'video/mp4'],
        'poster' => ['source' => 'uploads/poster.jpg', 'crop' => ['default' => ['crop_w' => 1280, 'crop_h' => 720, 'crop_x' => 0, 'crop_y' => 0]]],
        'heading' => ['en' => 'Promo'],
    ]);

    $node = schemaBuilder()->nodes($block, 'en')[0];

    expect($node['@type'])->toBe('VideoObject')
        ->and($node['contentUrl'])->toContain('clip.mp4')
        ->and($node['thumbnailUrl'])->toContain('poster.jpg');
});

it('builds a VideoObject with a Vimeo embed url', function (): void {
    $block = schemaBlock(BlockType::VIDEO->value, ['source' => 'url', 'url' => 'https://vimeo.com/123456789', 'heading' => ['en' => 'Reel']]);

    $node = schemaBuilder()->nodes($block, 'en')[0];

    expect($node['@type'])->toBe('VideoObject')
        ->and($node['embedUrl'])->toBe('https://player.vimeo.com/video/123456789');
});

it('skips a video with no resolvable thumbnail', function (): void {
    $block = schemaBlock(BlockType::VIDEO->value, ['source' => 'url', 'url' => 'https://youtu.be/abcDEFghij1']);

    expect(schemaBuilder(null)->nodes($block, 'en'))->toBe([]);
});

it('skips a video block with no video source', function (): void {
    $block = schemaBlock(BlockType::VIDEO->value, ['source' => 'upload', 'heading' => ['en' => 'Empty']]);

    expect(schemaBuilder()->nodes($block, 'en'))->toBe([]);
});

it('builds an AudioObject from an audio block', function (): void {
    $block = schemaBlock(BlockType::AUDIO->value, ['audio' => ['source' => 'uploads/song.mp3', 'mime_type' => 'audio/mpeg'], 'heading' => ['en' => 'Listen'], 'intro' => ['en' => 'A great track.']]);

    $node = schemaBuilder()->nodes($block, 'en')[0];

    expect($node['@type'])->toBe('AudioObject')
        ->and($node['name'])->toBe('Listen')
        ->and($node['description'])->toBe('A great track.')
        ->and($node['contentUrl'])->toContain('song.mp3');
});

it('skips an audio block with no file', function (): void {
    $block = schemaBlock(BlockType::AUDIO->value, ['heading' => ['en' => 'Listen']]);

    expect(schemaBuilder()->nodes($block, 'en'))->toBe([]);
});

it('builds ImageObject nodes from a gallery block and skips video items', function (): void {
    $block = schemaBlock(BlockType::GALLERY->value, ['media' => [
        ['source' => 'uploads/a.jpg', 'mime_type' => 'image/jpeg', 'crop' => ['default' => ['crop_w' => 1600, 'crop_h' => 900, 'crop_x' => 0, 'crop_y' => 0]], 'metadata' => ['caption' => 'Sunset'], 'alt_text' => 'A sunset'],
        ['source' => 'uploads/clip.mp4', 'mime_type' => 'video/mp4'],
        ['mime_type' => 'image/jpeg'],
    ]]);

    $nodes = schemaBuilder()->nodes($block, 'en');

    expect($nodes)->toHaveCount(1)
        ->and($nodes[0]['@type'])->toBe('ImageObject')
        ->and($nodes[0]['contentUrl'])->toContain('a.jpg')
        ->and($nodes[0]['caption'])->toBe('Sunset');
});

it('emits nothing for excluded block types', function (): void {
    foreach ([BlockType::ACCORDION, BlockType::RICH_TEXT, BlockType::TESTIMONIALS, BlockType::FEATURE_CARDS] as $type) {
        $block = schemaBlock($type->value, ['heading' => ['en' => 'Heading'], 'body' => ['en' => 'Body']]);

        expect(schemaBuilder()->nodes($block, 'en'))->toBe([]);
    }
});
