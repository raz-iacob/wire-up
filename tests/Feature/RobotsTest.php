<?php

declare(strict_types=1);

use App\Models\Settings;

it('allows all crawlers in robots.txt by default', function (): void {
    $response = $this->get('/robots.txt');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('User-agent: *')
        ->assertDontSee('Disallow: /');
});

it('blocks all crawlers in robots.txt when search engines are discouraged', function (): void {
    Settings::set(['noindex' => true]);

    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Disallow: /');
});

it('marks the public site indexable by default', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
        ->assertDontSee('noindex', false);
});

it('adds the robots noindex meta tag on the public site when discouraged', function (): void {
    Settings::set(['noindex' => true]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});
