<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\UpdatePageTool;
use App\Models\Locale;
use App\Models\Media;
use App\Models\Page;
use App\Services\SeoService;
use Illuminate\Support\Facades\Cache;

function pageTitled(string $title): Page
{
    return Page::query()
        ->whereHas('translations', fn ($query) => $query->where('key', 'title')->where('locale', 'en')->where('body', $title))
        ->firstOrFail();
}

it('advertises the update-page tool with its schema', function (): void {
    $tool = resolve(UpdatePageTool::class)->toArray();

    expect($tool['name'])->toBe('update-page')
        ->and($tool['inputSchema']['required'])->toBe(['page'])
        ->and($tool['inputSchema']['properties'])->toHaveKeys(['page', 'title', 'description', 'slug', 'noindex', 'og_image'])
        ->and(WireUpServer::TOOLS)->toContain(UpdatePageTool::class);
});

it('replaces the meta description the installer left on a page', function (): void {
    $page = pageTitled('Home');

    expect($page->description)->toBe('Welcome to our website!');

    WireUpServer::tool(UpdatePageTool::class, [
        'page' => $page->id,
        'description' => 'Handmade ceramics, fired in Rotterdam.',
    ])
        ->assertOk()
        ->assertSee('Handmade ceramics, fired in Rotterdam.');

    expect($page->refresh()->description)->toBe('Handmade ceramics, fired in Rotterdam.');
});

it('renames a page and changes its web address, leaving blocks and status alone', function (): void {
    $page = Page::factory()->create(['title' => 'Old Name', 'description' => 'Still accurate.']);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'old-name']);
    $page->updateBlocks([['id' => 'new-1', 'type' => 'rich-text', 'content' => ['body' => ['en' => '<p>Keep me</p>']]]]);

    WireUpServer::tool(UpdatePageTool::class, [
        'page' => $page->id,
        'title' => 'New Name',
        'slug' => 'new-name',
    ])
        ->assertOk()
        ->assertSee('New Name')
        ->assertSee('new-name');

    $page->refresh();

    expect($page->title)->toBe('New Name')
        ->and($page->getSlug())->toBe('new-name')
        ->and($page->description)->toBe('Still accurate.')
        ->and($page->status)->toBe(ContentStatus::DRAFT)
        ->and($page->blocks()->count())->toBe(1);
});

it('clears the description when passed an empty string', function (): void {
    $page = Page::factory()->create(['title' => 'Wordy Page', 'description' => 'Too much meta.']);

    WireUpServer::tool(UpdatePageTool::class, ['page' => $page->id, 'description' => ''])
        ->assertOk()
        ->assertSee('"description":""');

    expect($page->refresh()->description)->toBe('');
});

it('keeps the other languages when renaming a page', function (): void {
    Locale::query()->whereIn('code', ['en', 'nl'])->update(['active' => true]);
    Cache::forget('site-locales');

    $page = Page::factory()->create(['title' => ['en' => 'English Title', 'nl' => 'Nederlandse Titel']]);

    WireUpServer::tool(UpdatePageTool::class, ['page' => $page->id, 'title' => 'Renamed In English'])
        ->assertOk();

    $page->refresh()->load('translations');

    expect($page->translationsFor('title'))->toMatchArray([
        'en' => 'Renamed In English',
        'nl' => 'Nederlandse Titel',
    ]);
});

it('toggles noindex without losing the rest of the page metadata', function (): void {
    $page = pageTitled('Welcome');

    expect($page->isNoindex())->toBeTrue();

    WireUpServer::tool(UpdatePageTool::class, ['page' => $page->id, 'noindex' => false])
        ->assertOk()
        ->assertSee('"noindex":false');

    $page->refresh();

    expect($page->isNoindex())->toBeFalse()
        ->and($page->resolvedLayout()['hideHeader'])->toBeTrue()
        ->and($page->published_locales)->toBe(['en']);
});

