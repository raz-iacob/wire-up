<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\UpdateSettingsAction;
use App\Mcp\Support\Pages;
use App\Services\SettingsService;
use App\Services\UiStrings;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update-interface-translations')]
#[Description('Set the wording of the interface strings for one language. Pass a map of the exact English source string to the replacement; entries are merged with any existing ones, and an empty value clears one. The site\'s own language counts, so passing "en" rewords what English visitors read rather than translating. Use get-interface-translations first for the strings and languages.')]
final class UpdateInterfaceTranslationsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $targetLocales = SettingsService::current()->interfaceTranslationLocales();

        $validated = $request->validate(
            [
                'locale' => ['required', 'string', Rule::in($targetLocales)],
                'translations' => ['required', 'array'],
            ],
            [
                'locale.required' => 'Pass the "locale" to translate into.',
                'locale.in' => 'Unknown locale. Use one of: '.implode(', ', $targetLocales).'.',
                'translations.required' => 'Pass "translations" as a map of English string to its translation.',
                'translations.array' => 'Pass "translations" as a map of English string to its translation.',
            ],
        );

        $locale = $validated['locale'];
        $catalog = array_flip(UiStrings::strings());

        $stored = config('site.ui_translations');
        $stored = is_array($stored) ? $stored : [];
        $localeMap = is_array($stored[$locale] ?? null) ? $stored[$locale] : [];

        $applied = 0;
        $unknown = [];

        foreach ($validated['translations'] as $english => $translation) {
            if (! is_string($english) || ! array_key_exists($english, $catalog)) {
                $unknown[] = (string) $english;

                continue;
            }

            $value = is_string($translation) ? mb_trim($translation) : '';

            if ($value === '') {
                unset($localeMap[$english]);

                continue;
            }

            $localeMap[$english] = $value;
            $applied++;
        }

        $stored[$locale] = $localeMap;
        $stored = array_filter($stored, fn (mixed $map): bool => is_array($map) && $map !== []);

        new UpdateSettingsAction()->handle(['ui_translations' => $stored]);

        return Pages::json([
            'locale' => $locale,
            'applied' => $applied,
            'unknown' => $unknown,
            'remaining' => count(array_filter(
                UiStrings::strings(),
                fn (string $string): bool => mb_trim((string) ($localeMap[$string] ?? '')) === '',
            )),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'locale' => $schema->string()
                ->description('The language code to set wording for, from get-interface-translations. Any active locale, including the site\'s own.')
                ->required(),

            'translations' => $schema->object()
                ->description('A map of the exact English source string to its replacement, e.g. {"Log in": "Inloggen"} to translate, or {"Made with Wire-Up": "Designed by us"} with locale "en" to reword. Only strings from get-interface-translations are stored; unknown ones are reported back. An empty value clears one.')
                ->required(),
        ];
    }
}
