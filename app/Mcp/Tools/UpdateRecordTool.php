<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\UpdateRecordAction;
use App\Enums\ContentStatus;
use App\Mcp\Support\Pages;
use App\Mcp\Support\Records;
use App\Models\Record;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update-record')]
#[Description('Update an existing record: change its title, field values, media, categories, content blocks, or publication status. Only the keys you pass are changed. Passing "blocks" replaces the whole block list; "data" and "media" are merged field by field. Attributes you omit are left as they are.')]
final class UpdateRecordTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'record' => ['required', 'integer'],
                'title' => ['nullable', 'string', 'min:3', 'max:255'],
                'description' => ['nullable', 'string', 'max:160'],
                'data' => ['array'],
                'media' => ['array'],
                'media.*' => ['array'],
                'media.*.*' => ['integer', 'exists:media,id'],
                'categories' => ['array'],
                'categories.*' => ['integer', 'exists:categories,id'],
                'status' => ['nullable', 'string', 'in:draft,published,private'],
                ...Pages::blockRules(),
            ],
            [
                'record.required' => 'Pass the record id. Use list-records to find it.',
                'record.integer' => 'The record id must be an integer. Use list-records to find it.',
                'title.min' => 'The record title must be at least 3 characters.',
                'title.max' => 'The record title may not be longer than 255 characters.',
                'description.max' => 'The description may not be longer than 160 characters — it is used as the meta description.',
                'status.in' => 'Status must be one of: draft, published, private.',
                'media.*.*.exists' => 'One of the media ids does not exist. Use list-media or import-media-from-url first.',
                'categories.*.exists' => 'One of the category ids does not exist.',
                ...Pages::blockMessages(),
            ],
        );

        $record = Record::query()
            ->with(['recordType', 'slugs', 'translations', 'media'])
            ->find($validated['record']);

        if ($record === null) {
            return Response::error("No record with id {$validated['record']}. Use list-records to see the available records.");
        }

        $type = $record->recordType;

        $mediaError = Records::unknownMediaRole($type, $validated['media'] ?? []);

        if ($mediaError !== null) {
            return Response::error($mediaError);
        }

        $locale = resolve('localization')->getDefaultLocale();

        $status = isset($validated['status']) ? ContentStatus::from($validated['status']) : $record->computed_status;

        $attributes = ['status' => $status];

        if ($status === ContentStatus::SCHEDULED) {
            $attributes['published_at'] = $record->published_at;
        }

        if (($validated['title'] ?? null) !== null) {
            $attributes['title'] = $validated['title'];
        }

        if (($validated['description'] ?? null) !== null) {
            $attributes['description'] = $validated['description'];
        }

        if (array_key_exists('data', $validated)) {
            $attributes['data'] = Records::cleanData($type, $validated['data'], $locale, is_array($record->data) ? $record->data : []);
        }

        if (array_key_exists('blocks', $validated)) {
            $attributes['blocks'] = $validated['blocks'];
        }

        if (array_key_exists('categories', $validated)) {
            $attributes['categories'] = $validated['categories'];
        }

        if (array_key_exists('media', $validated)) {
            $attributes['media'] = Records::normalizeMedia($validated['media'], $locale);
        }

        new UpdateRecordAction()->handle($record, $attributes);

        return Records::json(['record' => Records::recordSummary($record->refresh())]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'record' => $schema->integer()
                ->description('The record id, as returned by list-records or create-record.')
                ->required(),

            'title' => $schema->string()
                ->description('A new title. The existing slug is kept.'),

            'description' => $schema->string()
                ->description('A new meta description (max 160 characters).'),

            'data' => $schema->object()
                ->description('Custom field values to set, keyed by field key. Merged into the existing data — only the keys you pass change. Translatable fields take a string (default language) or a {"en": "…"} map.'),

            'media' => $schema->object()
                ->description('Media to set, keyed by the media field key: {"gallery": [12, 13]}. Replaces that field\'s media; pass an empty array to clear it. Fields you omit keep their current media.'),

            'categories' => $schema->array()
                ->items($schema->integer())
                ->description('Category ids. Replaces the record\'s categories with this exact set.'),

            'blocks' => $schema->array()
                ->items($schema->object())
                ->description('The complete ordered block list: [{"id": <existing id, omit for new>, "type": "<block key>", "content": {...}}]. Blocks omitted from the list are deleted. See the block-types resource.'),

            'status' => $schema->string()
                ->enum(['draft', 'published', 'private'])
                ->description('Publication status to set. Omit to leave it unchanged.'),
        ];
    }
}
