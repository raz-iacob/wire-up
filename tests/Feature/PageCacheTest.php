<?php

declare(strict_types=1);

use App\Actions\UpdatePageAction;
use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Record;
use App\Models\Settings;
use App\Services\CartService;
use App\Services\PageCache;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

function cachedPage(string $slug = 'cached-test', string $title = 'Cached Test Page'): Page
{
    $page = Page::factory()->create(['title' => $title, 'status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);

    return Page::query()->findOrFail($page->id);
}

it('serves a guest the second visit from the saved copy', function (): void {
    cachedPage();

    $first = $this->get('/cached-test')->assertOk()->assertHeader('X-Page-Cache', 'miss');
    $second = $this->get('/cached-test')->assertOk()->assertHeader('X-Page-Cache', 'hit');

    expect($second->getContent())->toContain('Cached Test Page')
        ->and(str_contains((string) $first->getContent(), 'Cached Test Page'))->toBeTrue();
});

it('gives a cached page the visitor’s own csrf token for livewire', function (): void {
    cachedPage();

    $this->get('/cached-test')->assertHeader('X-Page-Cache', 'miss');

    session()->regenerateToken();
    Livewire::flushState();

    $this->get('/cached-test')
        ->assertHeader('X-Page-Cache', 'hit')
        ->assertSee('data-csrf="'.csrf_token().'"', false);
});

it('never caches for signed-in visitors, guests with a cart or a message, or odd addresses', function (): void {
    $page = cachedPage();

    $this->get('/cached-test?utm_source=news')->assertHeaderMissing('X-Page-Cache');
    $this->get('/cached-test?page=99')->assertHeaderMissing('X-Page-Cache');
    $this->get(URL::signedRoute('page', ['slug' => 'cached-test']))->assertHeaderMissing('X-Page-Cache');
    $this->withSession([CartService::SESSION_KEY => [1 => 1]])->get('/cached-test')->assertHeaderMissing('X-Page-Cache');
    $this->withSession(['_flash' => ['old' => [], 'new' => ['status']], 'status' => 'Saved'])->get('/cached-test')->assertHeaderMissing('X-Page-Cache');

    $this->flushSession();
    $this->actingAsAdmin()->get('/cached-test')->assertHeaderMissing('X-Page-Cache');

    expect($page->exists)->toBeTrue();
});

it('keeps separate copies for each page of a list and for markdown', function (): void {
    cachedPage();

    $this->get('/cached-test?page=2')->assertHeader('X-Page-Cache', 'miss');
    $this->get('/cached-test')->assertHeader('X-Page-Cache', 'miss');
    $this->get('/cached-test', ['Accept' => 'text/markdown'])->assertHeader('X-Page-Cache', 'miss');

    $this->get('/cached-test', ['Accept' => 'text/markdown'])
        ->assertHeader('X-Page-Cache', 'hit')
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->assertSee('# Cached Test Page', false);
});

it('drops the saved copies when content or settings change', function (): void {
    $page = cachedPage();
    $this->get('/cached-test')->assertHeader('X-Page-Cache', 'miss');

    resolve(UpdatePageAction::class)->handle($page, ['title' => ['en' => 'Cached Page Renamed'], 'status' => ContentStatus::PUBLISHED]);

    $this->get('/cached-test')->assertHeader('X-Page-Cache', 'miss')->assertSee('Cached Page Renamed');
    $this->get('/cached-test')->assertHeader('X-Page-Cache', 'hit');

    Settings::set(['contact_email' => 'hello@example.com']);

    $this->get('/cached-test')->assertHeader('X-Page-Cache', 'miss');
});

it('lets a scheduled page appear on time', function (): void {
    cachedPage();
    Page::factory()->create(['status' => ContentStatus::PUBLISHED, 'published_at' => now()->addMinutes(10)]);

    $this->get('/cached-test')->assertHeader('X-Page-Cache', 'miss');
    $this->get('/cached-test')->assertHeader('X-Page-Cache', 'hit');

    $this->travel(11)->minutes();

    $this->get('/cached-test')->assertHeader('X-Page-Cache', 'miss');
});

it('does not keep error pages, redirects or pages with a contact form', function (): void {
    $page = cachedPage();
    $page->updateBlocks([['id' => 'new-1', 'type' => 'contact-form', 'content' => []]]);

    $this->get('/missing-page')->assertNotFound();
    $this->get('/missing-page')->assertNotFound()->assertHeader('X-Page-Cache', 'miss');

    $this->get('/cached-test')->assertOk()->assertHeader('X-Page-Cache', 'miss');
    $this->get('/cached-test')->assertHeader('X-Page-Cache', 'miss');
});

it('clears the copies when an item sells out or comes back, but not for an ordinary stock change', function (): void {
    $record = sellableRecord(attributes: ['stock' => 2]);
    $cache = resolve(PageCache::class);
    $this->get('/cached-test');
    $version = fn (): int => (int) cache()->get(PageCache::VERSION_KEY);
    $before = $version();

    $cache->afterStockChange([$record->id], fn () => Record::query()->whereKey($record->id)->decrement('stock'));
    expect($version())->toBe($before);

    $cache->afterStockChange([$record->id], fn () => Record::query()->whereKey($record->id)->decrement('stock'));
    expect($version())->toBe($before + 1);

    $cache->afterStockChange([$record->id], fn () => Record::query()->whereKey($record->id)->increment('stock'));
    expect($version())->toBe($before + 2);
});

it('turns off with the switch in general settings and can be cleared from there', function (): void {
    cachedPage();
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-general')
        ->assertSet('page_cache', true)
        ->set('page_cache', false)
        ->call('update')
        ->assertHasNoErrors()
        ->call('clearPageCache')
        ->assertDispatched('toast-show');

    expect(Settings::get('page_cache'))->toBeFalse();

    auth()->logout();

    $this->get('/cached-test')->assertHeaderMissing('X-Page-Cache');
});
