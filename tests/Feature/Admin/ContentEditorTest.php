<?php

declare(strict_types=1);

use App\Models\Block;
use App\Models\Page;
use Illuminate\Support\Arr;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAsAdmin();
    $this->page = Page::factory()->create(['title' => 'Sample']);
});

function editor(Page $page): Testable
{
    return Livewire::test('pages::admin.pages-edit', ['page' => $page]);
}

it('renders the block editor on the pages edit screen', function (): void {
    $this->get(route('admin.pages-edit', $this->page))
        ->assertOk()
        ->assertSee('Add block');
});

it('renders each block type partial', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-a' => ['id' => 'new-a', 'type' => 'hero', 'position' => 0, 'content' => ['align' => 'center']],
            'new-b' => ['id' => 'new-b', 'type' => 'text-image', 'position' => 1, 'content' => ['reverseLayout' => false]],
            'new-c' => ['id' => 'new-c', 'type' => 'spacer', 'position' => 2, 'content' => ['size' => 'medium']],
        ])
        ->assertSee('Heading')
        ->assertSee('Spacer size')
        ->assertSee('Display image on the right');
});

it('derives the block header title from content', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-a' => ['id' => 'new-a', 'type' => 'hero', 'position' => 0, 'content' => ['heading' => ['en' => 'My splendid hero']]],
            'new-b' => ['id' => 'new-b', 'type' => 'text-image', 'position' => 1, 'content' => ['heading' => ['en' => '<p>Some <strong>rich</strong> heading here</p>'], 'body' => ['en' => '<p>Body copy</p>']]],
        ])
        ->assertSee('My splendid hero')
        ->assertSee('Some rich heading here')
        ->assertDontSee('<strong>', false);
});

it('falls back to the block type label when content text is empty', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-a' => ['id' => 'new-a', 'type' => 'hero', 'position' => 0, 'content' => []],
        ])
        ->assertSee('Hero');
});

it('derives the text-image header title from the heading, not the body', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-a' => ['id' => 'new-a', 'type' => 'text-image', 'position' => 0, 'content' => ['body' => ['en' => '<p>Body only copy</p>']]],
        ])
        ->assertSee('Text + Image')
        ->assertDontSee('Body only copy');
});

it('appends a block when adding', function (): void {
    editor($this->page)
        ->call('addBlock', 'hero')
        ->assertCount('blocks', 1)
        ->assertSet('blocks', function (array $blocks): bool {
            $block = Arr::first($blocks);

            return $block['type'] === 'hero' && $block['content']['align'] === 'center';
        });
});

it('ignores unknown block types when adding', function (): void {
    editor($this->page)
        ->call('addBlock', 'bogus')
        ->assertCount('blocks', 0);
});

it('inserts a block at the chosen position and renumbers', function (): void {
    $component = editor($this->page)
        ->set('blocks', [
            'new-a' => ['id' => 'new-a', 'type' => 'spacer', 'position' => 0, 'content' => []],
            'new-b' => ['id' => 'new-b', 'type' => 'spacer', 'position' => 1, 'content' => []],
        ])
        ->call('openBlockPicker', 1)
        ->assertSet('insertPosition', 1)
        ->call('addBlock', 'hero')
        ->assertSet('insertPosition', null);

    $blocks = array_values($component->get('blocks'));

    expect($blocks)->toHaveCount(3);
    expect($blocks[0]['id'])->toBe('new-a');
    expect($blocks[1]['type'])->toBe('hero');
    expect($blocks[1]['position'])->toBe(1);
    expect($blocks[2]['id'])->toBe('new-b');
    expect($blocks[2]['position'])->toBe(2);
});

it('shows the block picker options with descriptions', function (): void {
    $this->get(route('admin.pages-edit', $this->page))
        ->assertOk()
        ->assertSee('Add a block')
        ->assertSee('Full-width banner with a heading, subheading and background image.');
});

it('keys blocks by id so nested bindings stay stable', function (): void {
    editor($this->page)
        ->call('addBlock', 'hero')
        ->assertSet('blocks', fn (array $blocks): bool => array_keys($blocks) === [Arr::first($blocks)['id']]);
});

