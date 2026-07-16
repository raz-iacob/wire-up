<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\Records;
use App\Models\RecordType;
use App\Services\RecordTypePresets;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list-content-types')]
#[Description('List every content type (product, service, blog post, and so on) with its key, URL prefix, icon, field blueprint, and record count. Also lists the built-in presets you can create from.')]
final class ListContentTypesTool extends Tool
{
    public function handle(Request $request): Response
    {
        $types = RecordType::query()
            ->orderBy('position')
            ->get()
            ->map(Records::typeSummary(...));

        $presets = array_map(fn (array $preset): array => [
            'key' => $preset['key'],
            'name' => $preset['name'],
            'slug_prefix' => $preset['slug_prefix'],
        ], RecordTypePresets::all());

        return Records::json([
            'content_types' => $types->all(),
            'available_presets' => $presets,
        ]);
    }
}
