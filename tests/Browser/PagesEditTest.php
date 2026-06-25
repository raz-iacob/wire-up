<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Media;
use App\Models\Page;
use App\Models\Settings;

it('shows the homepage badge and makes the slug readonly in the editor', function (): void {
    $page = Page::factory()->create([
        'title' => 'Home Landing',
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'home-landing']);
    Settings::set(['home_page_id' => $page->id]);

    $this->actingAsAdmin();

    $browser = visit(route('admin.pages-edit', $page));

    $browser->assertNoJavascriptErrors()
        ->assertSee('Homepage')
        ->assertSee('Served at')
        ->assertScript("document.querySelectorAll('input[readonly]').length >= 1", true);
});

it('derives the text-image block header title from the heading, not the body', function (): void {
    $page = Page::factory()->create([
        'title' => 'Title Page',
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'title-page']);
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => 'text-image', 'content' => [
            'heading' => ['en' => '<p>The <strong>heading</strong> wins</p>'],
            'body' => ['en' => '<p>Body should be ignored</p>'],
        ]],
    ]);

    $this->actingAsAdmin();

    $browser = visit(route('admin.pages-edit', $page));

    $browser->assertNoJavascriptErrors()
        ->assertScript(
            "window.blockTitle({ type: 'text-image', content: { heading: { en: '<p>The <strong>heading</strong> wins</p>' }, body: { en: '<p>Body should be ignored</p>' } } }, 'en', 'fallback')",
            'The heading wins',
        )
        ->assertScript(
            "window.blockTitle({ type: 'text-image', content: { body: { en: '<p>Body only</p>' } } }, 'en', 'Text + Image')",
            'Text + Image',
        );
});

it('derives the location block header title from the heading', function (): void {
    $page = Page::factory()->create([
        'title' => 'Location Page',
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'location-page']);
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => 'location', 'content' => [
            'heading' => ['en' => '<p>Find <strong>us</strong></p>'],
            'map' => '123 Main St, Springfield',
        ]],
    ]);

    $this->actingAsAdmin();

    $browser = visit(route('admin.pages-edit', $page));

    $browser->assertNoJavascriptErrors()
        ->assertScript(
            "window.blockTitle({ type: 'location', content: { heading: { en: '<p>Find <strong>us</strong></p>' } } }, 'en', 'fallback')",
            'Find us',
        )
        ->assertScript(
            "window.blockTitle({ type: 'location', content: { map: 'Berlin' } }, 'en', 'Location')",
            'Location',
        );
});

it('toggles a raw HTML source view on rich text editors and round-trips edits', function (): void {
    $page = Page::factory()->create([
        'title' => 'Source Page',
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'source-page']);
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => 'text-image', 'content' => [
            'heading' => ['en' => '<p>Source <strong>peek</strong></p>'],
            'body' => ['en' => '<p>Body copy</p>'],
        ]],
    ]);

    $this->actingAsAdmin();

    $browser = visit(route('admin.pages-edit', $page));
    $browser->assertNoJavascriptErrors();

    $browser->script("document.querySelectorAll('button[aria-label=\"View source\"]')[0].click(); void 0");
    $browser->wait(0.3);

    $browser->assertScript(
        "(() => { const t = document.querySelector('textarea[data-editor-source]'); return !!t && getComputedStyle(t).display !== 'none' && t.value.includes('<strong>peek</strong>'); })()",
        true,
    );

    $browser->script("(() => { const t = document.querySelector('textarea[data-editor-source]'); t.value = '<p>Edited via source</p>'; t.dispatchEvent(new Event('change', { bubbles: true })); })(); void 0");
    $browser->wait(0.2);
    $browser->script("document.querySelectorAll('button[aria-label=\"View source\"]')[0].click(); void 0");
    $browser->wait(0.3);

    $browser->assertScript(
        "(() => { const t = document.querySelector('textarea[data-editor-source]'); const c = document.querySelector('[data-flux-editor] ui-editor-content'); return getComputedStyle(t).display === 'none' && getComputedStyle(c).display !== 'none' && c.innerText.includes('Edited via source'); })()",
        true,
    );

    $browser->assertNoJavascriptErrors();
});

