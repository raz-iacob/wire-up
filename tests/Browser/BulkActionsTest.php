<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Page;

it('selects pages on the list and publishes them in one go', function (): void {
    $alpha = Page::factory()->create(['title' => 'Alpha', 'status' => ContentStatus::DRAFT, 'published_at' => null]);
    $beta = Page::factory()->create(['title' => 'Beta', 'status' => ContentStatus::DRAFT, 'published_at' => null]);

    $this->actingAsAdmin();

    $page = visit(route('admin.pages-index'));

    $page->assertNoJavascriptErrors()
        ->assertDontSee('2 selected')
        ->click('[aria-label="Select Alpha"]')
        ->click('[aria-label="Select Beta"]')
        ->waitForText('2 selected')
        ->assertAttributeContains('tr:has([aria-label="Select Alpha"])', 'class', 'bg-zinc-50')
        ->click('2 selected')
        ->click('Publish')
        ->waitForText('2 pages published.')
        ->assertAttributeDoesntContain('tr:has([aria-label="Select Alpha"])', 'class', 'bg-zinc-50')
        ->assertNoJavascriptErrors();

    expect($alpha->fresh()->status)->toBe(ContentStatus::PUBLISHED)
        ->and($beta->fresh()->status)->toBe(ContentStatus::PUBLISHED);
});

it('selects a row and deselects it again by double-clicking it', function (): void {
    Page::factory()->create(['title' => 'Alpha']);

    $this->actingAsAdmin();

    $page = visit(route('admin.pages-index'));
    $doubleClickRow = <<<'JS'
        document.querySelector('tr:has([aria-label="Select Alpha"]) td:nth-child(3)').dispatchEvent(new MouseEvent('dblclick', { bubbles: true }))
        JS;

    $page->assertNoJavascriptErrors()->assertDontSee('1 selected');
    $page->script($doubleClickRow);
    $page->waitForText('1 selected')
        ->assertChecked('[aria-label="Select Alpha"]')
        ->assertAttributeContains('tr:has([aria-label="Select Alpha"])', 'class', 'bg-zinc-50');
    $page->script($doubleClickRow);
    $page->assertDontSee('1 selected')
        ->assertNotChecked('[aria-label="Select Alpha"]')
        ->assertNoJavascriptErrors();
});
