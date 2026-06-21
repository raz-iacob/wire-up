<?php

declare(strict_types=1);

use App\Enums\PageStatus;
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
