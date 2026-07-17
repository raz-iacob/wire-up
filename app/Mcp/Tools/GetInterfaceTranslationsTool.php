<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\Pages;
use App\Services\UiStrings;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get-interface-translations')]
#[Description('List the interface strings visitors see (sign-in, account, site chrome) that can be translated, the languages to translate into, and any translations already saved. Use this before update-interface-translations.')]
final class GetInterfaceTranslationsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $localization = resolve('localization');
        $active = $localization->getActiveLocales();

        $locales = [];
        foreach (array_keys($active) as $code) {
            if ($code === 'en') {
                continue;
            }

            $locales[] = ['code' => $code, 'name' => is_string($active[$code]['name'] ?? null) ? $active[$code]['name'] : $code];
        }

        $stored = config('site.ui_translations');
        $stored = is_array($stored) ? $stored : [];

        $translations = [];
        foreach ($locales as $locale) {
            $localeMap = is_array($stored[$locale['code']] ?? null) ? $stored[$locale['code']] : [];
            $translations[$locale['code']] = array_filter($localeMap, fn (mixed $value): bool => is_string($value) && $value !== '');
        }

        return Pages::json([
            'locales' => $locales,
            'strings' => UiStrings::strings(),
            'translations' => $translations,
            'hint' => $locales === []
                ? 'There are no languages to translate into — the source strings are English and no other language is active. Enable another language in the site settings first.'
                : 'Translate each entry in "strings" and save it with update-interface-translations(locale, {"English string": "translation"}). Untranslated strings fall back to English.',
        ]);
    }
}
