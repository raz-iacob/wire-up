<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Role;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function palette(?User $user = null): Testable
{
    return $user instanceof User
        ? Livewire::actingAs($user)->test('admin.search')
        : Livewire::test('admin.search');
}

/**
 * @param  array<int, string>  $abilities
 */
function limitedTo(array $abilities): User
{
    return User::factory()->for(Role::factory()->create(['abilities' => $abilities, 'bypass' => false]))->create();
}

beforeEach(function (): void {
    $this->actingAsAdmin();
});

it('offers somewhere to go before anything is typed', function (): void {
    $groups = palette()->get('groups');

    expect(array_column($groups, 'heading'))->toBe(['Go to'])
        ->and($groups[0]['items'])->not->toBeEmpty();
});

it('finds a page by its title', function (): void {
    $page = Page::factory()->create(['title' => 'Pricing and plans']);

    $items = palette()->set('query', 'pricing')->get('groups')[0]['items'];

    expect($items[0]['label'])->toBe('Pricing and plans')
        ->and($items[0]['description'])->toBe('Draft')
        ->and($items[0]['url'])->toBe(route('admin.pages-edit', $page));
});

it('groups records under the name of their content type', function (): void {
    $type = RecordType::factory()->create(['key' => 'guide', 'name' => 'Guides', 'slug_prefix' => 'guides', 'fields' => []]);
    $record = Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Alpha guide']]);

    $groups = collect(palette()->set('query', 'alpha guide')->get('groups'))->firstWhere('heading', 'Guides');

    expect($groups['items'][0]['label'])->toBe('Alpha guide')
        ->and($groups['items'][0]['url'])->toBe(route('admin.records-edit', [$type, $record]));
});

it('finds categories and users', function (): void {
    Category::factory()->create(['name' => ['en' => 'Alpha category']]);
    User::factory()->create(['name' => 'Alpha person', 'email' => 'alpha@wire-up.test']);

    $headings = array_column(palette()->set('query', 'alpha')->get('groups'), 'heading');

    expect($headings)->toContain('Categories')->toContain('Users');
});

it('matches a destination on its keywords, not just its name', function (): void {
    $items = palette()->set('query', 'logo')->get('groups')[0]['items'];

    expect(array_column($items, 'label'))->toContain('Design');
});

it('says nothing was found when nothing matches', function (): void {
    expect(palette()->set('query', 'zzzznothing')->get('groups'))->toBe([]);
});

it('hides content a role cannot reach', function (): void {
    $type = RecordType::factory()->create(['key' => 'guide', 'name' => 'Guides', 'slug_prefix' => 'guides', 'fields' => []]);
    Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Alpha guide']]);
    Page::factory()->create(['title' => 'Alpha page']);
    Category::factory()->create(['name' => ['en' => 'Alpha category']]);
    User::factory()->create(['name' => 'Alpha person', 'email' => 'alpha@wire-up.test']);

    $headings = array_column(palette(limitedTo(['pages.view']))->set('query', 'alpha')->get('groups'), 'heading');

    expect($headings)->toBe(['Pages']);
});

it('hides destinations a role cannot reach', function (): void {
    $labels = array_column(palette(limitedTo(['pages.view']))->get('groups')[0]['items'], 'label');

    expect($labels)->toContain('Pages')
        ->not->toContain('Users')
        ->not->toContain('Design')
        ->not->toContain('Roles');
});

it('puts the palette and its shortcut in the admin header', function (): void {
    $html = (string) $this->get(route('admin.dashboard'))->assertOk()->getContent();

    expect($html)->toContain('admin-search')
        ->toContain('cmd.k')
        ->toContain('⌘K')
        ->toContain('x-persist="admin-search"');
});

it('forgets the term once the palette closes', function (): void {
    $component = palette()->set('query', 'pricing');

    expect($component->get('query'))->toBe('pricing');

    $component->set('query', '');

    expect($component->get('groups'))->toHaveCount(1);
});

it('only offers analytics once reports are configured', function (): void {
    expect(array_column(palette()->get('groups')[0]['items'], 'label'))->not->toContain('Analytics');

    config()->set('services.google_analytics.property_id', '123456');
    config()->set('services.google_analytics.credentials', ['type' => 'service_account']);

    expect(array_column(palette()->get('groups')[0]['items'], 'label'))->toContain('Analytics');
});
