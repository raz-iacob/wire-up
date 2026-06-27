<?php

declare(strict_types=1);

use App\Enums\BlockType;
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
            'new-d' => ['id' => 'new-d', 'type' => 'divider', 'position' => 3, 'content' => ['size' => 'medium']],
        ])
        ->assertSee('Heading')
        ->assertSee('Spacer size')
        ->assertSee('Thickness')
        ->assertSee('Display image on the right');
});

it('renders the contact form block editor', function (): void {
    editor($this->page)
        ->set('blocks', [
            'cf' => ['id' => 'cf', 'type' => 'contact-form', 'position' => 0, 'content' => BlockType::CONTACT_FORM->defaultContent()],
        ])
        ->assertSee('Layout')
        ->assertSee('Fields')
        ->assertSee('Add field')
        ->assertDontSee('Custom fields')
        ->assertSee('Send submissions to');
});

it('reorders the contact fields', function (): void {
    $component = editor($this->page)
        ->set('blocks', [
            'cf' => ['id' => 'cf', 'type' => 'contact-form', 'position' => 0, 'content' => BlockType::CONTACT_FORM->defaultContent()],
        ]);

    $component->call('reorderContactFields', 'cf::name', 2);

    expect($component->get('blocks')['cf']['content']['fieldOrder'])
        ->toBe(['email', 'message', 'name']);
});

it('adds a missing built-in field through the picker', function (): void {
    $component = editor($this->page)
        ->set('blocks', [
            'cf' => ['id' => 'cf', 'type' => 'contact-form', 'position' => 0, 'content' => BlockType::CONTACT_FORM->defaultContent()],
        ]);

    expect($component->get('blocks')['cf']['content']['fieldOrder'])->not->toContain('phone');

    $component->call('addContactBuiltin', 'cf', 'phone');

    expect($component->get('blocks')['cf']['content']['fieldOrder'])->toBe(['name', 'email', 'message', 'phone']);

    $component->call('addContactBuiltin', 'cf', 'phone');
    expect($component->get('blocks')['cf']['content']['fieldOrder'])->toHaveCount(4);
});

it('removes a built-in field from the order', function (): void {
    $component = editor($this->page)
        ->set('blocks', [
            'cf' => ['id' => 'cf', 'type' => 'contact-form', 'position' => 0, 'content' => BlockType::CONTACT_FORM->defaultContent()],
        ]);

    $component->call('removeContactField', 'cf', 'email');

    expect($component->get('blocks')['cf']['content']['fieldOrder'])->toBe(['name', 'message']);
});

it('adds a custom field to both the list and the field order, then removes it', function (): void {
    $component = editor($this->page)
        ->set('blocks', [
            'cf' => ['id' => 'cf', 'type' => 'contact-form', 'position' => 0, 'content' => BlockType::CONTACT_FORM->defaultContent()],
        ])
        ->call('addContactField', 'cf');

    $custom = $component->get('blocks')['cf']['content']['customFields'];
    expect($custom)->toHaveCount(1)
        ->and($custom[0]['type'])->toBe('text')
        ->and($custom[0]['column'])->toBe('left');

    $fieldId = $custom[0]['id'];
    expect($component->get('blocks')['cf']['content']['fieldOrder'])
        ->toHaveCount(4)
        ->toContain($fieldId);

    $component->call('removeContactField', 'cf', $fieldId);

    $content = $component->get('blocks')['cf']['content'];
    expect($content['customFields'])->toBeEmpty();
    expect($content['fieldOrder'])->not->toContain($fieldId);
});

