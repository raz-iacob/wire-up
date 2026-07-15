<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Settings;

function publishMarkdownPage(string $slug, array $blocks = [], array $attributes = []): Page
{
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => ucfirst($slug),
        ...$attributes,
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);
    $page->updateBlocks($blocks);

    return $page;
}

function markdownRecordType(): RecordType
{
    return RecordType::factory()->create([
        'key' => 'product',
        'slug_prefix' => 'products',
        'fields' => [
            ['key' => 'heading', 'type' => 'text', 'label' => ['en' => 'Title'], 'translatable' => true],
            ['key' => 'overview', 'type' => 'rich-text', 'label' => ['en' => 'Overview'], 'translatable' => true],
            ['key' => 'price', 'type' => 'money', 'label' => ['en' => 'Price'], 'translatable' => false],
            ['key' => 'released', 'type' => 'date', 'label' => ['en' => 'Released'], 'translatable' => false],
            ['key' => 'launch', 'type' => 'datetime', 'label' => ['en' => 'Launch'], 'translatable' => false],
            ['key' => 'featured', 'type' => 'boolean', 'label' => ['en' => 'Featured'], 'translatable' => false],
            ['key' => 'stock', 'type' => 'number', 'label' => ['en' => 'Stock'], 'translatable' => false],
            ['key' => 'details', 'type' => 'rich-text', 'label' => ['en' => 'Details'], 'translatable' => false],
            ['key' => 'blank_details', 'type' => 'rich-text', 'label' => ['en' => 'Blank'], 'translatable' => false],
            ['key' => 'missing', 'type' => 'text', 'label' => ['en' => 'Missing'], 'translatable' => false],
            ['key' => 'photo', 'type' => 'photo', 'label' => ['en' => 'Photo'], 'translatable' => false],
        ],
    ]);
}

function publishMarkdownRecord(RecordType $type, string $slug, array $attributes = []): Record
{
    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);

    $record->slugs()->create(['locale' => 'en', 'slug' => $slug, 'base_path' => $type->slug_prefix]);

    return $record;
}

it('serves a markdown representation of a page when the request prefers text/markdown', function (): void {
    publishMarkdownPage('about-md', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => '<p>Big title</p>'],
            'subheading' => ['en' => 'A warm welcome'],
            'ctaPrimary' => ['enabled' => true, 'text' => ['en' => 'Start now'], 'link' => ['type' => 'url', 'value' => 'https://example.com/start', 'newTab' => false]],
            'ctaSecondary' => ['enabled' => true, 'text' => [], 'link' => ['type' => 'url', 'value' => '']],
        ]],
        ['id' => 'new-2', 'type' => 'rich-text', 'content' => [
            'heading' => ['en' => 'Our story'],
            'body' => ['en' => '<h3>Chapter one</h3><p>Copy with a <a href="https://example.com">link</a> and <strong>bold</strong> text.</p><ul><li>First</li><li>Second</li></ul>'],
        ]],
        ['id' => 'new-3', 'type' => 'divider', 'content' => ['size' => 'medium']],
        ['id' => 'new-4', 'type' => 'spacer', 'content' => ['size' => 'medium']],
    ], ['title' => 'About us', 'description' => 'Who we are.']);

    $response = $this->get(route('page', 'about-md'), ['Accept' => 'text/markdown']);

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->assertHeader('Vary', 'Accept');

    $markdown = $response->getContent();

    expect($markdown)
        ->toContain('# About us')
        ->toContain('> Who we are.')
        ->toContain('## Big title')
        ->toContain('A warm welcome')
        ->toContain('[Start now](https://example.com/start)')
        ->toContain('## Our story')
        ->toContain('### Chapter one')
        ->toContain('[link](https://example.com)')
        ->toContain('**bold**')
        ->toContain('- First')
        ->toContain('---')
        ->not->toContain('<p>');
});

