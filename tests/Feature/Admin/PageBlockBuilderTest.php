<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Page;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAsAdmin();
});

function blockEditor(): Testable
{
    return Livewire::test('pages::admin.pages-edit', ['page' => Page::factory()->create()]);
}

/**
 * @param  array<int, array<string, mixed>>  $items
 * @return array<string, array{id: string, type: string, position: int, content: array<string, mixed>}>
 */
function blockWith(string $type, array $items = [], string $id = 'b1'): array
{
    return [
        $id => ['id' => $id, 'type' => $type, 'position' => 0, 'content' => ['items' => $items]],
    ];
}

it('returns early when adding an item to a missing block', function (string $method): void {
    $component = blockEditor()->call($method, 'missing-block');

    expect($component->get('blocks'))->toBe([]);
})->with([
    'accordion' => ['addAccordionItem'],
    'testimonial' => ['addTestimonialItem'],
    'sponsor' => ['addSponsorItem'],
    'feature' => ['addFeatureItem'],
    'stat' => ['addStatItem'],
    'team' => ['addTeamItem'],
    'pricing' => ['addPricingItem'],
    'contact field' => ['addContactField'],
]);

it('returns early when removing an item from a missing block', function (string $method): void {
    $component = blockEditor()->call($method, 'missing-block', 0);

    expect($component->get('blocks'))->toBe([]);
})->with([
    'accordion' => ['removeAccordionItem'],
    'sponsor' => ['removeSponsorItem'],
    'feature' => ['removeFeatureItem'],
    'testimonial' => ['removeTestimonialItem'],
    'button' => ['removeButtonItem'],
    'stat' => ['removeStatItem'],
    'team' => ['removeTeamItem'],
    'pricing' => ['removePricingItem'],
]);

it('adds and removes items for each block type', function (string $type, string $addMethod, string $removeMethod): void {
    $component = blockEditor()->set('blocks', blockWith($type));

    $component->call($addMethod, 'b1');
    expect($component->get('blocks.b1.content.items'))->toHaveCount(1);

    $component->call($addMethod, 'b1');
    expect($component->get('blocks.b1.content.items'))->toHaveCount(2);

    $component->call($removeMethod, 'b1', 0);
    expect($component->get('blocks.b1.content.items'))->toHaveCount(1);

    $component->call($removeMethod, 'b1', 5);
    expect($component->get('blocks.b1.content.items'))->toHaveCount(1);
})->with([
    'accordion' => ['accordion', 'addAccordionItem', 'removeAccordionItem'],
    'testimonials' => ['testimonials', 'addTestimonialItem', 'removeTestimonialItem'],
    'sponsors' => ['sponsors', 'addSponsorItem', 'removeSponsorItem'],
    'feature-cards' => ['feature-cards', 'addFeatureItem', 'removeFeatureItem'],
    'stats' => ['stats', 'addStatItem', 'removeStatItem'],
    'team' => ['team', 'addTeamItem', 'removeTeamItem'],
    'pricing' => ['pricing', 'addPricingItem', 'removePricingItem'],
]);

it('caps button items at three', function (): void {
    $existing = [
        ['id' => 'i1', 'text' => [], 'variant' => 'primary', 'link' => ['type' => 'url', 'value' => '', 'newTab' => false]],
        ['id' => 'i2', 'text' => [], 'variant' => 'primary', 'link' => ['type' => 'url', 'value' => '', 'newTab' => false]],
    ];

    $component = blockEditor()->set('blocks', blockWith('buttons', $existing));

    $component->call('addButtonItem', 'b1');
    expect($component->get('blocks.b1.content.items'))->toHaveCount(3);

    $component->call('addButtonItem', 'b1');
    expect($component->get('blocks.b1.content.items'))->toHaveCount(3);

    $component->call('addButtonItem', 'missing');
    expect($component->get('blocks.b1.content.items'))->toHaveCount(3);

    $component->call('removeButtonItem', 'b1', 0);
    expect($component->get('blocks.b1.content.items'))->toHaveCount(2);
});

it('reorders items and ignores blocks without a matching item', function (string $type, string $reorderMethod): void {
    $items = [
        ['id' => 'i1', 'title' => [], 'body' => []],
        ['id' => 'i2', 'title' => [], 'body' => []],
    ];

    $blocks = [
        'no-items' => ['id' => 'no-items', 'type' => 'hero', 'position' => 0, 'content' => []],
        'b1' => ['id' => 'b1', 'type' => $type, 'position' => 1, 'content' => ['items' => $items]],
    ];

    $component = blockEditor()->set('blocks', $blocks);

    $component->call($reorderMethod, 'i2', 0);
    expect($component->get('blocks.b1.content.items.0.id'))->toBe('i2');

    $component->call($reorderMethod, 'unknown-item', 0);
    expect($component->get('blocks.b1.content.items.0.id'))->toBe('i2');
})->with([
    'accordion' => ['accordion', 'reorderAccordionItems'],
    'feature-cards' => ['feature-cards', 'reorderFeatureItems'],
    'sponsors' => ['sponsors', 'reorderSponsorItems'],
    'testimonials' => ['testimonials', 'reorderTestimonialItems'],
    'buttons' => ['buttons', 'reorderButtonItems'],
    'stats' => ['stats', 'reorderStatItems'],
    'team' => ['team', 'reorderTeamItems'],
    'pricing' => ['pricing', 'reorderPricingItems'],
]);

