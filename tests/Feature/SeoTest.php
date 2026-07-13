<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\Locale;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Settings;
use App\Models\User;
use App\Services\RecordTypePresets;

function seoFeaturePage(string $slug, array $attributes = [], array $blocks = []): Page
{
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);

    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);

    foreach (array_values($blocks) as $position => $block) {
        $page->blocks()->create([
            'type' => $block['type'],
            'position' => $position,
            'content' => $block['content'],
        ]);
    }

    return $page->refresh();
}

function seoFeatureRecord(string $presetKey, array $data = [], array $attributes = [], array $blocks = []): Record
{
    /** @var array<string, mixed> $preset */
    $preset = RecordTypePresets::find($presetKey);

    $type = RecordType::factory()->create([
        'key' => $preset['key'],
        'slug_prefix' => $preset['slug_prefix'],
        'name' => $preset['name'],
        'fields' => $preset['fields'],
    ]);

    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'data' => $data,
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);

    $record->setSlugs();

    foreach (array_values($blocks) as $position => $block) {
        $record->blocks()->create([
            'type' => $block['type'],
            'position' => $position,
            'content' => $block['content'],
        ]);
    }

    return $record->refresh();
}

it('lists published pages in the sitemap and omits drafts', function (): void {
    seoFeaturePage('about-us', ['title' => ['en' => 'About Us']]);
    seoFeaturePage('draft-page', ['status' => ContentStatus::DRAFT, 'published_at' => null]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('/about-us', false)
        ->assertDontSee('/draft-page', false)
        ->assertDontSee('/welcome', false);
});

it('empties the sitemap when the site discourages search engines', function (): void {
    seoFeaturePage('about-us', ['title' => ['en' => 'About Us']]);
    Settings::set(['noindex' => true]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('<urlset', false)
        ->assertDontSee('/about-us', false);
});

it('excludes a page-level noindex page from the sitemap', function (): void {
    seoFeaturePage('public-page', ['title' => ['en' => 'Public']]);
    seoFeaturePage('hidden-page', ['title' => ['en' => 'Hidden'], 'metadata' => ['published_locales' => ['en'], 'noindex' => true]]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('/public-page', false)
        ->assertDontSee('/hidden-page', false);
});

it('lists pages in llms.txt and dumps full content in llms-full.txt', function (): void {
    seoFeaturePage('story', ['title' => ['en' => 'Our Story']], [
        ['type' => BlockType::RICH_TEXT->value, 'content' => ['heading' => ['en' => 'Once upon a time'], 'body' => ['en' => '<p>We started in a garage.</p>']]],
    ]);

    $this->get('/llms.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Our Story')
        ->assertSee('/story');

    $this->get('/llms-full.txt')
        ->assertOk()
        ->assertSee('We started in a garage.');
});

it('emits canonical, Open Graph and JSON-LD on a public page', function (): void {
    seoFeaturePage('reach-us', ['title' => ['en' => 'Contact'], 'description' => ['en' => 'Reach our team.']]);

    $this->get('/reach-us')
        ->assertOk()
        ->assertSee('<link rel="canonical"', false)
        ->assertSee('<meta property="og:title" content="Contact">', false)
        ->assertSee('<meta property="og:description" content="Reach our team.">', false)
        ->assertSee('application/ld+json', false)
        ->assertSee('"@type":"WebPage"', false);
});

it('uses a block-derived description when a page has none stored', function (): void {
    seoFeaturePage('team', ['title' => ['en' => 'Team']], [
        ['type' => BlockType::RICH_TEXT->value, 'content' => ['heading' => ['en' => 'Meet the crew behind the work']]],
    ]);

    $this->get('/team')
        ->assertOk()
        ->assertSee('Meet the crew behind the work', false);
});

it('uses the site default share image when a page has none', function (): void {
    Settings::set(['default_og_image' => ['source' => 'default-share.jpg', 'crop' => ['default' => ['crop_w' => 1200, 'crop_h' => 630, 'crop_x' => 0, 'crop_y' => 0]]]]);
    seoFeaturePage('pricing', ['title' => ['en' => 'Pricing']]);

    $this->get('/pricing')
        ->assertOk()
        ->assertSee('default-share.jpg', false);
});

it('emits hreflang alternates in the sitemap for a multi-locale site', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    $page = seoFeaturePage('about-hreflang', [
        'metadata' => ['published_locales' => ['en', 'nl']],
        'title' => ['en' => 'About', 'nl' => 'Over ons'],
    ]);
    $page->slugs()->create(['locale' => 'nl', 'slug' => 'over-ons']);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('hreflang="en"', false)
        ->assertSee('hreflang="nl"', false)
        ->assertSee('hreflang="x-default"', false);
});

it('skips published pages with no slug in the llms exports', function (): void {
    Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => ['en' => 'Slugless Page'],
    ]);

    $this->get('/llms.txt')->assertOk()->assertDontSee('Slugless Page');
    $this->get('/llms-full.txt')->assertOk()->assertDontSee('Slugless Page');
});