it('interleaves custom fields with built-in fields in a single order', function (): void {
    $component = editor($this->page)
        ->set('blocks', [
            'cf' => ['id' => 'cf', 'type' => 'contact-form', 'position' => 0, 'content' => BlockType::CONTACT_FORM->defaultContent()],
        ])
        ->call('addContactField', 'cf');

    $fieldId = $component->get('blocks')['cf']['content']['customFields'][0]['id'];

    $component->call('reorderContactFields', 'cf::'.$fieldId, 0);

    expect($component->get('blocks')['cf']['content']['fieldOrder'][0])->toBe($fieldId);
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

it('seeds gallery defaults when adding', function (): void {
    editor($this->page)
        ->call('addBlock', 'gallery')
        ->assertCount('blocks', 1)
        ->assertSet('blocks', function (array $blocks): bool {
            $block = Arr::first($blocks);

            return $block['type'] === 'gallery'
                && $block['content']['media'] === []
                && $block['content']['columns'] === 3
                && $block['content']['lightbox'] === true
                && $block['content']['hasBackground'] === false;
        });
});

it('renders the gallery block partial', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-g' => ['id' => 'new-g', 'type' => 'gallery', 'position' => 0, 'content' => ['media' => [], 'columns' => 3, 'lightbox' => true, 'hasBackground' => false]],
        ])
        ->assertSee('Images &amp; videos', false)
        ->assertSee('Columns')
        ->assertSee('Open media in a lightbox on click');
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

it('shows the block picker options with descriptions and a search input', function (): void {
    $this->get(route('admin.pages-edit', $this->page))
        ->assertOk()
        ->assertSee('Add a block')
        ->assertSee('Full-width banner with a heading, subheading and background image.')
        ->assertSee('Search blocks…')
        ->assertDontSee('Choose a block to add to your page.');
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
                && $content['background']['video'] === null
                && $content['width'] === 'full'
                && $content['height'] === 'auto'
                && $content['verticalAlign'] === 'center'
                && $content['ctaPrimary']['enabled'] === false;
        });
});

it('offers a background video option in the hero editor', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-h' => ['id' => 'new-h', 'type' => 'hero', 'position' => 0, 'content' => BlockType::HERO->defaultContent()],
        ])
        ->assertSee('Background video');
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

it('seeds a full default content structure for an accordion block', function (): void {
    editor($this->page)
        ->call('addBlock', 'accordion')
        ->assertSet('blocks', function (array $blocks): bool {
            $content = Arr::first($blocks)['content'];

            return $content['icon'] === 'chevron'
                && $content['exclusive'] === true
                && $content['hasBackground'] === false
                && count($content['items']) === 1;
        });
});

it('renders the rich text, stats, team and pricing block partials', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-rt' => ['id' => 'new-rt', 'type' => 'rich-text', 'position' => 0, 'content' => BlockType::RICH_TEXT->defaultContent()],
            'new-st' => ['id' => 'new-st', 'type' => 'stats', 'position' => 1, 'content' => BlockType::STATS->defaultContent()],
            'new-tm' => ['id' => 'new-tm', 'type' => 'team', 'position' => 2, 'content' => BlockType::TEAM->defaultContent()],
            'new-pr' => ['id' => 'new-pr', 'type' => 'pricing', 'position' => 3, 'content' => BlockType::PRICING->defaultContent()],
        ])
        ->assertSee('Add stat')
        ->assertSee('Dividers')
        ->assertSee('Add member')
        ->assertSee('Overlay')
        ->assertSee('Add plan')
        ->assertSee('Social links')
        ->assertSee('Highlight this plan');
});

it('adds, removes and reorders stats, team and pricing items', function (): void {
    editor($this->page)
        ->set('blocks', [
            'st' => ['id' => 'st', 'type' => 'stats', 'position' => 0, 'content' => ['columns' => 4, 'items' => [
                ['id' => 'a', 'value' => ['en' => 'A'], 'label' => []],
            ]]],
            'tm' => ['id' => 'tm', 'type' => 'team', 'position' => 1, 'content' => ['columns' => 3, 'items' => [
                ['id' => 'x', 'name' => ['en' => 'X'], 'role' => [], 'bio' => [], 'photo' => null, 'socials' => []],
                ['id' => 'y', 'name' => ['en' => 'Y'], 'role' => [], 'bio' => [], 'photo' => null, 'socials' => []],
            ]]],
            'pr' => ['id' => 'pr', 'type' => 'pricing', 'position' => 2, 'content' => ['columns' => 3, 'items' => []]],
        ])
        ->call('addStatItem', 'st')
        ->assertSet('blocks.st.content.items', fn (array $items): bool => count($items) === 2)
        ->call('removeStatItem', 'st', 0)
        ->assertSet('blocks.st.content.items', fn (array $items): bool => count($items) === 1 && array_keys($items) === [0])
        ->call('reorderTeamItems', 'y', 0)
        ->assertSet('blocks.tm.content.items', fn (array $items): bool => array_column($items, 'id') === ['y', 'x'])
        ->call('addPricingItem', 'pr')
        ->assertSet('blocks.pr.content.items', fn (array $items): bool => count($items) === 1 && $items[0]['featured'] === false)
        ->call('removePricingItem', 'pr', 0)
        ->assertSet('blocks.pr.content.items', fn (array $items): bool => $items === []);
});

