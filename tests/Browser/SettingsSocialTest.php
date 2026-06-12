<?php

declare(strict_types=1);

it('renders the social settings screen with all platform fields and no js errors', function (): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-social'));

    $page->assertNoJavascriptErrors()
        ->assertSee('Facebook URL')
        ->assertSee('LinkedIn URL')
        ->assertSee('X (Twitter) URL')
        ->assertSee('YouTube URL')
        ->assertSee('Instagram URL')
        ->assertSee('TikTok URL');
});