it('includes the site tagline in the llms index', function (): void {
    Settings::set(['title' => ['en' => 'Acme'], 'description' => ['en' => 'Building better things']]);

    $this->get('/llms.txt')
        ->assertOk()
        ->assertSee('Building better things');
});

it('embeds block-level structured data in the page JSON-LD', function (): void {
    seoFeaturePage('about-us', ['title' => ['en' => 'About Us']], [
        ['type' => BlockType::LOCATION->value, 'content' => ['name' => ['en' => 'Acme'], 'address' => ['en' => '123 Main St'], 'phone' => '+1 555 0100']],
        ['type' => BlockType::TEAM->value, 'content' => ['items' => [['id' => 'a', 'name' => ['en' => 'Jane'], 'role' => ['en' => 'CEO'], 'socials' => []]]]],
        ['type' => BlockType::PRICING->value, 'content' => ['items' => [['id' => 'a', 'name' => ['en' => 'Pro'], 'price' => ['en' => '$99']], ['id' => 'b', 'name' => ['en' => 'Free'], 'price' => ['en' => 'Free']]]]],
        ['type' => BlockType::AUDIO->value, 'content' => ['audio' => ['source' => 'uploads/song.mp3', 'mime_type' => 'audio/mpeg'], 'heading' => ['en' => 'Listen']]],
    ]);

    $html = $this->get('/about-us')->assertOk()->getContent();
    expect(preg_match('#<script type="application/ld\+json">(.*?)</script>#s', (string) $html, $m))->toBe(1);

    /** @var array{'@graph': array<int, array<string, mixed>>} $data */
    $data = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
    $graph = $data['@graph'];
    $types = array_column($graph, '@type');

    expect($types)->toContain('LocalBusiness', 'Person', 'Offer', 'AudioObject');

    $offers = array_values(array_filter($graph, fn (array $n): bool => $n['@type'] === 'Offer'));
    expect($offers[0])->toMatchArray(['name' => 'Pro', 'price' => '99', 'priceCurrency' => 'USD'])
        ->and($offers[1])->not->toHaveKey('price');
});

it('emits a VideoObject using the default share image as thumbnail', function (): void {
    Settings::set(['default_og_image' => ['source' => 'default-share.jpg', 'crop' => ['default' => ['crop_w' => 1200, 'crop_h' => 630, 'crop_x' => 0, 'crop_y' => 0]]]]);

    seoFeaturePage('watch', ['title' => ['en' => 'Watch']], [
        ['type' => BlockType::VIDEO->value, 'content' => ['source' => 'url', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'heading' => ['en' => 'Trailer']]],
    ]);

    $this->get('/watch')
        ->assertOk()
        ->assertSee('"@type":"VideoObject"', false)
        ->assertSee('"embedUrl":"https://www.youtube.com/embed/dQw4w9WgXcQ"', false);
});

it('renders a noindex robots tag for a page-level noindex page only', function (): void {
    seoFeaturePage('indexable', ['title' => ['en' => 'Indexable']]);
    seoFeaturePage('private-page', ['title' => ['en' => 'Private'], 'metadata' => ['published_locales' => ['en'], 'noindex' => true]]);

    $this->get('/private-page')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

    $this->get('/indexable')
        ->assertOk()
        ->assertSee('content="index, follow', false)
        ->assertDontSee('noindex', false);
});