it('renders the buttons, audio and downloads block partials', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-btn' => ['id' => 'new-btn', 'type' => 'buttons', 'position' => 0, 'content' => BlockType::BUTTONS->defaultContent()],
            'new-aud' => ['id' => 'new-aud', 'type' => 'audio', 'position' => 1, 'content' => BlockType::AUDIO->defaultContent()],
            'new-dl' => ['id' => 'new-dl', 'type' => 'downloads', 'position' => 2, 'content' => BlockType::DOWNLOADS->defaultContent()],
        ])
        ->assertSee('Add button')
        ->assertSee('Alignment')
        ->assertSee('Audio file')
        ->assertSee('Files');
});

it('adds and removes buttons, capping at three', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-btn' => ['id' => 'new-btn', 'type' => 'buttons', 'position' => 0, 'content' => BlockType::BUTTONS->defaultContent()],
        ])
        ->call('addButtonItem', 'new-btn')
        ->assertSet('blocks.new-btn.content.items', fn (array $items): bool => count($items) === 2)
        ->call('addButtonItem', 'new-btn')
        ->assertSet('blocks.new-btn.content.items', fn (array $items): bool => count($items) === 3)
        ->call('addButtonItem', 'new-btn')
        ->assertSet('blocks.new-btn.content.items', fn (array $items): bool => count($items) === 3)
        ->call('removeButtonItem', 'new-btn', 1)
        ->assertSet('blocks.new-btn.content.items', fn (array $items): bool => count($items) === 2 && array_keys($items) === [0, 1]);
});

it('reorders buttons by id', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-btn' => ['id' => 'new-btn', 'type' => 'buttons', 'position' => 0, 'content' => ['align' => 'center', 'items' => [
                ['id' => 'a', 'text' => ['en' => 'A'], 'variant' => 'primary', 'link' => ['type' => 'url', 'value' => '', 'newTab' => false]],
                ['id' => 'b', 'text' => ['en' => 'B'], 'variant' => 'primary', 'link' => ['type' => 'url', 'value' => '', 'newTab' => false]],
            ]]],
        ])
        ->call('reorderButtonItems', 'b', 0)
        ->assertSet('blocks.new-btn.content.items', fn (array $items): bool => array_column($items, 'id') === ['b', 'a']);
});

it('adds and removes accordion items', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-acc' => ['id' => 'new-acc', 'type' => 'accordion', 'position' => 0, 'content' => ['items' => [['title' => [], 'body' => []]]]],
        ])
        ->call('addAccordionItem', 'new-acc')
        ->assertSet('blocks.new-acc.content.items', fn (array $items): bool => count($items) === 2)
        ->call('addAccordionItem', 'new-acc')
        ->assertSet('blocks.new-acc.content.items', fn (array $items): bool => count($items) === 3)
        ->call('removeAccordionItem', 'new-acc', 1)
        ->assertSet('blocks.new-acc.content.items', fn (array $items): bool => count($items) === 2 && array_keys($items) === [0, 1]);
});

it('reorders accordion items by id', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-acc' => ['id' => 'new-acc', 'type' => 'accordion', 'position' => 0, 'content' => ['items' => [
                ['id' => 'a', 'title' => ['en' => 'A'], 'body' => []],
                ['id' => 'b', 'title' => ['en' => 'B'], 'body' => []],
                ['id' => 'c', 'title' => ['en' => 'C'], 'body' => []],
            ]]],
        ])
        ->call('reorderAccordionItems', 'c', 0)
        ->assertSet('blocks.new-acc.content.items', fn (array $items): bool => array_column($items, 'id') === ['c', 'a', 'b'])
        ->call('reorderAccordionItems', 'c', 2)
        ->assertSet('blocks.new-acc.content.items', fn (array $items): bool => array_column($items, 'id') === ['a', 'b', 'c']);
});