it('renders a code block as a fenced markdown code block', function (): void {
    publishMarkdownPage('code-md', [
        ['id' => 'new-1', 'type' => 'code', 'content' => [
            'heading' => ['en' => 'Install'],
            'intro' => ['en' => '<p>Run this first.</p>'],
            'code' => "composer install\nnpm install",
            'language' => 'bash',
        ]],
        ['id' => 'new-2', 'type' => 'code', 'content' => [
            'code' => 'echo 1;',
            'language' => 'not a language',
        ]],
    ]);

    $markdown = (string) $this->get(route('page', 'code-md'), ['Accept' => 'text/markdown'])->getContent();

    expect($markdown)
        ->toContain('## Install')
        ->toContain('Run this first.')
        ->toContain("```bash\ncomposer install\nnpm install\n```")
        ->toContain("```\necho 1;\n```");
});

it('keeps serving html when the request does not prefer markdown', function (): void {
    publishMarkdownPage('plain', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => ['heading' => ['en' => 'Hello there']]],
    ]);

    $response = $this->get(route('page', 'plain'), ['Accept' => 'text/html, text/markdown']);

    $response->assertOk()->assertSee('Hello there');

    expect($response->headers->get('Vary'))->toContain('Accept');
    expect((string) $response->headers->get('Content-Type'))->toContain('text/html');
});

it('returns 404 markdown-side for unpublished pages', function (): void {
    $page = Page::factory()->create(['status' => ContentStatus::DRAFT]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'secret-draft']);

    $this->get(route('page', 'secret-draft'), ['Accept' => 'text/markdown'])->assertNotFound();
});

it('serves the home page as markdown', function (): void {
    $page = publishMarkdownPage('landing', [
        ['id' => 'new-1', 'type' => 'rich-text', 'content' => ['body' => ['en' => '<p>Landing copy</p>']]],
    ], ['title' => 'Landing', 'description' => '']);

    Settings::set(['home_page_id' => $page->id]);

    $response = $this->get(route('home'), ['Accept' => 'text/markdown']);

    $response->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

    expect($response->getContent())
        ->toContain('# Landing')
        ->toContain('Landing copy');
});

