<?php

declare(strict_types=1);

use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\GetInterfaceTranslationsTool;
use App\Mcp\Tools\UpdateInterfaceTranslationsTool;
use App\Models\Locale;
use App\Models\Settings;

function mcpActivateLocale(string $code = 'nl'): void
{
    Locale::query()->where('code', $code)->update(['active' => true]);
    cache()->forget('site-locales');
}

it('advertises the interface translation tools with their schema', function (): void {
    expect(resolve(GetInterfaceTranslationsTool::class)->toArray()['name'])->toBe('get-interface-translations')
        ->and(resolve(UpdateInterfaceTranslationsTool::class)->toArray()['name'])->toBe('update-interface-translations')
        ->and(resolve(UpdateInterfaceTranslationsTool::class)->toArray()['inputSchema']['required'])->toBe(['locale', 'translations']);
});

it('reports when there is no language to translate into', function (): void {
    WireUpServer::tool(UpdateInterfaceTranslationsTool::class, ['locale' => 'nl', 'translations' => ['Log in' => 'x']])
        ->assertHasErrors(['There are no non-English languages active to translate into.']);
});

it('lists translatable strings, target languages, and saved translations', function (): void {
    mcpActivateLocale('nl');
    Settings::set(['ui_translations' => ['nl' => ['Log in' => 'Inloggen']]]);

    WireUpServer::tool(GetInterfaceTranslationsTool::class)
        ->assertOk()
        ->assertSee('"code":"nl"')
        ->assertSee('Log in')
        ->assertSee('Inloggen')
        ->assertSee('update-interface-translations');
});

it('reports no languages when only english is active', function (): void {
    WireUpServer::tool(GetInterfaceTranslationsTool::class)
        ->assertOk()
        ->assertSee('"locales":[]')
        ->assertSee('Enable another language');
});

it('saves interface translations for a language and reports unknown strings', function (): void {
    mcpActivateLocale('nl');

    WireUpServer::tool(UpdateInterfaceTranslationsTool::class, [
        'locale' => 'nl',
        'translations' => ['Log in' => 'Inloggen', 'Totally Made Up String' => 'x', 'My account' => ''],
    ])
        ->assertOk()
        ->assertSee('"applied":1')
        ->assertSee('Totally Made Up String');

    expect(Settings::get('ui_translations'))->toBe(['nl' => ['Log in' => 'Inloggen']]);
});

it('clears a translation when saved empty', function (): void {
    mcpActivateLocale('nl');
    Settings::set(['ui_translations' => ['nl' => ['Log in' => 'Inloggen']]]);

    WireUpServer::tool(UpdateInterfaceTranslationsTool::class, ['locale' => 'nl', 'translations' => ['Log in' => '']])
        ->assertOk();

    expect(Settings::get('ui_translations'))->toBe([]);
});

it('rejects a non-translatable locale', function (): void {
    mcpActivateLocale('nl');

    WireUpServer::tool(UpdateInterfaceTranslationsTool::class, ['locale' => 'en', 'translations' => ['Log in' => 'x']])
        ->assertHasErrors(['Unknown or non-translatable locale']);
});

it('requires a locale', function (): void {
    mcpActivateLocale('nl');

    WireUpServer::tool(UpdateInterfaceTranslationsTool::class, ['translations' => ['Log in' => 'x']])
        ->assertHasErrors(['Pass the "locale" to translate into.']);
});