it('backfills ids onto accordion items that lack them', function (): void {
    $page = $this->page;

    Block::factory()->create([
        'blockable_id' => $page->id,
        'blockable_type' => 'page',
        'type' => 'accordion',
        'position' => 0,
        'content' => ['items' => [
            ['title' => ['en' => 'A'], 'body' => []],
            ['title' => ['en' => 'B'], 'body' => []],
        ]],
    ]);

    editor($page)->assertSet('blocks', function (array $blocks): bool {
        $items = Arr::first($blocks)['content']['items'];

        return count($items) === 2
            && is_string($items[0]['id'] ?? null) && $items[0]['id'] !== ''
            && is_string($items[1]['id'] ?? null) && $items[1]['id'] !== ''
            && $items[0]['id'] !== $items[1]['id'];
    });
});

it('renders the accordion block editor fields', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-acc' => ['id' => 'new-acc', 'type' => 'accordion', 'position' => 0, 'content' => ['items' => [['title' => [], 'body' => []]]]],
        ])
        ->assertSee('Add item')
        ->assertSee('Indicator')
        ->assertSee('Only one item open at a time');
});

it('seeds a full default content structure for a testimonials block', function (): void {
    editor($this->page)
        ->call('addBlock', 'testimonials')
        ->assertCount('blocks', 1)
        ->assertSet('blocks', function (array $blocks): bool {
            $content = Arr::first($blocks)['content'];

            return $content['layout'] === 'grid'
                && $content['columns'] === 3
                && $content['hasBackground'] === false
                && $content['intro'] === []
                && count($content['items']) === 1
                && $content['items'][0]['rating'] === 0
                && $content['items'][0]['avatar'] === null;
        });
});

it('renders the testimonials block editor fields', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-t' => ['id' => 'new-t', 'type' => 'testimonials', 'position' => 0, 'content' => BlockType::TESTIMONIALS->defaultContent()],
        ])
        ->assertSee('Add testimonial')
        ->assertSee('Role / company')
        ->assertSee('Rating')
        ->assertSee('Layout')
        ->assertSee('Split')
        ->assertSee('Use gold star ratings')
        ->assertSee('Card background')
        ->assertSee('Card text');
});

it('adds and removes testimonial items', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-t' => ['id' => 'new-t', 'type' => 'testimonials', 'position' => 0, 'content' => ['items' => [['quote' => [], 'author' => []]]]],
        ])
        ->call('addTestimonialItem', 'new-t')
        ->assertSet('blocks.new-t.content.items', fn (array $items): bool => count($items) === 2)
        ->call('addTestimonialItem', 'new-t')
        ->assertSet('blocks.new-t.content.items', fn (array $items): bool => count($items) === 3)
        ->call('removeTestimonialItem', 'new-t', 1)
        ->assertSet('blocks.new-t.content.items', fn (array $items): bool => count($items) === 2 && array_keys($items) === [0, 1]);
});

it('reorders testimonial items by id', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-t' => ['id' => 'new-t', 'type' => 'testimonials', 'position' => 0, 'content' => ['items' => [
                ['id' => 'a', 'author' => ['en' => 'A'], 'quote' => []],
                ['id' => 'b', 'author' => ['en' => 'B'], 'quote' => []],
                ['id' => 'c', 'author' => ['en' => 'C'], 'quote' => []],
            ]]],
        ])
        ->call('reorderTestimonialItems', 'c', 0)
        ->assertSet('blocks.new-t.content.items', fn (array $items): bool => array_column($items, 'id') === ['c', 'a', 'b'])
        ->call('reorderTestimonialItems', 'c', 2)
        ->assertSet('blocks.new-t.content.items', fn (array $items): bool => array_column($items, 'id') === ['a', 'b', 'c']);
});

it('seeds the new testimonial item with the full field shape', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-t' => ['id' => 'new-t', 'type' => 'testimonials', 'position' => 0, 'content' => ['items' => []]],
        ])
        ->call('addTestimonialItem', 'new-t')
        ->assertSet('blocks.new-t.content.items', function (array $items): bool {
            $item = $items[0];

            return is_string($item['id']) && $item['id'] !== ''
                && $item['quote'] === []
                && $item['author'] === []
                && $item['role'] === []
                && $item['avatar'] === null
                && $item['rating'] === 0;
        });
});