it('lists published records in the sitemap and omits drafts and noindexed records', function (): void {
    seoFeatureRecord('product', ['heading' => ['en' => 'Piftie']], ['title' => ['en' => 'Piftie']]);
    seoFeatureRecord('service', [], ['title' => ['en' => 'Draft Service'], 'status' => ContentStatus::DRAFT, 'published_at' => null]);
    seoFeatureRecord('event', [], ['title' => ['en' => 'Hidden Event'], 'metadata' => ['published_locales' => ['en'], 'noindex' => true]]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('/products/piftie', false)
        ->assertDontSee('/services/', false)
        ->assertDontSee('/events/', false);
});

it('lists records grouped by type in llms.txt and dumps their content in llms-full.txt', function (): void {
    seoFeatureRecord('product', [
        'heading' => ['en' => 'Piftie'],
        'overview' => ['en' => '<p>A handmade delight.</p>'],
    ], ['title' => ['en' => 'Piftie'], 'description' => ['en' => 'The finest piftie.']]);

    $this->get('/llms.txt')
        ->assertOk()
        ->assertSee('## Products')
        ->assertSee('Piftie')
        ->assertSee('/products/piftie')
        ->assertSee('The finest piftie.');

    $this->get('/llms-full.txt')
        ->assertOk()
        ->assertSee('A handmade delight.');
});

it('emits per-record canonical, robots and WebPage JSON-LD on a published record', function (): void {
    seoFeatureRecord('service', [
        'heading' => ['en' => 'Consulting'],
    ], ['title' => ['en' => 'Consulting'], 'description' => ['en' => 'We advise.']]);

    $this->get('/services/consulting')
        ->assertOk()
        ->assertSee('<link rel="canonical"', false)
        ->assertSee('<meta property="og:title" content="Consulting">', false)
        ->assertSee('<meta property="og:description" content="We advise.">', false)
        ->assertSee('"@type":"WebPage"', false)
        ->assertSee('"@type":"Service"', false)
        ->assertSee('content="index, follow', false);
});

it('emits Product structured data with an Offer for a product record', function (): void {
    seoFeatureRecord('product', [
        'heading' => ['en' => 'Piftie'],
        'current_price' => '19.99',
        'regular_price' => '29.99',
        'sku' => 'PIF-001',
    ], ['title' => ['en' => 'Piftie']]);

    $html = (string) $this->get('/products/piftie')->assertOk()->getContent();
    expect(preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m))->toBe(1);

    /** @var array{'@graph': array<int, array<string, mixed>>} $data */
    $data = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
    $product = array_first(array_filter($data['@graph'], fn (array $n): bool => $n['@type'] === 'Product'));

    expect($product)->toMatchArray(['@type' => 'Product', 'sku' => 'PIF-001'])
        ->and($product['offers'])->toMatchArray(['@type' => 'Offer', 'price' => '19.99']);
});

it('emits Event structured data with dates and location for an event record', function (): void {
    seoFeatureRecord('event', [
        'heading' => ['en' => 'Launch Party'],
        'starts_at' => '2026-08-01 18:00:00',
        'ends_at' => '2026-08-01 22:00:00',
        'location' => 'Amsterdam',
    ], ['title' => ['en' => 'Launch Party']]);

    $html = (string) $this->get('/events/launch-party')->assertOk()->getContent();
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

    /** @var array{'@graph': array<int, array<string, mixed>>} $data */
    $data = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
    $event = array_first(array_filter($data['@graph'], fn (array $n): bool => $n['@type'] === 'Event'));

    expect($event['@type'])->toBe('Event')
        ->and($event)->toHaveKeys(['startDate', 'endDate'])
        ->and($event['location'])->toMatchArray(['@type' => 'Place', 'name' => 'Amsterdam']);
});

it('emits Person structured data with a job title for a team member record', function (): void {
    seoFeatureRecord('team-member', [
        'heading' => ['en' => 'Jane Doe'],
        'role' => ['en' => 'Founder'],
    ], ['title' => ['en' => 'Jane Doe']]);

    $html = (string) $this->get('/team/jane-doe')->assertOk()->getContent();
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

    /** @var array{'@graph': array<int, array<string, mixed>>} $data */
    $data = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
    $person = array_first(array_filter($data['@graph'], fn (array $n): bool => $n['@type'] === 'Person'));

    expect($person)->toMatchArray(['@type' => 'Person', 'name' => 'Jane Doe', 'jobTitle' => 'Founder']);
});

it('renders a noindex robots tag for a page-level noindex record', function (): void {
    seoFeatureRecord('service', ['heading' => ['en' => 'Secret']], [
        'title' => ['en' => 'Secret'],
        'metadata' => ['published_locales' => ['en'], 'noindex' => true],
    ]);

    $this->actingAs(User::factory()->create(['role' => 'owner']));

    $this->get('/services/secret')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('skips published records with no slug in the llms index', function (): void {
    /** @var array<string, mixed> $preset */
    $preset = RecordTypePresets::find('service');

    $type = RecordType::factory()->create([
        'key' => $preset['key'],
        'slug_prefix' => $preset['slug_prefix'],
        'name' => $preset['name'],
        'fields' => $preset['fields'],
    ]);

    Record::factory()->create([
        'record_type_id' => $type->id,
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
        'title' => ['en' => 'Slugless Record'],
    ]);

    $this->get('/llms.txt')->assertOk()->assertDontSee('Slugless Record');
});
