<?php

declare(strict_types=1);

$group = "document.querySelector('[data-wireup-settings-group]')";

it('keeps the settings group open across admin navigations once it is expanded', function () use ($group): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.dashboard'));
    $page->wait(0.4);

    $page->assertScript("{$group}.hasAttribute('data-open')", false);

    $page->script("{$group}.querySelector('button').click(); void 0");
    $page->wait(0.4);

    $page->assertScript("{$group}.hasAttribute('data-open')", true)
        ->assertScript("localStorage.getItem('wireup.sidebar.settings')", 'open');

    $page->click('Pages');
    $page->wait(0.8);

    $page->assertScript("{$group}.hasAttribute('data-open')", true)
        ->assertNoJavascriptErrors();
});

it('leaves the settings group closed across navigations once it is collapsed', function () use ($group): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-general'));
    $page->wait(0.4);

    $page->script("{$group}.querySelector('button').click(); void 0");
    $page->wait(0.4);

    $page->assertScript("{$group}.hasAttribute('data-open')", true);

    $page->script("{$group}.querySelector('button').click(); void 0");
    $page->wait(0.4);

    $page->assertScript("localStorage.getItem('wireup.sidebar.settings')", 'closed');

    $page->click('Dashboard');
    $page->wait(0.8);

    $page->assertScript("{$group}.hasAttribute('data-open')", false)
        ->assertNoJavascriptErrors();
});
