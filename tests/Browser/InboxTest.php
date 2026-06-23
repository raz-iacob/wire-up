<?php

declare(strict_types=1);

use App\Models\Submission;

it('lists submissions and opens one, marking it read', function (): void {
    $submission = Submission::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'message' => 'Hello from the inbox test',
        'form_name' => 'Massage enquiry',
    ]);

    $this->actingAsAdmin();

    $page = visit(route('admin.inbox-index'));

    $page->assertNoJavascriptErrors()
        ->assertSee('Inbox')
        ->assertSee('Ada Lovelace')
        ->assertSee('Massage enquiry')
        ->click('Ada Lovelace')
        ->waitForText('Hello from the inbox test')
        ->assertSee('Hello from the inbox test')
        ->assertNoJavascriptErrors();

    expect($submission->fresh()->read_at)->not->toBeNull();
});