it('removes the selected block', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-a' => ['id' => 'new-a', 'type' => 'hero', 'position' => 0, 'content' => []],
            'new-b' => ['id' => 'new-b', 'type' => 'spacer', 'position' => 1, 'content' => []],
        ])
        ->call('confirmRemoveBlock', 'new-a')
        ->assertSet('selectedBlock', 'new-a')
        ->call('removeBlock')
        ->assertSet('blocks', fn (array $blocks): bool => array_keys($blocks) === ['new-b'])
        ->assertSet('selectedBlock', null);
});

it('reorders blocks and renumbers positions', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-a' => ['id' => 'new-a', 'type' => 'hero', 'position' => 0, 'content' => []],
            'new-b' => ['id' => 'new-b', 'type' => 'spacer', 'position' => 1, 'content' => []],
            'new-c' => ['id' => 'new-c', 'type' => 'text-image', 'position' => 2, 'content' => []],
        ])
        ->call('reorderBlocks', 'new-c', 0)
        ->assertSet('blocks', fn (array $blocks): bool => array_keys($blocks) === ['new-c', 'new-a', 'new-b']
            && $blocks['new-c']['position'] === 0
            && $blocks['new-a']['position'] === 1
            && $blocks['new-b']['position'] === 2);
});

it('persists reordered blocks when saving', function (): void {
    $page = $this->page;

    editor($page)
        ->set('title.en', 'Sample')
        ->set('slugs.en', 'sample')
        ->set('blocks', [
            'new-1' => ['id' => 'new-1', 'type' => 'spacer', 'position' => 0, 'content' => ['size' => 'large']],
            'new-2' => ['id' => 'new-2', 'type' => 'hero', 'position' => 1, 'content' => ['heading' => ['en' => 'Welcome']]],
        ])
        ->call('reorderBlocks', 'new-2', 0)
        ->call('update')
        ->assertHasNoErrors();

    $ordered = $page->blocks()->get();

    expect($ordered)->toHaveCount(2);
    expect($ordered[0]->type->value)->toBe('hero');
    expect($ordered[0]->position)->toBe(0);
    expect($ordered[1]->type->value)->toBe('spacer');
});

it('updates existing blocks and deletes removed ones when saving', function (): void {
    $page = $this->page;

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

    editor($page)
        ->set('title.en', 'Sample')
        ->set('slugs.en', 'sample')
        ->set('blocks', [
            (string) $keep->id => ['id' => (string) $keep->id, 'type' => 'hero', 'position' => 0, 'content' => ['heading' => ['en' => 'New']]],
        ])
        ->call('update')
        ->assertHasNoErrors();

    expect($page->blocks()->count())->toBe(1);
    expect($keep->fresh()->content['heading']['en'])->toBe('New');

    $this->assertDatabaseMissing('blocks', ['id' => $drop->id]);
});

it('seeds a full default content structure for a hero block', function (): void {
    editor($this->page)
        ->call('addBlock', 'hero')
        ->assertSet('blocks', function (array $blocks): bool {
            $content = Arr::first($blocks)['content'];

            return $content['background']['type'] === 'image'
                && $content['width'] === 'full'
                && $content['height'] === 'auto'
                && $content['verticalAlign'] === 'center'
                && $content['ctaPrimary']['enabled'] === false;
        });
});

it('backfills missing default content for existing hero blocks', function (): void {
    $page = $this->page;

    $block = Block::factory()->create([
        'blockable_id' => $page->id,
        'blockable_type' => 'page',
        'type' => 'hero',
        'position' => 0,
        'content' => ['heading' => ['en' => 'Legacy']],
    ]);

    editor($page)->assertSet('blocks', function (array $blocks) use ($block): bool {
        $content = $blocks[(string) $block->id]['content'];

        return $content['background']['type'] === 'image'
            && $content['width'] === 'full'
            && $content['heading']['en'] === 'Legacy';
    });
});

it('seeds a full default content structure for a text-image block', function (): void {
    editor($this->page)
        ->call('addBlock', 'text-image')
        ->assertSet('blocks', function (array $blocks): bool {
            $content = Arr::first($blocks)['content'];

            return $content['reverseLayout'] === false
                && $content['hasBackground'] === false
                && $content['ctaPrimary']['enabled'] === false
                && $content['ctaSecondary']['enabled'] === false;
        });
});