it('seeds a full default content structure for a video block', function (): void {
    editor($this->page)
        ->call('addBlock', 'video')
        ->assertCount('blocks', 1)
        ->assertSet('blocks', function (array $blocks): bool {
            $content = Arr::first($blocks)['content'];

            return $content['source'] === 'upload'
                && $content['video'] === null
                && $content['url'] === ''
                && $content['poster'] === null
                && $content['aspect'] === '16:9'
                && $content['autoplay'] === false
                && $content['controls'] === true
                && $content['hasBackground'] === false;
        });
});

it('renders the video block editor fields', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-v' => ['id' => 'new-v', 'type' => 'video', 'position' => 0, 'content' => BlockType::VIDEO->defaultContent()],
        ])
        ->assertSee('Video source')
        ->assertSee('Link / embed')
        ->assertSee('Poster image')
        ->assertSee('Aspect ratio')
        ->assertSee('Autoplay (muted)')
        ->assertSee('Show controls');
});

it('seeds a full default content structure for a sponsors block', function (): void {
    editor($this->page)
        ->call('addBlock', 'sponsors')
        ->assertCount('blocks', 1)
        ->assertSet('blocks', function (array $blocks): bool {
            $content = Arr::first($blocks)['content'];

            return $content['layout'] === 'grid'
                && $content['columns'] === 4
                && $content['hasBackground'] === false
                && $content['grayscale'] === false
                && $content['showNames'] === false
                && $content['intro'] === []
                && count($content['items']) === 1
                && $content['items'][0]['logo'] === null
                && $content['items'][0]['name'] === []
                && $content['items'][0]['link'] === ''
                && $content['items'][0]['tier'] === '';
        });
});

it('renders the sponsors block editor fields', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-s' => ['id' => 'new-s', 'type' => 'sponsors', 'position' => 0, 'content' => BlockType::SPONSORS->defaultContent()],
        ])
        ->assertSee('Add sponsor')
        ->assertSee('Logo')
        ->assertSee('Tier')
        ->assertSee('Layout')
        ->assertSee('Marquee')
        ->assertSee('Grouped by tier')
        ->assertSee('Show sponsor names')
        ->assertSee('Show logos in grayscale (color on hover)');
});

it('adds and removes sponsor items', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-s' => ['id' => 'new-s', 'type' => 'sponsors', 'position' => 0, 'content' => ['items' => [['logo' => null, 'name' => []]]]],
        ])
        ->call('addSponsorItem', 'new-s')
        ->assertSet('blocks.new-s.content.items', fn (array $items): bool => count($items) === 2)
        ->call('addSponsorItem', 'new-s')
        ->assertSet('blocks.new-s.content.items', fn (array $items): bool => count($items) === 3)
        ->call('removeSponsorItem', 'new-s', 1)
        ->assertSet('blocks.new-s.content.items', fn (array $items): bool => count($items) === 2 && array_keys($items) === [0, 1]);
});

it('reorders sponsor items by id', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-s' => ['id' => 'new-s', 'type' => 'sponsors', 'position' => 0, 'content' => ['items' => [
                ['id' => 'a', 'name' => ['en' => 'A'], 'logo' => null],
                ['id' => 'b', 'name' => ['en' => 'B'], 'logo' => null],
                ['id' => 'c', 'name' => ['en' => 'C'], 'logo' => null],
            ]]],
        ])
        ->call('reorderSponsorItems', 'c', 0)
        ->assertSet('blocks.new-s.content.items', fn (array $items): bool => array_column($items, 'id') === ['c', 'a', 'b'])
        ->call('reorderSponsorItems', 'c', 2)
        ->assertSet('blocks.new-s.content.items', fn (array $items): bool => array_column($items, 'id') === ['a', 'b', 'c']);
});

