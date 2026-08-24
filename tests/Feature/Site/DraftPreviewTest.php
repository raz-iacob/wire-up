<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Services\SettingsService;
use Illuminate\Support\Facades\URL;

function draftPage(string $slug = 'unfinished'): Page
{
    $page = Page::factory()->create([
        'title' => 'Work In Progress',
        'status' => ContentStatus::DRAFT,
        'metadata' => ['published_locales' => []],
        'published_at' => null,
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);

    return $page;
}

it('refuses a draft page to a guest without a signature', function (): void {
    draftPage();

    $this->get(route('page', 'unfinished'))->assertNotFound();
});

it('shows a draft page to a guest holding a signed preview link', function (): void {
    $page = draftPage();
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => 'rich-text', 'content' => ['body' => ['en' => '<p>Still being written.</p>']]],
    ]);

    $this->get($page->previewUrl())
        ->assertOk()
        ->assertSee('Still being written.')
        ->assertSee('This page is not published');
});

it('never lets a previewed draft be indexed', function (): void {
    $page = draftPage();

    $this->get($page->previewUrl())
        ->assertOk()
        ->assertSee('noindex, nofollow', false);
});

it('refuses a preview link once it has expired', function (): void {
    $page = draftPage();
    $url = $page->previewUrl();

    $this->travel(config()->integer('wireup.draft_preview_days') + 1)->days();

    $this->get($url)->assertNotFound();
});

it('refuses a preview link whose signature was tampered with', function (): void {
    $page = draftPage();

    $this->get($page->previewUrl().'x')->assertNotFound();
});

it('refuses a preview link minted for a different page', function (): void {
    draftPage('unfinished');
    $other = draftPage('other-draft');

    $tampered = str_replace('other-draft', 'unfinished', $other->previewUrl());

    $this->get($tampered)->assertNotFound();
});

it('shows a draft record to a guest holding a signed preview link', function (): void {
    $type = RecordType::factory()->create(['key' => 'guide', 'slug_prefix' => 'guides', 'fields' => []]);
    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => 'Unreleased Guide'],
        'data' => ['heading' => ['en' => 'Unreleased Guide']],
        'status' => ContentStatus::DRAFT,
        'metadata' => ['published_locales' => []],
        'published_at' => null,
    ]);
    $record->setSlugs();

    $this->get($record->previewUrl())
        ->assertOk()
        ->assertSee('Unreleased Guide');
});

it('opens a members-only draft, because the owner shared the link deliberately', function (): void {
    $page = draftPage();
    $page->update(['metadata' => [...$page->metadata, 'members_only' => true]]);

    expect($page->fresh()?->isMembersOnly())->toBeTrue();

    $this->get($page->previewUrl())->assertOk();
});

it('expires the link after the configured number of days', function (): void {
    config()->set('wireup.draft_preview_days', 1);

    $page = draftPage();

    expect($page->previewUrl())->toContain('expires=')
        ->and(URL::hasValidSignature(request()->create($page->previewUrl())))->toBeTrue();
});

it('signs the homepage preview against the home route, not its slug', function (): void {
    $home = SettingsService::current()->homePage(publishedOnly: false);
    $home->update(['status' => ContentStatus::DRAFT, 'metadata' => ['published_locales' => []]]);

    $link = $home->fresh()?->previewUrl();

    expect($link)->toStartWith(route('home'))
        ->and($link)->toContain('signature=');
});

it('shows a draft homepage through its preview link', function (): void {
    $home = SettingsService::current()->homePage(publishedOnly: false);
    $home->update(['status' => ContentStatus::DRAFT, 'metadata' => ['published_locales' => []]]);
    $home->updateBlocks([
        ['id' => 'new-1', 'type' => 'rich-text', 'content' => ['heading' => ['en' => '<p>Heading XXX</p>']]],
    ]);

    $this->get((string) $home->fresh()?->previewUrl())
        ->assertOk()
        ->assertSee('Heading XXX')
        ->assertSee('This page is not published');
});

it('hides a draft homepage from a guest with no signature', function (): void {
    $home = SettingsService::current()->homePage(publishedOnly: false);
    $home->update(['status' => ContentStatus::DRAFT, 'metadata' => ['published_locales' => []]]);

    $this->get(route('home'))->assertNotFound();
});

it('lets someone who can reach the admin see an unpublished homepage', function (): void {
    $home = SettingsService::current()->homePage(publishedOnly: false);
    $home->update(['status' => ContentStatus::DRAFT, 'metadata' => ['published_locales' => []]]);

    $this->actingAsAdmin();

    $this->get(route('home'))->assertOk()->assertSee('This page is not published');
});

it('still serves a published homepage to a guest', function (): void {
    $this->get(route('home'))->assertOk()->assertDontSee('This page is not published');
});