it('reorders testimonial items with saved avatars then saves without errors', function (): void {
    $first = Media::factory()->create();
    $second = Media::factory()->create();
    $third = Media::factory()->create();

    $avatar = fn (Media $media): array => [
        'id' => $media->id,
        'source' => $media->source,
        'preview' => $media->preview,
        'filename' => $media->filename,
        'alt_text' => $media->alt_text,
        'mime_type' => $media->mime_type,
        'crop' => [],
        'metadata' => [],
    ];

    $page = Page::factory()->create([
        'title' => 'Testimonials Save',
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'testimonials-save']);
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => 'text-image', 'content' => ['body' => ['en' => '<p>Intro</p>']]],
        ['id' => 'new-2', 'type' => 'testimonials', 'content' => [
            'items' => [
                ['id' => 'one', 'quote' => ['en' => '<p>First</p>'], 'author' => ['en' => 'Author One'], 'avatar' => $avatar($first), 'rating' => 0],
                ['id' => 'two', 'quote' => ['en' => '<p>Second</p>'], 'author' => ['en' => 'Author Two'], 'avatar' => $avatar($second), 'rating' => 0],
                ['id' => 'three', 'quote' => ['en' => '<p>Third</p>'], 'author' => ['en' => 'Author Three'], 'avatar' => $avatar($third), 'rating' => 0],
            ],
        ]],
    ]);

    $this->actingAsAdmin();

    $browser = visit(route('admin.pages-edit', $page));
    $browser->assertNoJavascriptErrors();

    $browser->script("window.dispatchEvent(new CustomEvent('blocks-toggle-all', { detail: true })); void 0");
    $browser->wait(0.4);

    $browser->drag('[wire\\:sort\\:item="three"] [wire\\:sort\\:handle]', '[wire\\:sort\\:item="one"]');
    $browser->wait(0.6);

    $comp = "window.Livewire.all().find(c => c.\$wire.get('blocks') !== undefined)";
    $testimonialItems = "(() => { const b = Object.values($comp.\$wire.get('blocks')).find(b => b.type === 'testimonials'); return b.content.items.map(i => i.id).join(','); })()";

    $browser->assertScript($testimonialItems, 'three,one,two');

    $browser->script("$comp.\$wire.update(); void 0");
    $browser->wait(1.0);
    $browser->assertNoJavascriptErrors();

    $browser->click('Author One')
        ->wait(0.6)
        ->assertNoJavascriptErrors();
});

it('reorders feature cards with saved images then saves without errors', function (): void {
    $media = collect(range(1, 3))->map(fn (): Media => Media::factory()->create());
    $image = fn (Media $m): array => ['id' => $m->id, 'source' => $m->source, 'crop' => [], 'metadata' => []];

    $page = Page::factory()->create([
        'title' => 'Feature Save',
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'feature-save']);
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => 'feature-cards', 'content' => [
            'items' => [
                ['id' => 'one', 'image' => $image($media[0]), 'title' => ['en' => 'Card One'], 'body' => ['en' => '<p>First</p>']],
                ['id' => 'two', 'image' => $image($media[1]), 'title' => ['en' => 'Card Two'], 'body' => ['en' => '<p>Second</p>']],
                ['id' => 'three', 'image' => $image($media[2]), 'title' => ['en' => 'Card Three'], 'body' => ['en' => '<p>Third</p>']],
            ],
        ]],
    ]);

    $this->actingAsAdmin();

    $browser = visit(route('admin.pages-edit', $page));
    $browser->assertNoJavascriptErrors();

    $browser->script("window.dispatchEvent(new CustomEvent('blocks-toggle-all', { detail: true })); void 0");
    $browser->wait(0.4);

    $browser->drag('[wire\\:sort\\:item="three"] [wire\\:sort\\:handle]', '[wire\\:sort\\:item="one"]');
    $browser->wait(0.6);

    $comp = "window.Livewire.all().find(c => c.\$wire.get('blocks') !== undefined)";
    $items = "(() => { const b = Object.values($comp.\$wire.get('blocks')).find(b => b.type === 'feature-cards'); return b.content.items.map(i => i.id).join(','); })()";

    $browser->assertScript($items, 'three,one,two');

    $browser->script("$comp.\$wire.update(); void 0");
    $browser->wait(1.0);
    $browser->assertNoJavascriptErrors();

    $browser->click('Card One')
        ->wait(0.6)
        ->assertNoJavascriptErrors();
});

