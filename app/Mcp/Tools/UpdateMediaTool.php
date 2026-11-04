<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\SetMediaPrivacyAction;
use App\Actions\UpdateMediaAction;
use App\Enums\MediaType;
use App\Mcp\Support\MediaPayload;
use App\Mcp\Support\Pages;
use App\Models\Media;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update-media')]
#[Description('Change a file in the media library: its alt text, or whether a document is private. The library alt text is used everywhere the file appears, in blocks and on records, and a change here reaches all of them — unless a block sets its own "metadata.alt". A private document can only be opened by signed-in staff and never appears on the site; images, video and audio cannot be private.')]
final class UpdateMediaTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'media' => ['required', 'integer'],
                'alt_text' => ['nullable', 'string', 'max:255'],
                'private' => ['boolean'],
            ],
            [
                'media.required' => 'Pass "media": the media id. Use list-media to find it.',
                'media.integer' => '"media" must be the media id, a whole number. Use list-media to find it.',
                'alt_text.max' => '"alt_text" may not be longer than 255 characters.',
                'private.boolean' => '"private" must be true or false.',
            ],
        );

        if (! array_key_exists('alt_text', $validated) && ! array_key_exists('private', $validated)) {
            return Response::error('Pass "alt_text", "private" or both — there is nothing else to change.');
        }

        $media = Media::query()->find($validated['media']);

        if ($media === null) {
            return Response::error("No media with id {$validated['media']}. Use list-media to see the library.");
        }

        $private = $validated['private'] ?? null;

        if ($private === true && $media->type !== MediaType::DOCUMENT) {
            return Response::error("Only documents can be private, and \"{$media->filename}\" is a file of type {$media->type->value}.");
        }

        if (array_key_exists('alt_text', $validated)) {
            new UpdateMediaAction()->handle($media, ['alt_text' => $validated['alt_text']]);
        }

        if ($private !== null) {
            new SetMediaPrivacyAction()->handle($media, (bool) $private);
        }

        return Pages::json(['media' => MediaPayload::summary($media->refresh())]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'media' => $schema->integer()
                ->description('The media id, as returned by list-media.')
                ->required(),

            'alt_text' => $schema->string()
                ->description('The new alt text, describing the file for people who cannot see it (max 255 characters). Pass "" to clear it.'),

            'private' => $schema->boolean()
                ->description('true makes a document private, false makes it public again. Documents only.'),
        ];
    }
}