it('renders media, listing and contact blocks as markdown', function (): void {
    publishMarkdownPage('overview', [
        ['id' => 'new-1', 'type' => 'text-image', 'content' => [
            'heading' => ['en' => 'Side by side'],
            'subheading' => ['en' => 'Sub line'],
            'body' => ['en' => '<p>Body copy</p>'],
            'image' => ['source' => 'images/pic.jpg', 'metadata' => ['alt' => 'A pic']],
        ]],
        ['id' => 'new-2', 'type' => 'gallery', 'content' => [
            'media' => [
                ['source' => 'images/one.jpg', 'mime_type' => 'image/jpeg', 'metadata' => ['caption' => 'First photo']],
                ['source' => 'videos/clip.mp4', 'mime_type' => 'video/mp4'],
            ],
        ]],
        ['id' => 'new-3', 'type' => 'photo', 'content' => [
            'heading' => ['en' => 'Framed'],
            'intro' => ['en' => '<p>Photo intro</p>'],
            'image' => ['source' => 'images/framed.jpg', 'metadata' => ['alt' => 'Framed shot']],
            'imageLink' => ['link' => ['type' => 'url', 'value' => 'https://example.com/full', 'newTab' => false]],
        ]],
        ['id' => 'new-4', 'type' => 'video', 'content' => [
            'heading' => ['en' => 'Watch this'],
            'intro' => ['en' => '<p>Video intro</p>'],
            'source' => 'url',
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]],
        ['id' => 'new-5', 'type' => 'video', 'content' => [
            'source' => 'url',
            'url' => 'https://vimeo.com/123456',
        ]],
        ['id' => 'new-6', 'type' => 'video', 'content' => [
            'source' => 'upload',
            'video' => ['source' => 'videos/native.mp4'],
        ]],
        ['id' => 'new-7', 'type' => 'audio', 'content' => [
            'heading' => ['en' => 'Podcast'],
            'audio' => ['source' => 'audio/episode.mp3'],
        ]],
        ['id' => 'new-8', 'type' => 'downloads', 'content' => [
            'heading' => ['en' => 'Files'],
            'files' => [
                ['source' => 'files/brochure.pdf', 'filename' => 'brochure.pdf', 'metadata' => ['caption' => 'Brochure']],
                ['filename' => 'missing.pdf'],
            ],
        ]],
        ['id' => 'new-9', 'type' => 'contact-form', 'content' => [
            'heading' => ['en' => '<p>Get in touch</p>'],
            'description' => ['en' => '<p>We reply fast.</p>'],
        ]],
        ['id' => 'new-10', 'type' => 'search', 'content' => [
            'heading' => ['en' => 'Find records'],
        ]],
        ['id' => 'new-11', 'type' => 'buttons', 'content' => [
            'items' => [
                ['id' => 'b1', 'text' => ['en' => 'Click me'], 'variant' => 'primary', 'link' => ['type' => 'url', 'value' => 'https://example.com/go', 'newTab' => false]],
                ['id' => 'b2', 'text' => ['en' => 'Broken'], 'variant' => 'primary', 'link' => ['type' => 'url', 'value' => '']],
                ['id' => 'b3', 'text' => [], 'variant' => 'primary', 'link' => ['type' => 'url', 'value' => 'https://example.com/hidden']],
            ],
        ]],
        ['id' => 'new-12', 'type' => 'text-image', 'content' => [
            'body' => ['en' => '<p>No image here</p>'],
        ]],
        ['id' => 'new-13', 'type' => 'stats', 'content' => [
            'heading' => ['en' => 'Empty stats'],
        ]],
        ['id' => 'new-14', 'type' => 'location', 'content' => [
            'name' => ['en' => 'Head office'],
            'address' => ['en' => '1 Main Street'],
            'phone' => '+1 555 0100',
            'email' => 'hello@example.com',
            'map' => 'https://maps.example.com/office',
        ]],
    ]);

    $markdown = $this->get(route('page', 'overview'), ['Accept' => 'text/markdown'])->getContent();

    expect($markdown)
        ->toContain('## Side by side')
        ->toContain('Sub line')
        ->toContain('Body copy')
        ->toContain('![A pic](')
        ->toContain('![First photo](')
        ->not->toContain('clip.mp4')
        ->toContain('[![Framed shot](')
        ->toContain('](https://example.com/full)')
        ->toContain('## Watch this')
        ->toContain('Video intro')
        ->toContain('[Watch video](https://www.youtube.com/watch?v=dQw4w9WgXcQ)')
        ->toContain('[Watch video](https://vimeo.com/123456)')
        ->toContain('videos/native.mp4')
        ->toContain('## Podcast')
        ->toContain('[Listen](')
        ->toContain('audio/episode.mp3')
        ->toContain('- [Brochure](')
        ->not->toContain('missing.pdf')
        ->toContain('## Get in touch')
        ->toContain('We reply fast.')
        ->toContain('## Find records')
        ->toContain('- [Click me](https://example.com/go)')
        ->not->toContain('[Broken]')
        ->not->toContain('hidden')
        ->toContain('No image here')
        ->toContain('## Empty stats')
        ->toContain('## Head office')
        ->toContain('1 Main Street')
        ->toContain('+1 555 0100')
        ->toContain('hello@example.com')
        ->toContain('](https://maps.example.com/office)');
});

