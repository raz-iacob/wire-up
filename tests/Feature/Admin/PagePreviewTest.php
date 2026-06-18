<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAsAdmin();
    $this->page = Page::factory()->create(['title' => 'Sample']);
});

it('caches a snapshot and opens the preview', function (): void {
    $component = Livewire::test('pages::admin.pages-edit', ['page' => $this->page])
        ->set('title.en', 'Sample')
        ->set('blocks', [
            'new-1' => ['id' => 'new-1', 'type' => 'hero', 'position' => 0, 'content' => ['heading' => ['en' => 'Preview heading']]],
        ])
        ->call('preview')
        ->assertSet('showPreview', true);

    $token = $component->get('previewToken');

    expect($token)->not->toBeNull();

    $snapshot = Cache::get("page-preview:{$this->page->id}:".auth()->id().":{$token}");

    expect($snapshot)->toBeArray();
    expect($snapshot['page_id'])->toBe($this->page->id);
    expect($snapshot['blocks'][0]['content']['heading']['en'])->toBe('Preview heading');
});

it('renders unsaved blocks through the front-end on the preview route', function (): void {
    $component = Livewire::test('pages::admin.pages-edit', ['page' => $this->page])
        ->set('title.en', 'Sample')
        ->set('blocks', [
            'new-1' => ['id' => 'new-1', 'type' => 'hero', 'position' => 0, 'content' => ['heading' => ['en' => 'Unsaved hero heading']]],
            'new-2' => ['id' => 'new-2', 'type' => 'text-image', 'position' => 1, 'content' => ['body' => ['en' => '<p>Unsaved body copy</p>']]],
        ])
        ->call('preview');

    $token = $component->get('previewToken');

    $this->get(route('admin.pages-preview', ['page' => $this->page, 'token' => $token]))
        ->assertOk()
        ->assertSee('Unsaved hero heading')
        ->assertSee('Unsaved body copy', false);
});

it('does not persist the previewed blocks', function (): void {
    Livewire::test('pages::admin.pages-edit', ['page' => $this->page])
        ->set('blocks', [
            'new-1' => ['id' => 'new-1', 'type' => 'hero', 'position' => 0, 'content' => ['heading' => ['en' => 'Just a preview']]],
        ])
        ->call('preview');

    expect($this->page->blocks()->count())->toBe(0);
});

it('returns 404 for an unknown token', function (): void {
    $this->get(route('admin.pages-preview', ['page' => $this->page, 'token' => 'does-not-exist']))
        ->assertNotFound();
});

it('returns 404 when the token belongs to a different page', function (): void {
    $other = Page::factory()->create();

    $component = Livewire::test('pages::admin.pages-edit', ['page' => $other])
        ->call('preview');

    $token = $component->get('previewToken');

    $this->get(route('admin.pages-preview', ['page' => $this->page, 'token' => $token]))
        ->assertNotFound();
});

it('redirects non-admins away from the preview route', function (): void {
    $nonAdmin = User::factory()->create(['active' => true, 'admin' => false]);

    $this->actingAs($nonAdmin)
        ->get(route('admin.pages-preview', ['page' => $this->page, 'token' => 'any']))
        ->assertRedirect();
});