it('syncs media for a block item', function (): void {
    $items = [['id' => 'i1', 'logo' => null, 'name' => [], 'link' => '', 'tier' => '']];
    $component = blockEditor()->set('blocks', blockWith('sponsors', $items));

    $component->call('syncBlockItemMedia', 'b1', 'i1', 'logo', ['id' => 7]);
    expect($component->get('blocks.b1.content.items.0.logo'))->toBe(['id' => 7]);

    $component->call('syncBlockItemMedia', 'missing', 'i1', 'logo', ['id' => 7]);
    $component->call('syncBlockItemMedia', 'b1', 'missing-item', 'logo', ['id' => 9]);
    expect($component->get('blocks.b1.content.items.0.logo'))->toBe(['id' => 7]);
});

it('manages contact form built-in and custom fields', function (): void {
    $component = blockEditor()->set('blocks', blockWith('contact-form'));

    $component->call('addContactBuiltin', 'b1', 'email');
    expect($component->get('blocks.b1.content.fieldOrder'))->toBe(['email'])
        ->and($component->get('blocks.b1.content.fields.email'))->not->toBeNull();

    $component->call('addContactBuiltin', 'b1', 'email');
    expect($component->get('blocks.b1.content.fieldOrder'))->toBe(['email']);

    $component->call('addContactBuiltin', 'b1', 'not-a-field');
    $component->call('addContactBuiltin', 'missing', 'name');
    expect($component->get('blocks.b1.content.fieldOrder'))->toBe(['email']);

    $component->call('addContactField', 'b1');
    $order = $component->get('blocks.b1.content.fieldOrder');
    expect($order)->toHaveCount(2);

    $customId = $component->get('blocks.b1.content.customFields.0.id');
    $component->call('removeContactField', 'b1', $customId);
    expect($component->get('blocks.b1.content.fieldOrder'))->toBe(['email'])
        ->and($component->get('blocks.b1.content.customFields'))->toBe([]);

    $component->call('addContactField', 'missing');
    $component->call('removeContactField', 'missing', 'x');
    expect($component->get('blocks.b1.content.fieldOrder'))->toBe(['email']);
});

it('reorders contact fields and ignores malformed input', function (): void {
    $content = [
        'items' => [],
        'fieldOrder' => ['email', 'name', 'phone'],
    ];
    $blocks = ['b1' => ['id' => 'b1', 'type' => 'contact-form', 'position' => 0, 'content' => $content]];

    $component = blockEditor()->set('blocks', $blocks);

    $component->call('reorderContactFields', 'b1::phone', 0);
    expect($component->get('blocks.b1.content.fieldOrder'))->toBe(['phone', 'email', 'name']);

    $component->call('reorderContactFields', 'no-separator', 0);
    $component->call('reorderContactFields', 'b1::missing-token', 0);
    expect($component->get('blocks.b1.content.fieldOrder'))->toBe(['phone', 'email', 'name']);

    $bare = ['b2' => ['id' => 'b2', 'type' => 'contact-form', 'position' => 0, 'content' => ['items' => []]]];
    $component->set('blocks', $bare);
    $component->call('reorderContactFields', 'b2::email', 0);
    expect($component->get('blocks.b2.content'))->not->toHaveKey('fieldOrder');
});

it('reorders blocks and ignores an unknown block id', function (): void {
    $blocks = [
        'b1' => ['id' => 'b1', 'type' => 'hero', 'position' => 0, 'content' => []],
        'b2' => ['id' => 'b2', 'type' => 'rich-text', 'position' => 1, 'content' => []],
    ];

    $component = blockEditor()->set('blocks', $blocks);

    $component->call('reorderBlocks', 'b2', 0);
    expect(array_keys($component->get('blocks')))->toBe(['b2', 'b1']);

    $component->call('reorderBlocks', 'unknown', 0);
    expect(array_keys($component->get('blocks')))->toBe(['b2', 'b1']);
});

it('deduplicates block anchors with incrementing suffixes on save', function (): void {
    $page = Page::factory()->create(['status' => ContentStatus::DRAFT]);

    $blocks = [
        'b1' => ['id' => 'b1', 'type' => 'rich-text', 'position' => 0, 'content' => ['anchor' => 'intro']],
        'b2' => ['id' => 'b2', 'type' => 'rich-text', 'position' => 1, 'content' => ['anchor' => 'intro']],
        'b3' => ['id' => 'b3', 'type' => 'rich-text', 'position' => 2, 'content' => ['anchor' => 'intro']],
    ];

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->set('blocks', $blocks)
        ->set('status', ContentStatus::DRAFT)
        ->set('publishedLocales', [])
        ->call('update')
        ->assertSet('blocks.b1.content.anchor', 'intro')
        ->assertSet('blocks.b2.content.anchor', 'intro-2')
        ->assertSet('blocks.b3.content.anchor', 'intro-3');
});