it('seeds the new sponsor item with the full field shape', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-s' => ['id' => 'new-s', 'type' => 'sponsors', 'position' => 0, 'content' => ['items' => []]],
        ])
        ->call('addSponsorItem', 'new-s')
        ->assertSet('blocks.new-s.content.items', function (array $items): bool {
            $item = $items[0];

            return is_string($item['id']) && $item['id'] !== ''
                && $item['logo'] === null
                && $item['name'] === []
                && $item['link'] === ''
                && $item['tier'] === '';
        });
});

it('persists a team member photo through save', function (): void {
    $page = $this->page;

    editor($page)
        ->set('title.en', 'Sample')
        ->set('slugs.en', 'sample')
        ->set('blocks', [
            'new-tm' => ['id' => 'new-tm', 'type' => 'team', 'position' => 0, 'content' => array_replace_recursive(
                BlockType::TEAM->defaultContent(),
                ['items' => [['id' => 'm1', 'name' => ['en' => 'Jane']]]],
            )],
        ])
        ->call('syncBlockItemMedia', 'new-tm', 'm1', 'photo', ['id' => 9, 'source' => 'media/jane.jpg'])
        ->assertSet('blocks.new-tm.content.items.0.photo', ['id' => 9, 'source' => 'media/jane.jpg'])
        ->call('update')
        ->assertHasNoErrors();

    expect($page->blocks()->first()->content['items'][0]['photo'])->toBe(['id' => 9, 'source' => 'media/jane.jpg']);
});

it('syncs item media into the matching block item by id', function (): void {
    editor($this->page)
        ->set('blocks', [
            'b1' => ['id' => 'b1', 'type' => 'testimonials', 'position' => 0, 'content' => ['items' => [
                ['id' => 'one', 'avatar' => null],
                ['id' => 'two', 'avatar' => null],
            ]]],
        ])
        ->call('syncBlockItemMedia', 'b1', 'two', 'avatar', ['id' => 9, 'source' => 'media/a.jpg'])
        ->assertSet('blocks.b1.content.items.1.avatar', ['id' => 9, 'source' => 'media/a.jpg'])
        ->assertSet('blocks.b1.content.items.0.avatar', null);
});

it('ignores item media sync for an unknown block or item', function (): void {
    editor($this->page)
        ->set('blocks', [
            'b1' => ['id' => 'b1', 'type' => 'testimonials', 'position' => 0, 'content' => ['items' => [
                ['id' => 'one', 'avatar' => null],
            ]]],
        ])
        ->call('syncBlockItemMedia', 'b1', 'missing', 'avatar', ['id' => 9])
        ->assertSet('blocks.b1.content.items.0.avatar', null)
        ->call('syncBlockItemMedia', 'nope', 'one', 'avatar', ['id' => 9])
        ->assertSet('blocks.b1.content.items.0.avatar', null);
});

it('renders the photo block editor fields', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-p' => ['id' => 'new-p', 'type' => 'photo', 'position' => 0, 'content' => BlockType::PHOTO->defaultContent()],
        ])
        ->assertSee('Image')
        ->assertSee('Width')
        ->assertSee('Full-bleed')
        ->assertSee('Link (optional)')
        ->assertSee('Use background color');
});

it('renders the feature cards block editor fields', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-f' => ['id' => 'new-f', 'type' => 'feature-cards', 'position' => 0, 'content' => BlockType::FEATURE_CARDS->defaultContent()],
        ])
        ->assertSee('Add card')
        ->assertSee('Image or icon')
        ->assertSee('Title')
        ->assertSee('Description')
        ->assertSee('Columns')
        ->assertSee('Image height')
        ->assertSee('Round the image corners')
        ->assertSee('Show a button')
        ->assertSee('Show cards with a background');
});

it('adds and removes feature card items', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-f' => ['id' => 'new-f', 'type' => 'feature-cards', 'position' => 0, 'content' => ['items' => [['image' => null, 'title' => []]]]],
        ])
        ->call('addFeatureItem', 'new-f')
        ->assertSet('blocks.new-f.content.items', fn (array $items): bool => count($items) === 2)
        ->call('addFeatureItem', 'new-f')
        ->assertSet('blocks.new-f.content.items', fn (array $items): bool => count($items) === 3)
        ->call('removeFeatureItem', 'new-f', 1)
        ->assertSet('blocks.new-f.content.items', fn (array $items): bool => count($items) === 2 && array_keys($items) === [0, 1]);
});

