<?php

declare(strict_types=1);

use App\Ai\Agents\SiteAssistant;
use App\Models\Settings;
use App\Models\User;
use App\Services\SettingsService;
use Laravel\Ai\Tools\LoadSkill;
use Laravel\Ai\Tools\Request;
use Livewire\Livewire;

function assistantGuideTool(): LoadSkill
{
    $tools = LoadSkill::mergeInto([], new SiteAssistant);

    return $tools[0];
}

it('gives the assistant the built-in guides', function (string $name): void {
    $tool = assistantGuideTool();

    expect((string) $tool->description())->toContain("- {$name}: Use when")
        ->and((string) $tool->handle(new Request(['name' => $name])))->not->toContain('does not exist');
})->with(['rebuild-site', 'set-up-shop', 'content-types', 'design-and-branding']);

it('gives the assistant the guides the owner wrote', function (): void {
    Settings::set(['assistant_guides' => [
        ['name' => 'house-style', 'title' => 'House style', 'description' => 'Use when writing copy.', 'instructions' => 'Write in Canadian English.'],
    ]]);

    $tool = assistantGuideTool();

    expect((string) $tool->description())->toContain('- house-style: Use when writing copy.')
        ->and((string) $tool->handle(new Request(['name' => 'house-style'])))->toContain('Write in Canadian English.');
});

it('ignores saved guides that are incomplete', function (): void {
    Settings::set(['assistant_guides' => [
        ['name' => 'ok', 'description' => 'Use when testing.', 'instructions' => 'Fine.'],
        ['name' => '', 'description' => 'No name', 'instructions' => 'x'],
        ['name' => 'no-description', 'description' => '', 'instructions' => 'x'],
        'junk',
    ]]);

    expect(SettingsService::current()->assistantGuides())->toBe([
        ['name' => 'ok', 'title' => 'ok', 'description' => 'Use when testing.', 'instructions' => 'Fine.'],
    ]);
});

it('lets the owner write, save and remove guides', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-assistant')
        ->call('addGuide')
        ->set('guides.0.title', ' House style ')
        ->set('guides.0.description', 'Use when writing copy.')
        ->set('guides.0.instructions', 'Write in Canadian English.')
        ->call('update')
        ->assertHasNoErrors();

    expect(SettingsService::current()->assistantGuides())->toBe([
        ['name' => 'house-style', 'title' => 'House style', 'description' => 'Use when writing copy.', 'instructions' => 'Write in Canadian English.'],
    ]);

    Livewire::test('pages::admin.settings-assistant')
        ->assertSet('guides.0.title', 'House style')
        ->call('removeGuide', 0)
        ->call('update');

    expect(SettingsService::current()->assistantGuides())->toBe([]);
});

it('requires every part of a guide', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-assistant')
        ->call('addGuide')
        ->call('update')
        ->assertHasErrors(['guides.0.title' => 'required', 'guides.0.description' => 'required', 'guides.0.instructions' => 'required']);
});

it('refuses a name already used by another guide or a built-in one', function (array $titles): void {
    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.settings-assistant');

    foreach ($titles as $index => $title) {
        $component->call('addGuide')
            ->set("guides.$index.title", $title)
            ->set("guides.$index.description", 'Use when.')
            ->set("guides.$index.instructions", 'Do it.');
    }

    $component->call('update')
        ->assertHasErrors('guides.'.(count($titles) - 1).'.title');

    expect(Settings::get('assistant_guides'))->toBeNull();
})->with([
    'duplicate' => [['Tone', 'tone']],
    'built-in' => [['Set up shop']],
    'nothing usable' => [['!!!']],
]);

it('offers at most twenty guides', function (): void {
    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.settings-assistant');

    foreach (range(1, 22) as $attempt) {
        $component->call('addGuide');
    }

    $component->assertCount('guides', 20);
});

it('forbids changing the guides without the settings edit ability', function (): void {
    $this->actingAs(User::factory()->editor()->create(['active' => true]));

    Livewire::test('pages::admin.settings-assistant')
        ->call('update')
        ->assertForbidden();
});

it('lists the guides page in the settings menu once the assistant is connected', function (): void {
    $this->actingAsAdmin();

    $this->get(route('admin.settings-assistant'))
        ->assertOk()
        ->assertDontSee('href="'.route('admin.settings-assistant').'"', false);

    config(['site.ai_api_key' => 'sk-test']);

    $this->get(route('admin.settings-assistant'))
        ->assertSee('href="'.route('admin.settings-assistant').'"', false);
});
