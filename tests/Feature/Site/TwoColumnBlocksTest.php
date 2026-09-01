<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\Page;

/**
 * @param  array<string, mixed>  $content
 */
function twoColumnHtml(string $slug, BlockType $type, array $content): string
{
    $page = Page::factory()->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);
    $page->blocks()->create(['type' => $type, 'position' => 0, 'content' => $content]);

    return (string) test()->get('/'.$slug)->assertOk()->getContent();
}

/**
 * @param  array<string, mixed>  $content
 */
function textImageHtml(string $slug, array $content = []): string
{
    return twoColumnHtml($slug, BlockType::TEXT_IMAGE, [
        'heading' => ['en' => '<p>Our approach</p>'],
        'body' => ['en' => '<p>What we do.</p>'],
        'image' => ['source' => 'media/example.jpg', 'metadata' => ['alt' => 'Example']],
        ...$content,
    ]);
}

it('splits the two columns evenly by default', function (): void {
    expect(textImageHtml('ti-even'))->toContain('md:grid-cols-2');
});

it('widens the side that is asked for, following the image position', function (string $split, bool $reverse, string $expected): void {
    expect(textImageHtml('ti-'.$split.'-'.($reverse ? 'rev' : 'std'), [
        'columnSplit' => $split,
        'reverseLayout' => $reverse,
    ]))->toContain($expected);
})->with([
    'wider text, image left' => ['text-wide', false, 'md:grid-cols-[2fr_3fr]'],
    'wider text, image right' => ['text-wide', true, 'md:grid-cols-[3fr_2fr]'],
    'wider image, image left' => ['image-wide', false, 'md:grid-cols-[3fr_2fr]'],
    'wider image, image right' => ['image-wide', true, 'md:grid-cols-[2fr_3fr]'],
]);

it('falls back to an even split for an unknown value', function (): void {
    expect(textImageHtml('ti-bogus', ['columnSplit' => 'sideways']))->toContain('md:grid-cols-2');
});

it('keeps the image at its own shape and radius by default', function (): void {
    $html = textImageHtml('ti-plain');

    expect($html)->toContain('rounded-[calc(var(--wire-radius)*1.5)]')
        ->and($html)->not->toContain('aspect-square');
});

it('crops the image to a chosen ratio', function (string $ratio, string $expected): void {
    expect(textImageHtml('ti-ratio-'.str_replace(':', '-', $ratio), ['imageRatio' => $ratio]))->toContain($expected);
})->with([
    'square' => ['1:1', 'aspect-square'],
    'four three' => ['4:3', 'aspect-4/3'],
    'widescreen' => ['16:9', 'aspect-video'],
    'portrait' => ['3:4', 'aspect-3/4'],
]);

it('takes a chosen image radius', function (string $radius, string $expected): void {
    expect(textImageHtml('ti-radius-'.$radius, ['imageRadius' => $radius]))->toContain($expected);
})->with([
    'square' => ['none', 'rounded-none'],
    'small' => ['small', 'rounded-(--wire-radius)'],
    'round' => ['full', 'rounded-full'],
]);

it('paints the section a chosen colour when it has a background', function (): void {
    $html = textImageHtml('ti-painted', [
        'hasBackground' => true,
        'bg' => '#0f2b46',
        'textColor' => '#ffffff',
    ]);

    expect($html)->toContain('background-color:#0f2b46;color:#ffffff')
        ->and($html)->toContain('bg-(--wire-card-bg)');
});

it('ignores block colours when the background is off', function (): void {
    expect(textImageHtml('ti-unpainted', ['bg' => '#0f2b46']))->not->toContain('background-color:#0f2b46');
});

it('discards a section colour that could break out of the style attribute', function (): void {
    expect(textImageHtml('ti-guarded', [
        'hasBackground' => true,
        'bg' => 'red;background-image:url(https://evil.test/x.png)',
    ]))->not->toContain('background-image');
});