it('reorders feature card items by id', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-f' => ['id' => 'new-f', 'type' => 'feature-cards', 'position' => 0, 'content' => ['items' => [
                ['id' => 'a', 'title' => ['en' => 'A'], 'image' => null],
                ['id' => 'b', 'title' => ['en' => 'B'], 'image' => null],
                ['id' => 'c', 'title' => ['en' => 'C'], 'image' => null],
            ]]],
        ])
        ->call('reorderFeatureItems', 'c', 0)
        ->assertSet('blocks.new-f.content.items', fn (array $items): bool => array_column($items, 'id') === ['c', 'a', 'b'])
        ->call('reorderFeatureItems', 'c', 2)
        ->assertSet('blocks.new-f.content.items', fn (array $items): bool => array_column($items, 'id') === ['a', 'b', 'c']);
});

it('seeds the new feature card item with the full field shape', function (): void {
    editor($this->page)
        ->set('blocks', [
            'new-f' => ['id' => 'new-f', 'type' => 'feature-cards', 'position' => 0, 'content' => ['items' => []]],
        ])
        ->call('addFeatureItem', 'new-f')
        ->assertSet('blocks.new-f.content.items', function (array $items): bool {
            $item = $items[0];

            return is_string($item['id']) && $item['id'] !== ''
                && $item['image'] === null
                && $item['title'] === []
                && $item['body'] === []
                && $item['cta']['enabled'] === false
                && $item['cta']['link']['type'] === 'url';
        });
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

it('renders the page layout options fieldset', function (): void {
    $this->get(route('admin.pages-edit', $this->page))
        ->assertOk()
        ->assertSee('Layout')
        ->assertSee('Background image')
        ->assertSee('Background color')
        ->assertSee('Fixed background')
        ->assertSee('Add custom CSS')
        ->assertSee('Hide site header')
        ->assertSee('Hide site footer');
});

it('seeds default layout options on mount', function (): void {
    editor($this->page)
        ->assertSet('layout.hideHeader', false)
        ->assertSet('layout.hideFooter', false)
        ->assertSet('layout.backgroundColor', null)
        ->assertSet('layout.backgroundImage', null)
        ->assertSet('layout.backgroundFixed', false)
        ->assertSet('layout.customCss', '');
});

it('loads saved layout options from metadata on mount', function (): void {
    $page = Page::factory()->create([
        'metadata' => ['layout' => ['hideHeader' => true, 'customCss' => '.promo { color: red; }']],
    ]);

    editor($page)
        ->assertSet('layout.hideHeader', true)
        ->assertSet('layout.customCss', '.promo { color: red; }')
        ->assertSet('layout.hideFooter', false);
});

it('shows the edit label when custom CSS is already set', function (): void {
    $page = Page::factory()->create([
        'metadata' => ['layout' => ['customCss' => '.promo { color: red; }']],
    ]);

    editor($page)->assertSee('Edit custom CSS');
});

it('persists page layout options into metadata when saving', function (): void {
    $page = $this->page;

    editor($page)
        ->set('title.en', 'Sample')
        ->set('slugs.en', 'sample')
        ->set('layout.hideHeader', true)
        ->set('layout.hideFooter', true)
        ->set('layout.backgroundColor', '#101820')
        ->set('layout.backgroundFixed', true)
        ->set('layout.customCss', '.promo { color: #bada55; }')
        ->call('update')
        ->assertHasNoErrors();

    $layout = Page::query()->whereKey($page->id)->sole()->metadata['layout'];

    expect($layout)->toMatchArray([
        'hideHeader' => true,
        'hideFooter' => true,
        'backgroundColor' => '#101820',
        'backgroundFixed' => true,
        'customCss' => '.promo { color: #bada55; }',
    ]);
});

it('normalizes an empty background color to null when saving', function (): void {
    $page = $this->page;

    editor($page)
        ->set('title.en', 'Sample')
        ->set('slugs.en', 'sample')
        ->set('layout.backgroundColor', '')
        ->call('update')
        ->assertHasNoErrors();

    expect(Page::query()->whereKey($page->id)->sole()->metadata['layout']['backgroundColor'])->toBeNull();
});