it('reorders sponsors with saved logos then saves without errors', function (): void {
    $media = collect(range(1, 3))->map(fn (): Media => Media::factory()->create());
    $logo = fn (Media $m): array => ['id' => $m->id, 'source' => $m->source, 'crop' => [], 'metadata' => []];

    $page = Page::factory()->create([
        'title' => 'Sponsors Save',
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'sponsors-save']);
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => 'sponsors', 'content' => [
            'items' => [
                ['id' => 'one', 'logo' => $logo($media[0]), 'name' => ['en' => 'Acme'], 'link' => '', 'tier' => ''],
                ['id' => 'two', 'logo' => $logo($media[1]), 'name' => ['en' => 'Globex'], 'link' => '', 'tier' => ''],
                ['id' => 'three', 'logo' => $logo($media[2]), 'name' => ['en' => 'Initech'], 'link' => '', 'tier' => ''],
            ],
        ]],
    ]);

    $this->actingAsAdmin();

    $browser = visit(route('admin.pages-edit', $page));
    $browser->assertNoJavascriptErrors();

    $browser->script("window.dispatchEvent(new CustomEvent('blocks-toggle-all', { detail: true })); void 0");
    $browser->wait(0.4);

    $browser->drag('[wire\\:sort\\:item="three"] [wire\\:sort\\:handle]', '[wire\\:sort\\:item="one"]');
    $browser->wait(0.6);

    $comp = "window.Livewire.all().find(c => c.\$wire.get('blocks') !== undefined)";
    $items = "(() => { const b = Object.values($comp.\$wire.get('blocks')).find(b => b.type === 'sponsors'); return b.content.items.map(i => i.id).join(','); })()";

    $browser->assertScript($items, 'three,one,two');

    $browser->script("$comp.\$wire.update(); void 0");
    $browser->wait(1.0);
    $browser->assertNoJavascriptErrors();

    $browser->click('Acme')
        ->wait(0.6)
        ->assertNoJavascriptErrors();
});

it('renders and toggles collapsible accordion items in the editor', function (): void {
    $page = Page::factory()->create([
        'title' => 'Accordion Editor',
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'accordion-editor']);
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => 'accordion', 'content' => [
            'items' => [
                ['id' => 'one', 'title' => ['en' => 'First item'], 'body' => ['en' => '<p>Body one</p>']],
                ['id' => 'two', 'title' => ['en' => 'Second item'], 'body' => ['en' => '<p>Body two</p>']],
            ],
        ]],
    ]);

    $this->actingAsAdmin();

    $browser = visit(route('admin.pages-edit', $page));
    $browser->assertNoJavascriptErrors();

    $browser->script("window.dispatchEvent(new CustomEvent('blocks-toggle-all', { detail: true })); void 0");
    $browser->wait(0.4);

    $browser->assertSee('First item')
        ->assertSee('Second item')
        ->click('First item')
        ->wait(0.4)
        ->assertSee('Body one')
        ->assertNoJavascriptErrors();
});

it('keeps block items collapsed on load and opens only a newly added one', function (): void {
    $page = Page::factory()->create([
        'title' => 'Sponsors Collapse',
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'sponsors-collapse']);
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => 'sponsors', 'content' => ['items' => [
            ['id' => 'a', 'logo' => null, 'name' => ['en' => 'Acme'], 'link' => '', 'tier' => ''],
            ['id' => 'b', 'logo' => null, 'name' => [], 'link' => '', 'tier' => ''],
        ]]],
    ]);

    $this->actingAsAdmin();

    $browser = visit(route('admin.pages-edit', $page));
    $browser->assertNoJavascriptErrors();

    $browser->script("window.dispatchEvent(new CustomEvent('blocks-toggle-all', { detail: true })); void 0");
    $browser->wait(0.5);

    $openStates = "(() => { const cards = document.querySelectorAll('[wire\\\\:sort\\\\:group^=\"sponsors-\"] [wire\\\\:sort\\\\:item]'); return Array.from(cards).map(c => window.Alpine.\$data(c).open).join(','); })()";

    $browser->assertScript($openStates, 'false,false');

    $comp = "window.Livewire.all().find(c => c.\$wire.get('blocks') !== undefined)";
    $browser->script("$comp.\$wire.addSponsorItem(Object.keys($comp.\$wire.get('blocks'))[0]); void 0");
    $browser->wait(0.6);

    $browser->assertScript($openStates, 'false,false,true')
        ->assertNoJavascriptErrors();
});
