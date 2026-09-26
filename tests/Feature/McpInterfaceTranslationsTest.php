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

it('rejects a locale the site does not use', function (): void {
    WireUpServer::tool(UpdateInterfaceTranslationsTool::class, ['locale' => 'nl', 'translations' => ['Log in' => 'x']])
        ->assertHasErrors(['Unknown locale. Use one of: en.']);
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

it('offers the site\'s own language when it is the only one active', function (): void {
    WireUpServer::tool(GetInterfaceTranslationsTool::class)
        ->assertOk()
        ->assertSee('"code":"en"')
        ->assertSee('reword');
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

it('rewords a string in the site\'s own language', function (): void {
    WireUpServer::tool(UpdateInterfaceTranslationsTool::class, [
        'locale' => 'en',
        'translations' => ['Made with Wire-Up' => 'Designed & Developed by Raz'],
    ])->assertOk();

    expect(Settings::get('ui_translations'))->toBe(['en' => ['Made with Wire-Up' => 'Designed & Developed by Raz']]);
});

it('requires a locale', function (): void {
    mcpActivateLocale('nl');

    WireUpServer::tool(UpdateInterfaceTranslationsTool::class, ['translations' => ['Log in' => 'x']])
        ->assertHasErrors(['Pass the "locale" to translate into.']);
});
