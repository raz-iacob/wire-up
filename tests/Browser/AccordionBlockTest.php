<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Page;

it('expands accordion items and hides the chevron in plus-minus mode', function (): void {
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => 'Services',
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'services']);
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => 'accordion', 'content' => [
            'heading' => ['en' => '<p>Our Services</p>'],
            'icon' => 'plus-minus',
            'exclusive' => true,
            'items' => [
                ['title' => ['en' => 'PC Service and Support'], 'body' => ['en' => '<p>Repairs and upgrades</p>']],
                ['title' => ['en' => 'Custom PC Builds'], 'body' => ['en' => '<p>Tailored rigs</p>']],
            ],
        ]],
    ]);

    $browser = visit(route('page', 'services'));

    $browser->assertNoJavascriptErrors()
        ->assertSee('Our Services')
        ->assertSee('Repairs and upgrades')
        ->assertDontSee('Tailored rigs')
        ->click('Custom PC Builds')
        ->wait(0.5)
        ->assertSee('Tailored rigs');

    $browser->assertScript(
        "getComputedStyle(document.querySelector('.site-accordion button[data-flux-accordion-heading] > svg')).display",
        'none',
    );
});

it('keeps the chevron visible and themed when an item is open', function (): void {
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => 'Faq',
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'faq']);
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => 'accordion', 'content' => [
            'icon' => 'chevron',
            'items' => [
                ['title' => ['en' => 'First question'], 'body' => ['en' => '<p>Answer</p>']],
            ],
        ]],
    ]);

    $browser = visit(route('page', 'faq'));

    $browser->assertNoJavascriptErrors()
        ->assertScript(
            "(() => {
                const btn = document.querySelector('.site-accordion button[data-flux-accordion-heading][data-open]');
                if (! btn) { return 'no-open-button'; }
                const after = getComputedStyle(btn, '::after');
                const color = after.borderRightColor;
                if (! color || color === 'rgba(0, 0, 0, 0)') { return 'no-indicator'; }
                return color === getComputedStyle(btn).color ? 'themed' : 'mismatch';
            })()",
            'themed',
        );
});