it('sets and clears the social sharing image', function (): void {
    $page = Page::factory()->create(['title' => 'Shareable']);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'shareable']);
    $media = Media::factory()->create(['type' => MediaType::IMAGE, 'width' => 1600, 'height' => 900]);

    WireUpServer::tool(UpdatePageTool::class, ['page' => $page->id, 'og_image' => $media->id])
        ->assertOk()
        ->assertSee('"og_image":'.$media->id);

    expect(SeoService::current()->ogImageUrl($page->refresh()))->toContain($media->source);

    WireUpServer::tool(UpdatePageTool::class, ['page' => $page->id, 'og_image' => null])
        ->assertOk()
        ->assertSee('"og_image":null');

    expect($page->refresh()->media()->wherePivot('role', 'og_image')->count())->toBe(0);
});

it('refuses a social sharing image that is not an image', function (): void {
    $page = Page::factory()->create(['title' => 'Wrong Media']);
    $media = Media::factory()->create(['type' => MediaType::DOCUMENT]);

    WireUpServer::tool(UpdatePageTool::class, ['page' => $page->id, 'og_image' => $media->id])
        ->assertHasErrors()
        ->assertSee('Media id '.$media->id.' is not an image.');

    expect($page->refresh()->media()->count())->toBe(0);
});

it('keeps a scheduled page scheduled for the same date', function (): void {
    $page = Page::factory()->create([
        'title' => 'Future Announcement',
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->addWeek(),
    ]);

    WireUpServer::tool(UpdatePageTool::class, ['page' => $page->id, 'description' => 'Coming soon.'])
        ->assertOk();

    $page->refresh();

    expect($page->computed_status)->toBe(ContentStatus::SCHEDULED)
        ->and($page->published_at?->toAtomString())->toBe(now()->addWeek()->toAtomString())
        ->and($page->description)->toBe('Coming soon.');
});

it('returns a friendly error when updating an unknown page', function (): void {
    WireUpServer::tool(UpdatePageTool::class, ['page' => 999999, 'title' => 'Nowhere'])
        ->assertHasErrors(['No page with id 999999. Use list-pages to see the available pages.']);
});

it('asks for at least one field to change', function (): void {
    $page = Page::factory()->create(['title' => 'Untouched']);

    WireUpServer::tool(UpdatePageTool::class, ['page' => $page->id])
        ->assertHasErrors(['Pass at least one of title, description, slug, noindex or og_image to change.']);
});

it('refuses a title that another page already uses', function (): void {
    $taken = Page::factory()->create(['title' => 'Taken Title']);
    $page = Page::factory()->create(['title' => 'Free Title']);

    WireUpServer::tool(UpdatePageTool::class, ['page' => $page->id, 'title' => 'Taken Title'])
        ->assertHasErrors()
        ->assertSee('Another page is already titled "Taken Title" (id '.$taken->id.')');

    expect($page->refresh()->title)->toBe('Free Title');
});

it('accepts its own title unchanged', function (): void {
    $page = Page::factory()->create(['title' => 'Same Title']);

    WireUpServer::tool(UpdatePageTool::class, ['page' => $page->id, 'title' => 'Same Title', 'description' => 'New meta.'])
        ->assertOk();

    expect($page->refresh()->description)->toBe('New meta.');
});

it('rejects a web address that is not a slug', function (): void {
    $page = Page::factory()->create(['title' => 'Slug Rules']);

    WireUpServer::tool(UpdatePageTool::class, ['page' => $page->id, 'slug' => 'Not A Slug!'])
        ->assertHasErrors()
        ->assertSee('The web address can only use lowercase letters, numbers and hyphens.');
});

it('rejects a meta description longer than 160 characters', function (): void {
    $page = Page::factory()->create(['title' => 'Long Meta']);

    WireUpServer::tool(UpdatePageTool::class, ['page' => $page->id, 'description' => str_repeat('a', 161)])
        ->assertHasErrors()
        ->assertSee('may not be longer than 160 characters');
});

it('validates the page id', function (): void {
    WireUpServer::tool(UpdatePageTool::class, ['page' => 'nope', 'title' => 'Whatever'])
        ->assertHasErrors()
        ->assertSee('The page id must be an integer.');
});
