<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use App\Services\SettingsNavigation;
use Livewire\Livewire;
use Livewire\LivewireManager;

function settingsTab(string $html, string $route): string
{
    preg_match('/<a\b[^>]*href="'.preg_quote(route($route), '/').'"[^>]*>/', $html, $match);

    return $match[0] ?? '';
}

it('groups the settings into seven sections that open their first screen', function (): void {
    $this->actingAs(User::factory()->developer()->create(['active' => true]));

    Livewire::test('admin.sidebar-settings')
        ->assertSeeInOrder(['Site', 'Design', 'Content', 'Integrations', 'Team', 'System', 'Developer'])
        ->assertSee([route('admin.settings-general'), route('admin.settings-design'), route('admin.settings-content-types'), route('admin.settings-integrations'), route('admin.settings-roles'), route('admin.settings-updates'), route('admin.settings-developer')])
        ->assertDontSee([route('admin.settings-identity'), route('admin.settings-menus'), route('admin.settings-backups'), route('admin.settings-errors')]);
});

it('shows the tabs of the current section, with the open screen selected', function (): void {
    $this->actingAsAdmin();

    $html = (string) $this->get(route('admin.settings-identity'))->assertOk()->getContent();

    expect(settingsTab($html, 'admin.settings-identity'))->toContain('data-selected')
        ->and(settingsTab($html, 'admin.settings-general'))->not->toBeEmpty()->not->toContain('data-selected')
        ->and(settingsTab($html, 'admin.settings-social'))->not->toBeEmpty();

    $this->get(route('admin.settings-backups'))->assertSeeInOrder(['Updates', 'Backups', 'Export / Import']);
});

it('hides the sections and tabs a person cannot use', function (): void {
    $role = Role::factory()->create(['key' => 'site-manager', 'abilities' => ['settings.view', 'settings.edit']]);
    $this->actingAs(User::factory()->role($role)->create(['active' => true]));

    $keys = array_column(resolve(SettingsNavigation::class)->sections(), 'key');

    expect($keys)->toBe(['site', 'design', 'content', 'integrations', 'system']);

    $this->actingAsAdmin();

    $integrations = collect(resolve(SettingsNavigation::class)->sections())->firstWhere('key', 'integrations');

    expect(array_column($integrations['tabs'], 'label'))->toBe(['App Integrations', 'Webhooks']);

    config()->set(['site.ai_api_key' => 'sk-test', 'cashier.key' => 'pk_test_x', 'cashier.secret' => 'sk_test_x', 'cashier.webhook.secret' => 'whsec_x']);

    $integrations = collect(resolve(SettingsNavigation::class)->sections())->firstWhere('key', 'integrations');

    expect(array_column($integrations['tabs'], 'label'))->toBe(['App Integrations', 'Webhooks', 'AI Assistant', 'Shop']);
});

it('keeps track of the open screen while Livewire updates the page', function (): void {
    $this->actingAsAdmin();

    $this->partialMock(LivewireManager::class, function ($mock): void {
        $mock->shouldReceive('isLivewireRequest')->andReturn(true);
        $mock->shouldReceive('originalPath')->andReturn('admin/settings/identity');
    });

    $section = resolve(SettingsNavigation::class)->currentSection();

    expect($section['key'] ?? null)->toBe('site')
        ->and(collect($section['tabs'] ?? [])->firstWhere('current', true)['label'] ?? null)->toBe('Identity');
});

it('has no current section outside the settings', function (): void {
    $this->actingAsAdmin();

    $this->get(route('admin.dashboard'))->assertOk();

    expect(resolve(SettingsNavigation::class)->currentSection())->toBeNull();
});
