<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\Page;

it('submits the contact form and shows the inline success state', function (): void {
    $this->travelBack();

    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => 'Contact us',
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'contact-us']);
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => 'contact-form', 'content' => array_replace_recursive(
            BlockType::CONTACT_FORM->defaultContent(),
            [
                'recipient' => 'owner@example.com',
                'heading' => ['en' => '<p>Get in touch</p>'],
                'successMessage' => ['en' => '<p>Got it, thanks!</p>'],
            ],
        )],
    ]);

    $browser = visit(route('page', 'contact-us'));

    $browser->assertNoJavascriptErrors()
        ->assertSee('Get in touch')
        ->fill('#cf-name', 'Ada Lovelace')
        ->fill('#cf-email', 'ada@example.com')
        ->fill('#cf-message', 'Hello from the browser test')
        ->wait(3.5)
        ->press('Send')
        ->waitForText('Got it, thanks!')
        ->assertNoJavascriptErrors();

    $this->assertDatabaseHas('submissions', [
        'type' => 'contact',
        'email' => 'ada@example.com',
        'message' => 'Hello from the browser test',
    ]);
});