it('gives the location block the same split and section colours', function (): void {
    $html = twoColumnHtml('loc-split', BlockType::LOCATION, [
        'heading' => ['en' => '<p>Find us</p>'],
        'map' => '10 Downing Street, London',
        'columnSplit' => 'text-wide',
        'hasBackground' => true,
        'bg' => '#112233',
    ]);

    expect($html)->toContain('md:grid-cols-[3fr_2fr]')
        ->and($html)->toContain('background-color:#112233');
});

it('keeps the hero at its derived type scale and faded subheading by default', function (): void {
    $html = twoColumnHtml('hero-plain', BlockType::HERO, [
        'heading' => ['en' => '<p>Welcome</p>'],
        'subheading' => ['en' => '<p>Come in.</p>'],
    ]);

    expect($html)->toContain('text-[length:calc(var(--wire-heading-size)*1.2)]')
        ->and($html)->toContain('opacity-90');
});

it('takes exact hero type sizes and drops the derived scale', function (): void {
    $html = twoColumnHtml('hero-sized', BlockType::HERO, [
        'heading' => ['en' => '<p>Welcome</p>'],
        'subheading' => ['en' => '<p>Come in.</p>'],
        'headingSize' => 72,
        'subheadingSize' => 22,
        'dimSubheading' => false,
    ]);

    expect($html)->toContain('font-size:72px')
        ->toContain('font-size:22px')
        ->and($html)->not->toContain('text-[length:calc(var(--wire-heading-size)*1.2)]')
        ->and($html)->not->toContain('opacity-90');
});

it('gives the hero an exact height, and ignores one outside the range', function (): void {
    expect(twoColumnHtml('hero-tall', BlockType::HERO, [
        'heading' => ['en' => '<p>Welcome</p>'],
        'height' => 'custom',
        'customHeight' => 640,
    ]))->toContain('min-height:640px');

    expect(twoColumnHtml('hero-silly', BlockType::HERO, [
        'heading' => ['en' => '<p>Welcome</p>'],
        'height' => 'custom',
        'customHeight' => 99999,
    ]))->not->toContain('min-height:99999px');
});

it('keeps a hero colour alongside an exact size', function (): void {
    expect(twoColumnHtml('hero-both', BlockType::HERO, [
        'heading' => ['en' => '<p>Welcome</p>'],
        'headingColor' => '#ff8800',
        'headingSize' => 60,
    ]))->toContain('color:#ff8800;font-size:60px');
});

it('scrolls feature cards in a carousel and shows a block button', function (): void {
    $html = twoColumnHtml('fc-carousel', BlockType::FEATURE_CARDS, [
        'heading' => ['en' => '<p>What we do</p>'],
        'layout' => 'carousel',
        'items' => [['title' => ['en' => 'One']], ['title' => ['en' => 'Two']]],
        'button' => ['enabled' => true, 'text' => ['en' => 'See all'], 'link' => ['type' => 'url', 'value' => 'https://example.com/all']],
    ]);

    expect($html)->toContain('x-ref="track"')
        ->toContain('snap-x')
        ->toContain('See all')
        ->toContain('https://example.com/all');
});

it('keeps feature cards in a grid by default', function (): void {
    $html = twoColumnHtml('fc-grid', BlockType::FEATURE_CARDS, [
        'heading' => ['en' => '<p>What we do</p>'],
        'items' => [['title' => ['en' => 'One']]],
    ]);

    expect($html)->toContain('sm:grid-cols-2')->and($html)->not->toContain('x-ref="track"');
});

it('panels the contact form without painting the section', function (): void {
    $html = twoColumnHtml('cf-panel', BlockType::CONTACT_FORM, [
        'heading' => ['en' => '<p>Get in touch</p>'],
        'panelBg' => '#0f2b46',
        'textareaRows' => 12,
        'submitAlign' => 'right',
    ]);

    expect($html)->toContain('background-color:#0f2b46')
        ->toContain('rows="12"')
        ->toContain('justify-end')
        ->and($html)->not->toContain('bg-(--wire-card-bg)');
});

it('leaves the contact form unpanelled with five rows by default', function (): void {
    $html = twoColumnHtml('cf-plain', BlockType::CONTACT_FORM, ['heading' => ['en' => '<p>Get in touch</p>']]);

    expect($html)->toContain('rows="5"')->and($html)->not->toContain('sm:p-8');
});
