<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\Pages;
use App\Mcp\Support\SiteSettings;
use App\Services\SettingsService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get-settings')]
#[Description('Get the site identity (title, tagline, homepage), the current design tokens, the active locales, and the valid options for every design setting.')]
final class GetSettingsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $localization = resolve('localization');

        return Pages::json([
            'identity' => SiteSettings::identity(),
            'design' => SiteSettings::design(),
            'social' => SettingsService::current()->socialLinks(),
            'locales' => [
                'default' => $localization->getDefaultLocale(),
                'active' => $localization->getActiveLocaleCodes()->all(),
            ],
            'options' => SiteSettings::options(),
        ]);
    }
}
