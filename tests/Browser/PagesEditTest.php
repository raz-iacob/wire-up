<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Media;
use App\Models\Page;
use App\Models\Settings;

it('shows the homepage badge and makes the slug readonly in the editor', function (): void {
    $page = Page::factory()->create([
        'title' => 'Home Landing',
        'status' => ContentStatus::PUBLISHED,
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
        'status' => ContentStatus::PUBLISHED,
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
        )
        ->assertScript(
            "window.blockTitle({ type: 'text-image', content: { heading: { en: '<p><span data-badge class=\"wire-badge\">For women</span></p><p>Hormones off</p><p>Life is hard</p>' } } }, 'en', 'fallback')",
            'For women Hormones off Life is hard',
        );
});

it('derives the location block header title from the heading', function (): void {
    $page = Page::factory()->create([
        'title' => 'Location Page',
        'status' => ContentStatus::PUBLISHED,
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
        'status' => ContentStatus::PUBLISHED,
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

it('registers an inline badge mark and applies chosen colours in the editor', function (): void {
    $page = Page::factory()->create([
        'title' => 'Badge Page',
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'badge-editor']);
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => 'text-image', 'content' => [
            'body' => ['en' => '<p>Make this a badge</p>'],
        ]],
    ]);

    $this->actingAsAdmin();

    $browser = visit(route('admin.pages-edit', $page));
    $browser->assertNoJavascriptErrors();

    $browser->assertScript("document.querySelectorAll('button[aria-label=\"Badge\"]').length >= 1", true);

    $browser->script("document.querySelector('button[aria-label=\"Badge\"]').click(); void 0");
    $browser->wait(0.3);

    $browser->assertScript(
        "(() => { const panel = [...document.querySelectorAll('[data-flux-editor] [popover]')].find(p => p.matches(':popover-open')); return !!panel && panel.querySelectorAll('input[type=\"color\"]').length === 2 && panel.innerText.includes('Apply'); })()",
        true,
    );

    $apply = "(() => { const root = [...document.querySelectorAll('[data-flux-editor]')].find(r => r._tiptap && r._tiptap.getText().includes('Make this a badge')); if (! root) { return false; } root._tiptap.chain().focus().selectAll().setBadge({ bg: '#2563eb', color: '#ffffff' }).run(); const h = root._tiptap.getHTML(); return h.includes('data-badge') && h.includes('#2563eb') && h.includes('#ffffff'); })()";

    $browser->assertScript($apply, true);

    $browser->assertScript(
        "(() => { const span = document.querySelector('ui-editor-content .wire-badge'); if (! span) { return 'no-span'; } const s = getComputedStyle(span); return [s.fontSize, s.fontWeight, s.textTransform, s.display].join('|'); })()",
        '11px|700|uppercase|inline-block',
    );

    $roundTrip = "(() => { const root = [...document.querySelectorAll('[data-flux-editor]')].find(r => r._tiptap); root._tiptap.commands.setContent('<p><span data-badge data-badge-bg=\"#abcdef\" data-badge-color=\"#123456\">Hi</span></p>', true); const h = root._tiptap.getHTML(); return h.includes('data-badge') && h.includes('#abcdef') && h.includes('#123456'); })()";

    $browser->assertScript($roundTrip, true);

    $browser->assertNoJavascriptErrors();
});

it('gives every editor the same toolbar with links and badges', function (): void {
    $page = Page::factory()->create([
        'title' => 'Toolbar Page',
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'toolbar-page']);
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => 'text-image', 'content' => [
            'heading' => ['en' => '<p>Heading copy</p>'],
            'body' => ['en' => '<p>Body copy</p>'],
        ]],
    ]);

    $this->actingAsAdmin();

    $browser = visit(route('admin.pages-edit', $page));
    $browser->assertNoJavascriptErrors();

    $browser->assertScript(
        "(() => { const editors = document.querySelectorAll('[data-flux-editor]').length; const links = document.querySelectorAll('[data-flux-editor] [data-editor=\"link\"]').length; const badges = document.querySelectorAll('[data-flux-editor] button[aria-label=\"Badge\"]').length; return editors >= 2 && links === editors && badges === editors; })()",
        true,
    );
});

it('strips formatting from pasted content but keeps paragraphs, lists and links', function (): void {
    $page = Page::factory()->create([
        'title' => 'Paste Page',
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'paste-page']);
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => 'text-image', 'content' => ['body' => ['en' => '<p>Body copy</p>']]],
    ]);

    $this->actingAsAdmin();

    $browser = visit(route('admin.pages-edit', $page));
    $browser->assertNoJavascriptErrors();

    $dirty = '<h1>Big title</h1><p>Hi <strong>bold</strong> <span style=\"color:red\">red</span> <a href=\"https://example.com\">link</a></p><ul><li>one</li></ul>';

    $browser->assertScript(
        "(() => { const out = window.cleanPastedHtml('{$dirty}'); return !/<h1|<strong|<span|style=/.test(out) && out.includes('Big title') && out.includes('bold') && out.includes('href=\"https://example.com\"') && out.includes('<li>one</li>'); })()",
        true,
    );
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
        'status' => ContentStatus::PUBLISHED,
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
        'status' => ContentStatus::PUBLISHED,
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
        'status' => ContentStatus::PUBLISHED,
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

it('saves a per-item media photo selected through the media library', function (): void {
    $media = Media::factory()->create();

    $page = Page::factory()->create([
        'title' => 'Team Save',
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'team-photo-save']);
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => 'team', 'content' => [
            'items' => [
                ['id' => 'm1', 'photo' => null, 'name' => ['en' => 'Jane'], 'role' => [], 'bio' => [], 'socials' => ['email' => '', 'website' => '', 'linkedin' => '', 'x' => '', 'instagram' => '']],
            ],
        ]],
    ]);

    $blockId = (string) $page->blocks()->first()->id;

    $this->actingAsAdmin();

    $browser = visit(route('admin.pages-edit', $page));
    $browser->assertNoJavascriptErrors();

    $target = "item-media-{$blockId}-m1-photo.en";
    $payload = json_encode([[
        'id' => $media->id,
        'source' => $media->source,
        'filename' => $media->filename,
        'mime_type' => $media->mime_type,
        'icon' => 'photo',
        'crop' => [],
        'metadata' => [],
    ]]);

    $browser->script("window.Livewire.dispatch('media-selected', { target: '{$target}', media: {$payload} }); void 0");
    $browser->wait(1.5);

    $comp = "window.Livewire.all().find(c => c.\$wire.get('blocks') !== undefined)";
    $photoId = "(() => { const b = Object.values($comp.\$wire.get('blocks')).find(b => b.type === 'team'); const p = b.content.items[0].photo; return p ? p.id : null; })()";

    $browser->assertScript($photoId, $media->id);

    $browser->script("$comp.\$wire.update(); void 0");
    $browser->wait(1.0);
    $browser->assertNoJavascriptErrors();

    expect($page->blocks()->first()->content['items'][0]['photo']['id'] ?? null)->toBe($media->id);
});

it('renders and toggles collapsible accordion items in the editor', function (): void {
    $page = Page::factory()->create([
        'title' => 'Accordion Editor',
        'status' => ContentStatus::PUBLISHED,
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
        'status' => ContentStatus::PUBLISHED,
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
