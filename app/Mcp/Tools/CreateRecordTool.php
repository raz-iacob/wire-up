<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\CreateRecordAction;
use App\Actions\UpdateRecordAction;
use App\Mcp\Support\Pages;
use App\Mcp\Support\Records;
use App\Models\RecordType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create-record')]
#[Description('Create a record of a content type — a product, service, blog post, and so on. Fill its custom fields with "data", attach media by id, and add content blocks. Records are created as drafts unless publish is true. Returns the new record with its URL.')]
final class CreateRecordTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'type' => ['required', 'string'],
                'title' => ['required', 'string', 'min:3', 'max:255'],
                'description' => ['nullable', 'string', 'max:160'],
                'data' => ['array'],
                'media' => ['array'],
                'media.*' => ['array'],
                'media.*.*' => ['integer', 'exists:media,id'],
                'categories' => ['array'],
                'categories.*' => ['integer', 'exists:categories,id'],
                'publish' => ['boolean'],
                ...Pages::blockRules(),
            ],
            [
                'type.required' => 'Pass the content type key. Use list-content-types to find it.',
                'title.required' => 'Give the record a title.',
                'title.min' => 'The record title must be at least 3 characters.',
                'title.max' => 'The record title may not be longer than 255 characters.',
                'description.max' => 'The description may not be longer than 160 characters — it is used as the meta description.',
                'media.*.*.exists' => 'One of the media ids does not exist. Use list-media or import-media-from-url first.',
                'categories.*.exists' => 'One of the category ids does not exist.',
                ...Pages::blockMessages(),
            ],
        );

        $type = RecordType::query()->where('key', $validated['type'])->first();

        if ($type === null) {
            return Response::error("No content type with key \"{$validated['type']}\". Use list-content-types to see the available types.");
        }

        $mediaError = Records::unknownMediaRole($type, $validated['media'] ?? []);

        if ($mediaError !== null) {
            return Response::error($mediaError);
        }

        $locale = resolve('localization')->getDefaultLocale();

        $record = new CreateRecordAction()->handle($type, [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? '',
            'data' => Records::cleanData($type, $validated['data'] ?? [], $locale),
        ]);

        new UpdateRecordAction()->handle($record, [
            'status' => Records::statusFor((bool) ($validated['publish'] ?? false)),
            'blocks' => $validated['blocks'] ?? [],
            'categories' => $validated['categories'] ?? [],
            'media' => Records::normalizeMedia($validated['media'] ?? [], $locale),
        ]);

        return Records::json([
            'record' => Records::recordSummary($record->refresh()),
            'hint' => 'Verify the record with get-record, then publish-record when it looks right. Add or reorder content blocks with update-record.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->description('The content type key, as returned by list-content-types.')
                ->required(),

            'title' => $schema->string()
                ->description('The record title. The slug is derived from it automatically.')
                ->required(),

            'description' => $schema->string()
                ->description('Optional meta description (max 160 characters).'),

            'data' => $schema->object()
                ->description('Custom field values keyed by field key, e.g. {"sku": "AB-1", "current_price": 49.99}. Translatable fields take a string (stored in the default language) or a {"en": "…"} map. Media fields are set with "media", not here. See the content type field blueprint from list-content-types / get-record.'),

            'media' => $schema->object()
                ->description('Media to attach, keyed by the media field key: {"gallery": [12, 13], "photo": [7]}. Values are media ids from list-media or import-media-from-url. Attached in the default language.'),

            'categories' => $schema->array()
                ->items($schema->integer())
                ->description('Category ids to assign to the record.'),

            'blocks' => $schema->array()
                ->items($schema->object())
                ->description('Ordered content blocks: [{"type": "<block key>", "content": {...}}]. See the block-types resource for the available types and content shapes.'),

            'publish' => $schema->boolean()
                ->description('Publish the record immediately instead of leaving it as a draft.')
                ->default(false),
        ];
    }
}
