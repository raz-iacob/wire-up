<?php

declare(strict_types=1);

use App\Models\User;

it('logs in as a member, shows the banner on their account and goes back to the admin', function (): void {
    $member = User::factory()->member()->create(['name' => 'Ada Lovelace', 'active' => true]);

    $this->actingAsAdmin();

    $page = visit(route('admin.users-edit', $member));

    $page->assertNoJavascriptErrors()
        ->click('Log in as Ada Lovelace')
        ->waitForText('Viewing the site as Ada Lovelace.')
        ->assertPathIs('/account')
        ->click('Back to the admin')
        ->waitForText('Last login')
        ->assertPathIs("/admin/users/{$member->id}/edit")
        ->assertDontSee('Viewing the site as')
        ->assertNoJavascriptErrors();
});
