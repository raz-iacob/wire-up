<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\MediaPayload;
use App\Mcp\Support\Pages;
use App\Models\Media;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list-media')]
#[Description('List media library items (newest first) with the source paths used in block content and the ids used in settings.')]
final class ListMediaTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'type' => ['sometimes', 'string', 'in:image,video,audio,document'],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
            ],
            ['type.in' => 'Type must be one of: image, video, audio, document.'],
        );

        $media = Media::query()
            ->when(isset($validated['type']), fn (Builder $query): Builder => $query->where('type', $validated['type']))
            ->latest('id')
            ->limit((int) ($validated['limit'] ?? 50))
            ->get()
            ->map(fn (Media $item): array => MediaPayload::summary($item));

        return Pages::json(['media' => $media->all()]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum(['image', 'video', 'audio', 'document'])
                ->description('Only return media of this type.'),

            'limit' => $schema->integer()
                ->description('Maximum number of items to return (default 50, max 200).')
                ->default(50),
        ];
    }
}
