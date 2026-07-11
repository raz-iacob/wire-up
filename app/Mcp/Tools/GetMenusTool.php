<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\Pages;
use App\Services\SettingsService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get-menus')]
#[Description('Get the navigation menus (header, footer, and custom) with their display settings and per-locale item lists. Page items reference page ids.')]
final class GetMenusTool extends Tool
{
    public function handle(Request $request): Response
    {
        return Pages::json([
            'menus' => SettingsService::current()->allMenus(),
            'hint' => 'Change a menu with update-menu, passing its key and the full item list.',
        ]);
    }
}
