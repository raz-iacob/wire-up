<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\Page;

it('signs someone up from the newsletter block and shows the success message', function (): void {
    $this->travelBack();

    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => 'Newsletter',
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'newsletter']);
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => 'newsletter', 'content' => [
            ...BlockType::NEWSLETTER->defaultContent(),
            'heading' => ['en' => '<p>Stay in the loop</p>'],
            'askName' => true,
            'buttonText' => ['en' => 'Join'],
            'successMessage' => ['en' => '<p>You’re on the list!</p>'],
        ]],
    ]);

    $browser = visit(route('page', 'newsletter'));

    $browser->assertNoJavascriptErrors()
        ->assertSee('Stay in the loop')
        ->fill('input[autocomplete="given-name"]', 'Ada')
        ->fill('input[type="email"]', 'ada@example.com')
        ->wait(3.5)
        ->press('Join');

    assertScriptEventually($browser, "document.querySelector('[data-newsletter-success]')?.innerText.includes('on the list') ?? false", true);

    $browser->assertNoJavascriptErrors();

    $this->assertDatabaseHas('submissions', [
        'type' => 'newsletter',
        'name' => 'Ada',
        'email' => 'ada@example.com',
    ]);
});
