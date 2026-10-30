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
        ->click('Publish')
        ->waitForText('2 pages published.')
        ->assertNoJavascriptErrors();

    expect($alpha->fresh()->status)->toBe(ContentStatus::PUBLISHED)
        ->and($beta->fresh()->status)->toBe(ContentStatus::PUBLISHED);
});