it('renders item-based blocks as markdown and skips empty items', function (): void {
    publishMarkdownPage('teams', [
        ['id' => 'new-1', 'type' => 'accordion', 'content' => [
            'items' => [
                ['id' => 'a1', 'title' => ['en' => 'What is it?'], 'body' => ['en' => '<p>An answer.</p>']],
            ],
        ]],
        ['id' => 'new-2', 'type' => 'testimonials', 'content' => [
            'heading' => ['en' => 'Praise'],
            'items' => [
                ['id' => 't1', 'quote' => ['en' => 'Simply great'], 'author' => ['en' => 'Jane'], 'role' => ['en' => 'CEO']],
                ['id' => 't2', 'quote' => [], 'author' => ['en' => 'Ghost']],
            ],
        ]],
        ['id' => 'new-3', 'type' => 'sponsors', 'content' => [
            'heading' => ['en' => 'Partners'],
            'items' => [
                ['id' => 's1', 'name' => ['en' => 'Acme'], 'link' => 'https://acme.test'],
                ['id' => 's2', 'name' => ['en' => 'NoLink'], 'link' => ''],
                ['id' => 's3', 'name' => [], 'link' => 'https://nameless.test'],
            ],
        ]],
        ['id' => 'new-4', 'type' => 'feature-cards', 'content' => [
            'heading' => ['en' => 'Features'],
            'intro' => ['en' => '<p>What you get</p>'],
            'items' => [
                ['id' => 'f1', 'title' => ['en' => 'Fast'], 'body' => ['en' => '<p>Very fast.</p>'], 'cta' => ['enabled' => true, 'text' => ['en' => 'More'], 'link' => ['type' => 'url', 'value' => 'https://example.com/fast', 'newTab' => false]]],
            ],
        ]],
        ['id' => 'new-5', 'type' => 'stats', 'content' => [
            'heading' => ['en' => 'Numbers'],
            'items' => [
                ['id' => 'n1', 'value' => ['en' => '99%'], 'label' => ['en' => 'Uptime']],
                ['id' => 'n2', 'value' => [], 'label' => []],
            ],
        ]],
        ['id' => 'new-6', 'type' => 'team', 'content' => [
            'heading' => ['en' => 'The crew'],
            'items' => [
                ['id' => 'm1', 'name' => ['en' => 'Ada'], 'role' => ['en' => 'Engineer'], 'bio' => ['en' => '<p>Builds things.</p>'], 'socials' => ['email' => 'ada@example.com', 'website' => 'https://ada.dev', 'linkedin' => '', 'x' => '', 'instagram' => '']],
                ['id' => 'm2', 'name' => [], 'role' => ['en' => 'Invisible']],
            ],
        ]],
        ['id' => 'new-7', 'type' => 'pricing', 'content' => [
            'heading' => ['en' => 'Plans'],
            'items' => [
                ['id' => 'p1', 'name' => ['en' => 'Pro'], 'price' => ['en' => '$29'], 'period' => ['en' => 'per month'], 'description' => ['en' => '<p>For teams.</p>'], 'features' => ['en' => '<ul><li>Everything</li></ul>'], 'cta' => ['enabled' => true, 'text' => ['en' => 'Buy Pro'], 'link' => ['type' => 'url', 'value' => 'https://example.com/buy', 'newTab' => false]]],
                ['id' => 'p2', 'name' => []],
            ],
        ]],
    ]);

    $markdown = $this->get(route('page', 'teams'), ['Accept' => 'text/markdown'])->getContent();

    expect($markdown)
        ->toContain('### What is it?')
        ->toContain('An answer.')
        ->toContain('## Praise')
        ->toContain('> Simply great')
        ->toContain('> — Jane, CEO')
        ->not->toContain('Ghost')
        ->toContain('- [Acme](https://acme.test)')
        ->toContain('- NoLink')
        ->not->toContain('nameless.test')
        ->toContain('### Fast')
        ->toContain('Very fast.')
        ->toContain('[More](https://example.com/fast)')
        ->toContain('- **99%** Uptime')
        ->toContain('### Ada')
        ->toContain('*Engineer*')
        ->toContain('Builds things.')
        ->toContain('ada@example.com')
        ->toContain('[Website](https://ada.dev)')
        ->not->toContain('Invisible')
        ->toContain('### Pro')
        ->toContain('**$29** per month')
        ->toContain('For teams.')
        ->toContain('- Everything')
        ->toContain('[Buy Pro](https://example.com/buy)');
});

