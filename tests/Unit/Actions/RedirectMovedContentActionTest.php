<?php

declare(strict_types=1);

use App\Actions\CreateRecordTypeAction;
use App\Actions\UpdatePageAction;
use App\Actions\UpdateRecordAction;
use App\Actions\UpdateRecordTypeAction;
use App\Enums\ContentStatus;
use App\Enums\RedirectOrigin;
use App\Models\Locale;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Redirect;
use App\Models\Settings;
use Illuminate\Support\Facades\Cache;

function publishedPageAt(string $slug): Page
{
    $page = Page::factory()->create(['status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);

    return $page;
}

/**
 * @return array<string, string>
 */
function redirectMap(): array
{
    return Redirect::query()->orderBy('from')->pluck('to', 'from')->all();
}

it('adds an automatic redirect when a live page changes its address', function (): void {
    $page = publishedPageAt('team');

    new UpdatePageAction()->handle($page, ['status' => ContentStatus::PUBLISHED, 'slugs' => ['en' => 'people']]);

    $redirect = Redirect::query()->sole();

    expect($redirect->from)->toBe('/team')
        ->and($redirect->to)->toBe('/people')
        ->and($redirect->status_code)->toBe(301)
        ->and($redirect->origin)->toBe(RedirectOrigin::AUTOMATIC);
});

it('adds no redirect for drafts, the homepage or saves that keep the address', function (): void {
    $draft = Page::factory()->create(['status' => ContentStatus::DRAFT]);
    $draft->slugs()->create(['locale' => 'en', 'slug' => 'draft-page']);
    $home = publishedPageAt('front-door');
    Settings::set(['home_page_id' => $home->id]);
    $unchanged = publishedPageAt('reach-us');

    new UpdatePageAction()->handle($draft, ['slugs' => ['en' => 'renamed-draft']]);
    new UpdatePageAction()->handle($home, ['status' => ContentStatus::PUBLISHED, 'slugs' => ['en' => 'start-here']]);
    new UpdatePageAction()->handle($unchanged, ['status' => ContentStatus::PUBLISHED, 'slugs' => ['en' => 'reach-us']]);
    new UpdatePageAction()->handle($unchanged, ['status' => ContentStatus::PUBLISHED]);

    expect(Redirect::query()->exists())->toBeFalse();
});

it('keeps one hop when a page moves twice and drops the redirect when it moves back', function (): void {
    $page = publishedPageAt('team');
    $update = fn (string $slug) => new UpdatePageAction()->handle($page, ['status' => ContentStatus::PUBLISHED, 'slugs' => ['en' => $slug]]);

    $update('people');
    $update('crew');

    expect(redirectMap())->toBe(['/people' => '/crew', '/team' => '/crew']);

    $update('team');

    expect(redirectMap())->toBe(['/crew' => '/team', '/people' => '/team']);
});

it('hands an old address back to a new page that takes it', function (): void {
    Redirect::factory()->create(['from' => '/events', 'to' => '/whats-on']);

    $page = Page::factory()->create();
    $page->slugs()->create(['locale' => 'en', 'slug' => 'events']);

    expect(Redirect::query()->exists())->toBeFalse();
});

it('redirects each language that was live at its own address', function (): void {
    Locale::query()->where('code', 'fr')->update(['active' => true]);
    Cache::forget('site-locales');

    $page = Page::factory()->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en', 'fr']],
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'team']);
    $page->slugs()->create(['locale' => 'fr', 'slug' => 'equipe']);

    new UpdatePageAction()->handle($page, ['status' => ContentStatus::PUBLISHED, 'slugs' => ['en' => 'people', 'fr' => 'personnes']]);

    expect(redirectMap())->toBe(['/fr/equipe' => '/fr/personnes', '/team' => '/people']);
});

it('redirects a live record under its content type prefix, but not a type without record pages', function (): void {
    $guides = RecordType::factory()->create(['slug_prefix' => 'guides']);
    $record = Record::factory()->for($guides)->create(['status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()]);
    $record->slugs()->create(['locale' => 'en', 'slug' => 'first-steps', 'base_path' => 'guides']);

    $notes = RecordType::factory()->create(['slug_prefix' => 'notes', 'has_detail_page' => false]);
    $note = Record::factory()->for($notes)->create(['status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()]);
    $note->slugs()->create(['locale' => 'en', 'slug' => 'memo', 'base_path' => 'notes']);

    new UpdateRecordAction()->handle($record, ['status' => ContentStatus::PUBLISHED, 'slugs' => ['en' => 'getting-started']]);
    new UpdateRecordAction()->handle($note, ['status' => ContentStatus::PUBLISHED, 'slugs' => ['en' => 'reminder']]);

    expect(redirectMap())->toBe(['/guides/first-steps' => '/guides/getting-started']);
});

it('moves a renamed content type with a folder redirect and one for its index page', function (): void {
    $type = RecordType::factory()->create(['slug_prefix' => 'products', 'has_index_page' => true]);
    Redirect::factory()->create(['from' => '/shop/*', 'to' => '/old-shop/*']);
    Redirect::factory()->create(['from' => '/shop/lamp', 'to' => '/somewhere']);
    $record = Record::factory()->for($type)->create();
    $record->slugs()->create(['locale' => 'en', 'slug' => 'lamp', 'base_path' => 'products']);

    resolve(UpdateRecordTypeAction::class)->handle($type, ['slug_prefix' => 'shop']);

    expect(redirectMap())->toBe(['/products' => '/shop', '/products/*' => '/shop/*']);
});

it('leaves out the folder redirect when the content type has no record pages', function (): void {
    $type = RecordType::factory()->create(['slug_prefix' => 'faqs', 'has_detail_page' => false, 'has_index_page' => true]);

    resolve(UpdateRecordTypeAction::class)->handle($type, ['slug_prefix' => 'questions']);

    expect(redirectMap())->toBe(['/faqs' => '/questions']);
});

it('takes back an old prefix redirect when a new content type uses that prefix', function (): void {
    Redirect::factory()->create(['from' => '/stories/*', 'to' => '/news/*']);
    Redirect::factory()->create(['from' => '/stories', 'to' => '/news']);

    resolve(CreateRecordTypeAction::class)->handle(['key' => 'story', 'name' => 'Stories', 'slug_prefix' => 'stories', 'icon' => 'book-open', 'fields' => []]);

    expect(Redirect::query()->exists())->toBeFalse();
});