it('seeds a full default content structure for a location block', function (): void {
    editor($this->page)
        ->call('addBlock', 'location')
        ->assertSet('blocks', function (array $blocks): bool {
            $content = Arr::first($blocks)['content'];

            return $content['map'] === ''
                && $content['phone'] === ''
                && $content['email'] === ''
                && $content['reverseLayout'] === false
                && $content['hasBackground'] === false
                && $content['directions']['enabled'] === false;
        });
});

it('renders the location block editor fields', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-loc' => ['id' => 'new-loc', 'type' => 'location', 'position' => 0, 'content' => ['map' => '']],
        ])
        ->assertSee('Map address or embed URL')
        ->assertSee('Opening hours')
        ->assertSee('Display map on the right');
});

it('backfills missing default content for existing text-image blocks', function (): void {
    $page = $this->page;

    $block = Block::factory()->create([
        'blockable_id' => $page->id,
        'blockable_type' => 'page',
        'type' => 'text-image',
        'position' => 0,
        'content' => ['body' => ['en' => '<p>Legacy</p>'], 'reverseLayout' => true],
    ]);

    editor($page)->assertSet('blocks', function (array $blocks) use ($block): bool {
        $content = $blocks[(string) $block->id]['content'];

        return $content['hasBackground'] === false
            && $content['ctaPrimary']['enabled'] === false
            && $content['reverseLayout'] === true
            && $content['body']['en'] === '<p>Legacy</p>';
    });
});

it('clears a cta link value when its link type changes', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-a' => ['id' => 'new-a', 'type' => 'hero', 'position' => 0, 'content' => [
                'ctaPrimary' => ['enabled' => true, 'link' => ['type' => 'anchor', 'value' => '#contact']],
            ]],
        ])
        ->set('blocks.new-a.content.ctaPrimary.link.type', 'page')
        ->assertSet('blocks.new-a.content.ctaPrimary.link.value', '');
});

it('clears a text-image cta link value when its link type changes', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-a' => ['id' => 'new-a', 'type' => 'text-image', 'position' => 0, 'content' => [
                'ctaSecondary' => ['enabled' => true, 'link' => ['type' => 'anchor', 'value' => '#contact']],
            ]],
        ])
        ->set('blocks.new-a.content.ctaSecondary.link.type', 'url')
        ->assertSet('blocks.new-a.content.ctaSecondary.link.value', '');
});

it('slugifies and de-duplicates block anchors on save', function (): void {
    $page = $this->page;

    editor($page)
        ->set('title.en', 'Sample')
        ->set('slugs.en', 'sample')
        ->set('blocks', [
            'new-a' => ['id' => 'new-a', 'type' => 'hero', 'position' => 0, 'content' => ['anchor' => 'Contact Us!']],
            'new-b' => ['id' => 'new-b', 'type' => 'text-image', 'position' => 1, 'content' => ['anchor' => 'Contact Us!']],
            'new-c' => ['id' => 'new-c', 'type' => 'spacer', 'position' => 2, 'content' => ['size' => 'medium']],
        ])
        ->call('update')
        ->assertHasNoErrors();

    $blocks = $page->blocks()->get();

    expect($blocks[0]->content['anchor'])->toBe('contact-us')
        ->and($blocks[1]->content['anchor'])->toBe('contact-us-2')
        ->and($blocks[2]->content)->not->toHaveKey('anchor');
});

it('drops published locales that are no longer active when loading', function (): void {
    $active = array_keys(resolve('localization')->getActiveLocales());

    $page = Page::factory()->create([
        'metadata' => ['published_locales' => [...$active, 'zz']],
    ]);

    editor($page)->assertSet('publishedLocales', $active);
});

it('does not add an anchor field to spacer blocks', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-a' => ['id' => 'new-a', 'type' => 'spacer', 'position' => 0, 'content' => ['size' => 'medium']],
        ])
        ->assertDontSee('Link directly to this block');
});
