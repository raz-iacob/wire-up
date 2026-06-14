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
