<?php

declare(strict_types=1);

use App\Models\Block;
use App\Models\Page;
use Illuminate\Support\Arr;
use Livewire\Livewire;

it('renders the content editor within the pages edit screen', function (): void {
    $page = Page::factory()->create();

    $this->actingAsAdmin()
        ->get(route('admin.pages-edit', $page))
        ->assertOk()
        ->assertSeeLivewire('admin.content-editor');
});

it('renders each block type partial', function (): void {
    Livewire::test('admin.content-editor', ['locale' => 'en'])
        ->set('blocks', [
            'new-a' => ['id' => 'new-a', 'type' => 'hero', 'content' => ['align' => 'center']],
            'new-b' => ['id' => 'new-b', 'type' => 'text-image', 'content' => ['reverseLayout' => false]],
            'new-c' => ['id' => 'new-c', 'type' => 'spacer', 'content' => ['size' => 'medium']],
        ])
        ->assertSee('Heading')
        ->assertSee('Spacer size')
        ->assertSee('Display image on the right');
});

it('derives the block header title from content', function (): void {
    Livewire::test('admin.content-editor', ['locale' => 'en'])
        ->set('blocks', [
            'new-a' => ['id' => 'new-a', 'type' => 'hero', 'content' => ['heading' => ['en' => 'My splendid hero']]],
            'new-b' => ['id' => 'new-b', 'type' => 'text-image', 'content' => ['body' => ['en' => '<p>Some <strong>rich</strong> text here</p>']]],
            'new-c' => ['id' => 'new-c', 'type' => 'spacer', 'content' => ['size' => 'medium']],
        ])
        ->assertSee('My splendid hero')
        ->assertSee('Some rich text here')
        ->assertDontSee('<strong>', false);
});

it('falls back to the block type label when content text is empty', function (): void {
    Livewire::test('admin.content-editor', ['locale' => 'en'])
        ->set('blocks', [
            'new-a' => ['id' => 'new-a', 'type' => 'hero', 'content' => []],
        ])
        ->assertSee('Hero');
});

it('appends a block when adding', function (): void {
    Livewire::test('admin.content-editor', ['locale' => 'en'])
        ->call('add', 'hero')
        ->assertCount('blocks', 1)
        ->assertSet('blocks', function (array $blocks): bool {
            $block = Arr::first($blocks);

            return $block['type'] === 'hero' && $block['content']['align'] === 'center';
        });
});

it('ignores unknown block types when adding', function (): void {
    Livewire::test('admin.content-editor', ['locale' => 'en'])
        ->call('add', 'bogus')
        ->assertCount('blocks', 0);
});

it('keys blocks by id so nested bindings stay stable', function (): void {
    Livewire::test('admin.content-editor', ['locale' => 'en'])
        ->call('add', 'hero')
        ->assertSet('blocks', fn (array $blocks): bool => array_keys($blocks) === [Arr::first($blocks)['id']]);
});

it('removes the selected block', function (): void {
    Livewire::test('admin.content-editor', ['locale' => 'en'])
        ->set('blocks', [
            'new-a' => ['id' => 'new-a', 'type' => 'hero', 'content' => []],
            'new-b' => ['id' => 'new-b', 'type' => 'spacer', 'content' => []],
        ])
        ->call('confirmRemove', 'new-a')
        ->assertSet('selected', 'new-a')
        ->call('remove')
        ->assertSet('blocks', fn (array $blocks): bool => array_keys($blocks) === ['new-b'])
        ->assertSet('selected', null);
});

it('reorders blocks by id and position', function (): void {
    Livewire::test('admin.content-editor', ['locale' => 'en'])
        ->set('blocks', [
            'new-a' => ['id' => 'new-a', 'type' => 'hero', 'content' => []],
            'new-b' => ['id' => 'new-b', 'type' => 'spacer', 'content' => []],
            'new-c' => ['id' => 'new-c', 'type' => 'text-image', 'content' => []],
        ])
        ->call('reorder', 'new-c', 0)
        ->assertSet('blocks', fn (array $blocks): bool => array_keys($blocks) === ['new-c', 'new-a', 'new-b']);
});

it('persists new blocks with positions when saving the page', function (): void {
    $page = Page::factory()->create(['title' => 'Sample']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->set('title.en', 'Sample')
        ->set('slugs.en', 'sample')
        ->set('blocks', [
            'new-1' => ['id' => 'new-1', 'type' => 'spacer', 'content' => ['size' => 'large']],
            'new-2' => ['id' => 'new-2', 'type' => 'hero', 'content' => ['heading' => ['en' => 'Welcome']]],
        ])
        ->call('update')
        ->assertHasNoErrors();

    expect($page->blocks()->count())->toBe(2);

    $this->assertDatabaseHas('blocks', [
        'blockable_id' => $page->id,
        'blockable_type' => 'page',
        'type' => 'spacer',
        'position' => 0,
    ]);

    $hero = $page->blocks()->where('type', 'hero')->first();
    expect($hero->position)->toBe(1);
    expect($hero->content['heading']['en'])->toBe('Welcome');
});

it('updates existing blocks and deletes removed ones when saving', function (): void {
    $page = Page::factory()->create(['title' => 'Sample']);

    $keep = Block::factory()->create([
        'blockable_id' => $page->id,
        'blockable_type' => 'page',
        'type' => 'hero',
        'position' => 0,
        'content' => ['heading' => ['en' => 'Old']],
    ]);

    $drop = Block::factory()->create([
        'blockable_id' => $page->id,
        'blockable_type' => 'page',
        'type' => 'spacer',
        'position' => 1,
        'content' => ['size' => 'small'],
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->set('title.en', 'Sample')
        ->set('slugs.en', 'sample')
        ->set('blocks', [
            (string) $keep->id => ['id' => (string) $keep->id, 'type' => 'hero', 'content' => ['heading' => ['en' => 'New']]],
        ])
        ->call('update')
        ->assertHasNoErrors();

    expect($page->blocks()->count())->toBe(1);
    expect($keep->fresh()->content['heading']['en'])->toBe('New');

    $this->assertDatabaseMissing('blocks', ['id' => $drop->id]);
});
