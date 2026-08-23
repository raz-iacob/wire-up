<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\UpdateSettingsAction;
use App\Mcp\Support\Pages;
use App\Services\SettingsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create-menu')]
#[Description('Create a navigation menu beyond the built-in header and footer — for example a documentation sidebar. Returns the menu key to pass to update-menu and to a page\'s layout.sidebar.menus. Header and footer already exist; use update-menu for those.')]
final class CreateMenuTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'key' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
                'position' => ['sometimes', 'string', 'in:left,right'],
                'sticky' => ['sometimes', 'boolean'],
                'background' => ['sometimes', 'boolean'],
                'mobile' => ['sometimes', 'string', 'in:collapse,hide,toggle'],
            ],
            [
                'name.required' => 'Pass a name for the menu, such as "Docs sidebar".',
                'key.regex' => 'The menu key may use lowercase letters, numbers and hyphens only.',
                'position.in' => 'Position must be "left" or "right".',
                'mobile.in' => 'Mobile behaviour must be "collapse", "hide" or "toggle".',
            ],
        );

        $menus = SettingsService::current()->allMenus();
        $existing = array_column($menus, 'key');
        $key = $validated['key'] ?? $this->uniqueKey($validated['name'], $existing);

        if (in_array($key, $existing, true)) {
            return Response::error("A menu with key \"{$key}\" already exists. Use update-menu to change it, or choose a different key.");
        }

        $menus[] = [
            'key' => $key,
            'name' => $validated['name'],
            'builtin' => false,
            'display' => SettingsService::normalizeMenuDisplay([
                'position' => $validated['position'] ?? 'left',
                'sticky' => $validated['sticky'] ?? false,
                'background' => $validated['background'] ?? true,
                'mobile' => $validated['mobile'] ?? 'collapse',
            ]),
            'items' => [],
        ];

        resolve(UpdateSettingsAction::class)->handle(['menus' => $menus]);

        return Pages::json([
            'menu' => ['key' => $key, 'name' => $validated['name']],
            'hint' => "Add items with update-menu using menu \"{$key}\", then show it beside a page with update-page layout.sidebar.menus.",
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->required()
                ->description('Human readable name shown in the admin, e.g. "Docs sidebar".'),
            'key' => $schema->string()
                ->description('Optional stable key used to reference the menu. Derived from the name when omitted.'),
            'position' => $schema->string()
                ->enum(['left', 'right'])
                ->description('Which side of the page content the sidebar sits on. Defaults to left.'),
            'sticky' => $schema->boolean()
                ->description('Keep the menu pinned while the page scrolls.'),
            'background' => $schema->boolean()
                ->description('Draw the menu on a card background. Defaults to true.'),
            'mobile' => $schema->string()
                ->enum(['collapse', 'hide', 'toggle'])
                ->description('How the menu behaves on small screens. Defaults to collapse.'),
        ];
    }

    /**
     * @param  array<int, string>  $existing
     */
    private function uniqueKey(string $name, array $existing): string
    {
        $base = Str::slug($name) ?: 'menu';
        $key = $base;
        $counter = 1;

        while (in_array($key, $existing, true)) {
            $key = $base.'-'.++$counter;
        }

        return $key;
    }
}