it('renders collection blocks as a list of record links', function (): void {
    $type = markdownRecordType();

    publishMarkdownRecord($type, 'widget', [
        'title' => ['en' => 'Super Widget'],
        'description' => ['en' => 'The best widget.'],
        'data' => ['heading' => ['en' => 'Super Widget'], 'overview' => ['en' => '<p>Truly super.</p>']],
    ]);

    Record::factory()->create([
        'record_type_id' => $type->id,
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => ['en' => 'Slugless'],
    ]);

    publishMarkdownPage('catalog', [
        ['id' => 'new-1', 'type' => 'collection', 'content' => [
            'recordTypeId' => $type->id,
            'source' => 'latest',
            'limit' => 12,
            'heading' => ['en' => 'Latest products'],
            'button' => ['enabled' => true, 'text' => ['en' => 'All products'], 'link' => ['type' => 'url', 'value' => 'https://example.com/products', 'newTab' => false]],
        ]],
    ]);

    $markdown = $this->get(route('page', 'catalog'), ['Accept' => 'text/markdown'])->getContent();

    expect($markdown)
        ->toContain('## Latest products')
        ->toContain('- [Super Widget](')
        ->toContain('/products/widget')
        ->toContain('Truly super.')
        ->not->toContain('Slugless')
        ->toContain('[All products](https://example.com/products)');
});

it('serves a markdown representation of a record with its fields and blocks', function (): void {
    $record = publishMarkdownRecord(markdownRecordType(), 'gadget', [
        'title' => ['en' => 'Gadget'],
        'description' => ['en' => 'Fallback description.'],
        'data' => [
            'heading' => ['en' => 'Great Gadget'],
            'overview' => ['en' => '<p>An <strong>excellent</strong> gadget.</p>'],
            'price' => '49.50',
            'released' => '2026-03-14',
            'launch' => '2026-03-14T09:30',
            'featured' => true,
            'stock' => 42,
            'details' => '<p>Hand made</p>',
            'blank_details' => '<p> </p>',
            'missing' => '',
        ],
    ]);

    $record->updateBlocks([
        ['id' => 'new-1', 'type' => 'rich-text', 'content' => ['body' => ['en' => '<p>Specs below</p>']]],
    ]);

    $response = $this->get(route('record', ['recordType' => 'products', 'slug' => 'gadget']), ['Accept' => 'text/markdown']);

    $response->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

    $markdown = $response->getContent();

    expect($markdown)
        ->toContain('# Great Gadget')
        ->toContain('An **excellent** gadget.')
        ->toContain('- **Price:**')
        ->toContain('49.50')
        ->toContain('- **Released:** 2026-03-14')
        ->toContain('- **Launch:** 2026-03-14 09:30')
        ->toContain('- **Featured:** Yes')
        ->toContain('- **Stock:** 42')
        ->toContain('- **Details:** Hand made')
        ->not->toContain('**Blank:**')
        ->not->toContain('**Missing:**')
        ->toContain('Specs below');
});

it('falls back to the record description when there is no overview', function (): void {
    publishMarkdownRecord(markdownRecordType(), 'bare', [
        'title' => ['en' => 'Bare Record'],
        'description' => ['en' => 'Just a description.'],
        'data' => [],
    ]);

    $markdown = $this->get(route('record', ['recordType' => 'products', 'slug' => 'bare']), ['Accept' => 'text/markdown'])->getContent();

    expect($markdown)
        ->toContain('# Bare Record')
        ->toContain('Just a description.');
});

it('returns 404 markdown-side for an unknown record type prefix', function (): void {
    $this->get('/nonexistent-type/some-slug', ['Accept' => 'text/markdown'])->assertNotFound();
});
