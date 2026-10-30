<?php

declare(strict_types=1);

use App\Actions\UpdatePageAction;
use App\Enums\ContentStatus;
use App\Models\NotFoundPath;
use App\Models\Page;
use App\Models\Redirect;

it('sends an old address to its new one with the chosen status and counts the visit', function (): void {
    $permanent = Redirect::factory()->create(['from' => '/old-page', 'to' => '/new-page']);
    Redirect::factory()->create(['from' => '/summer-sale', 'to' => '/offers', 'status_code' => 302]);

    $this->get('/Old-Page/')->assertStatus(301)->assertRedirect(url('/new-page'));
    $this->get('/summer-sale')->assertStatus(302)->assertRedirect(url('/offers'));

    expect($permanent->fresh()->hits)->toBe(1)
        ->and($permanent->fresh()->last_hit_at?->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('matches a query string exactly and otherwise carries the visitor query over', function (): void {
    Redirect::factory()->create(['from' => '/product?id=7', 'to' => '/shop/blue-mug']);
    Redirect::factory()->create(['from' => '/promo', 'to' => '/sale']);
    Redirect::factory()->create(['from' => '/partners', 'to' => '/about?tab=partners']);

    $this->get('/product?id=7')->assertRedirect(url('/shop/blue-mug'));
    $this->get('/product?id=8')->assertNotFound();
    $this->get('/promo?utm_source=mail')->assertRedirect(url('/sale').'?utm_source=mail');
    $this->get('/partners?ref=x')->assertRedirect(url('/about').'?tab=partners');
});

it('moves whole folders with wildcards, preferring the most specific one', function (): void {
    Redirect::factory()->create(['from' => '/blog/*', 'to' => '/news/*']);
    Redirect::factory()->create(['from' => '/docs/*', 'to' => '/help/*']);
    Redirect::factory()->create(['from' => '/docs/api/*', 'to' => '/developers/*']);
    Redirect::factory()->create(['from' => '/old-shop/*', 'to' => '/shop']);
    Redirect::factory()->create(['from' => '/forum/*', 'to' => 'https://community.example.com/*']);

    $this->get('/blog/2019/hello-world')->assertRedirect(url('/news/2019/hello-world'));
    $this->get('/docs/api/auth')->assertRedirect(url('/developers/auth'));
    $this->get('/docs/start')->assertRedirect(url('/help/start'));
    $this->get('/old-shop/blue-mug')->assertRedirect(url('/shop'));
    $this->get('/forum/t/caf%C3%A9')->assertRedirect('https://community.example.com/t/caf%C3%A9');
    $this->get('/blog')->assertNotFound();
});

it('redirects only page requests, never form posts', function (): void {
    Redirect::factory()->create(['from' => '/old-page', 'to' => '/new-page']);

    $this->head('/old-page')->assertRedirect(url('/new-page'));
    $this->post('/old-page')->assertStatus(405);
});

it('redirects an old wordpress address before the bot blocker can ban the visitor', function (): void {
    config(['laravel-bot-block.enable' => true]);
    $_SERVER['HTTP_CF_CONNECTING_IP'] = '8.8.8.8';

    try {
        Redirect::factory()->create(['from' => '/wp-content/uploads/menu.pdf', 'to' => '/menu']);

        $this->get('/wp-content/uploads/menu.pdf')->assertRedirect(url('/menu'));
        $this->get('/wp-content/uploads/other.pdf')->assertForbidden();
    } finally {
        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
    }
});

it('follows a published page to its new address after its slug changes', function (): void {
    $page = Page::factory()->create(['title' => 'Our Story', 'status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'our-story']);

    new UpdatePageAction()->handle($page, ['status' => ContentStatus::PUBLISHED, 'slugs' => ['en' => 'story-of-us']]);

    $this->get('/our-story')->assertStatus(301)->assertRedirect(url('/story-of-us'));
    $this->get('/story-of-us')->assertOk()->assertSee('Our Story');
});

it('logs addresses that are not found, counting repeat visits and keeping the first referrer', function (): void {
    $this->get('/missing-page', ['referer' => 'https://partner.example.com/links'])->assertNotFound();
    $this->get('/Missing-Page/')->assertNotFound();

    $logged = NotFoundPath::query()->sole();

    expect($logged->path)->toBe('/missing-page')
        ->and($logged->hits)->toBe(2)
        ->and($logged->referrer)->toBe('https://partner.example.com/links')
        ->and($logged->last_seen_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('does not log missing admin or system addresses', function (): void {
    $this->actingAsAdmin()->get('/admin/does-not-exist')->assertNotFound();
    $this->get('/img/w=10/'.str_repeat('a', 10).'.jpg')->assertNotFound();

    expect(NotFoundPath::query()->exists())->toBeFalse();
});

it('prunes not found addresses nobody has asked for in a month', function (): void {
    $stale = NotFoundPath::factory()->create(['last_seen_at' => now()->subDays(31)]);
    $recent = NotFoundPath::factory()->create(['last_seen_at' => now()->subDays(29)]);

    $this->artisan('model:prune', ['--model' => [NotFoundPath::class]])->assertSuccessful();

    expect(NotFoundPath::query()->pluck('id')->all())->toBe([$recent->id])
        ->and(NotFoundPath::query()->whereKey($stale->id)->exists())->toBeFalse();
});
