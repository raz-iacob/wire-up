<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\UpdateSettingsAction;
use App\Mcp\Support\Pages;
use App\Models\Page;
use App\Services\SettingsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update-menu')]
#[Description('Replace the items of a navigation menu (header, footer, or a custom menu key from get-menus). Items apply to one locale — the default locale unless specified — and replace that locale\'s full list.')]
final class UpdateMenuTool extends Tool
{
    public function handle(Request $request): Response
    {
        $localization = resolve('localization');
        $activeLocales = $localization->getActiveLocaleCodes()->all();

        $validated = $request->validate(
            [
                'menu' => ['required', 'string'],
                'items' => ['present', 'array', 'max:20'],
                'locale' => ['sometimes', 'string', Rule::in($activeLocales)],
                'display' => ['sometimes', 'array'],
                'display.background' => ['sometimes', 'boolean'],
                'display.position' => ['sometimes', 'string', 'in:left,right'],
                'display.sticky' => ['sometimes', 'boolean'],
                'display.mobile' => ['sometimes', 'string', 'in:collapse,hide,toggle'],
            ],
            [
                'menu.required' => 'Pass the menu key. Use get-menus to see the available menus.',
                'items.present' => 'Pass the full list of items the menu should have. An empty list clears the menu.',
                'items.max' => 'A menu may hold at most 20 items.',
                'locale.in' => 'Unknown locale. Active locales: '.implode(', ', $activeLocales).'.',
            ],
        );

        $menus = SettingsService::current()->allMenus();
        $keys = array_column($menus, 'key');
        $index = array_search($validated['menu'], $keys, true);

        if ($index === false) {
            return Response::error("No menu with key \"{$validated['menu']}\". Available menus: ".implode(', ', $keys).'.');
        }

        $items = [];

        foreach (array_values($validated['items']) as $i => $item) {
            $position = $i + 1;
            $normalized = is_array($item) ? $this->normalizeItem($item, $position) : "Item {$position}: each menu item must be an object.";

            if (is_string($normalized)) {
                return Response::error($normalized);
            }

            $items[] = $normalized;
        }

        $locale = $validated['locale'] ?? $localization->getDefaultLocale();
        $menus[$index]['items'][$locale] = $items;

        if (isset($validated['display'])) {
            $menus[$index]['display'] = SettingsService::normalizeMenuDisplay([...$menus[$index]['display'], ...$validated['display']]);
        }

        new UpdateSettingsAction()->handle(['menus' => $menus]);

        return Pages::json(['menu' => $menus[$index]]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'menu' => $schema->string()
                ->description('The menu key: "header", "footer", or a custom menu key from get-menus.')
                ->required(),

            'items' => $schema->array()
                ->items($schema->object())
                ->description('The complete ordered item list: [{"type": "page"|"link"|"heading"|"account", "label": "...", "page": <page id, for type page>, "url": "<https://, /path or #anchor, for type link>", "target": "_self"|"_blank", "appearance": "link"|"button", "badge": "..."}]. An "account" item needs no label/url/page — it auto-shows Log in (and Sign up when registration is open) to visitors and Account to signed-in members.')
                ->required(),

            'locale' => $schema->string()
                ->description('Locale code the items apply to. Defaults to the default locale.'),

            'display' => $schema->object()
                ->description('Display settings: {"background": bool, "position": "left"|"right", "sticky": bool, "mobile": "collapse"|"hide"|"toggle"}.'),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $item
     * @return array<string, mixed>|string
     */
    private function normalizeItem(array $item, int $position): array|string
    {
        $type = $item['type'] ?? 'page';

        if (! in_array($type, ['page', 'link', 'heading', 'account'], true)) {
            return "Item {$position}: unknown type \"{$type}\". Use page, link, heading, or account.";
        }

        if ($type === 'account') {
            $appearance = $item['appearance'] ?? 'link';

            if (! in_array($appearance, ['link', 'button'], true)) {
                return "Item {$position}: appearance must be link or button.";
            }

            return [
                'type' => 'account',
                'appearance' => $appearance,
                'target' => '_self',
                'label' => mb_trim((string) ($item['label'] ?? '')),
                'page_id' => null,
                'url' => '',
                'icon' => '',
                'icon_name' => '',
                'icon_svg' => '',
                'badge' => '',
                'badgeColor' => 'zinc',
            ];
        }

        $label = mb_trim((string) ($item['label'] ?? ''));

        if ($label === '' || mb_strlen($label) > 100) {
            return "Item {$position}: every item needs a label of at most 100 characters.";
        }

        $pageId = null;
        $url = '';

        if ($type === 'page') {
            $pageId = $item['page'] ?? $item['page_id'] ?? null;

            if (! is_numeric($pageId) || ! Page::query()->whereKey((int) $pageId)->exists()) {
                return "Item {$position}: a page item needs the id of an existing page. Use list-pages to find it.";
            }

            $pageId = (int) $pageId;
        }

        if ($type === 'link') {
            $url = mb_trim((string) ($item['url'] ?? ''));

            if (preg_match('/^(https?:\/\/\S+|\/\S*|#\S+)$/', $url) !== 1) {
                return "Item {$position}: a link item needs a url starting with https://, / or #.";
            }
        }

        $target = $item['target'] ?? '_self';

        if (! in_array($target, ['_self', '_blank'], true)) {
            return "Item {$position}: target must be _self or _blank.";
        }

        $appearance = $item['appearance'] ?? 'link';

        if (! in_array($appearance, ['link', 'button'], true)) {
            return "Item {$position}: appearance must be link or button.";
        }

        return [
            'type' => $type,
            'appearance' => $appearance,
            'target' => $target,
            'label' => $label,
            'page_id' => $pageId,
            'url' => $url,
            'icon' => '',
            'icon_name' => '',
            'icon_svg' => '',
            'badge' => mb_trim((string) ($item['badge'] ?? '')),
            'badgeColor' => is_string($item['badgeColor'] ?? null) && $item['badgeColor'] !== '' ? $item['badgeColor'] : 'zinc',
        ];
    }
}
