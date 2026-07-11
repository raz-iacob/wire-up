<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\UpdateSettingsAction;
use App\Mcp\Support\Pages;
use App\Services\SettingsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update-social')]
#[Description('Set the site\'s social profile links, shown in the footer and used in structured data. Pass only the platforms to change; an empty string removes a link.')]
final class UpdateSocialTool extends Tool
{
    public function handle(Request $request): Response
    {
        $platforms = array_map(strval(...), array_keys(config()->array('social.platforms')));

        $validated = $request->validate(
            [
                'links' => ['required', 'array'],
                'links.*' => ['nullable', 'string', 'max:255'],
            ],
            ['links.required' => 'Pass a links object keyed by platform: '.implode(', ', $platforms).'.'],
        );

        $unknown = array_diff(array_keys($validated['links']), $platforms);

        if ($unknown !== []) {
            return Response::error('Unknown platform(s): '.implode(', ', $unknown).'. Valid platforms: '.implode(', ', $platforms).'.');
        }

        foreach ($validated['links'] as $platform => $url) {
            $url = mb_trim((string) $url);

            if ($url !== '' && ! str_starts_with($url, 'https://')) {
                return Response::error("Give the {$platform} link as a full https:// URL.");
            }
        }

        $current = is_array(config('site.social')) ? config('site.social') : [];

        new UpdateSettingsAction()->handle(['social' => [...$current, ...$validated['links']]]);

        return Pages::json(['social' => SettingsService::current()->socialLinks()]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'links' => $schema->object()
                ->description('Profile URLs keyed by platform ('.implode(', ', array_keys(config()->array('social.platforms'))).'), e.g. {"instagram": "https://instagram.com/acme"}. An empty string removes a link.')
                ->required(),
        ];
    }
}
