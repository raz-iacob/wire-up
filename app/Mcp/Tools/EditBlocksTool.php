<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\BlockChanges;
use App\Mcp\Support\Blocks;
use App\Mcp\Support\Pages;
use App\Mcp\Support\Records;
use App\Models\Block;
use App\Models\Page;
use App\Models\Record;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('edit-blocks')]
#[Description('Change part of a page or record without resending its other blocks: update one block or one item, add, remove or move a block, or add or remove an item. Changes run in order and are saved together — if one fails, nothing is saved. Find block and item ids with get-page or get-record and "outline": true. Use update-page-blocks only to replace the whole list.')]
final class EditBlocksTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'page' => ['required_without:record', 'prohibits:record', 'integer'],
                'record' => ['required_without:page', 'integer'],
                'changes' => ['required', 'list', 'min:1', 'max:100'],
                'changes.*' => ['array'],
                'changes.*.action' => ['required', 'string', Rule::in(BlockChanges::ACTIONS)],
                'changes.*.block' => ['nullable'],
                'changes.*.item' => ['nullable'],
                'changes.*.type' => ['nullable', 'string'],
                'changes.*.after' => ['nullable'],
                'changes.*.before' => ['nullable'],
                'changes.*.content' => ['nullable', 'array'],
            ],
            [
                'page.required_without' => 'Pass "page" (a page id) or "record" (a record id).',
                'record.required_without' => 'Pass "page" (a page id) or "record" (a record id).',
                'page.prohibits' => 'Pass either "page" or "record", not both.',
                'page.integer' => '"page" must be the page id, a whole number. Use list-pages to find it.',
                'record.integer' => '"record" must be the record id, a whole number. Use list-records to find it.',
                'changes.required' => 'Pass "changes": a list such as [{"action": "update", "block": 12, "content": {...}}].',
                'changes.list' => '"changes" must be a list of change objects.',
                'changes.max' => 'Send at most 100 changes per call.',
                'changes.*.array' => 'Each change must be an object with an "action".',
                'changes.*.action.required' => 'Every change needs an "action": '.implode(', ', BlockChanges::ACTIONS).'.',
                'changes.*.action.in' => 'Unknown action. Use one of: '.implode(', ', BlockChanges::ACTIONS).'.',
                'changes.*.content.array' => '"content" must be an object of the fields to set.',
            ],
        );

        $model = isset($validated['page'])
            ? Page::query()->with(['slugs', 'translations', 'blocks'])->find($validated['page'])
            : Record::query()->with(['recordType', 'slugs', 'translations', 'blocks'])->find($validated['record']);

        if ($model === null) {
            return Response::error(isset($validated['page'])
                ? "No page with id {$validated['page']}. Use list-pages to see the available pages."
                : "No record with id {$validated['record']}. Use list-records to see the available records.");
        }

        $current = $model->blocks
            ->map(fn (Block $block): array => ['id' => $block->id, 'type' => $block->type->value, 'content' => $block->content ?? []])
            ->values()
            ->all();

        $changed = new BlockChanges($current)->apply($validated['changes']);

        if (is_string($changed)) {
            return Response::error($changed);
        }

        $blocks = Blocks::prepare($changed);
        $problem = Blocks::problem($blocks);

        if ($problem !== null) {
            return Response::error($problem.' Nothing was saved.');
        }

        Blocks::save($model, $blocks);
        $model->refresh()->load('blocks');

        return Pages::json([
            ...($model instanceof Page ? ['page' => Pages::summary($model)] : ['record' => Records::recordSummary($model)]),
            'changes' => count($validated['changes']),
            'blocks' => Blocks::outline($model->blocks),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()
                ->description('The page to change, as returned by list-pages. Pass this or "record".'),

            'record' => $schema->integer()
                ->description('The record to change, as returned by list-records. Pass this or "page".'),

            'changes' => $schema->array()
                ->items($schema->object())
                ->description(<<<'TEXT'
                    The changes, applied in order:
                    {"action": "update", "block": <id>, "content": {...}} merges into the block: objects (such as {"en": "…"} text) are merged key by key, lists such as "items" are replaced, null clears a field.
                    {"action": "add", "type": "<block key>", "content": {...}, "after": <block id>} adds a block; use "before" instead, or neither to add it at the end. A block added earlier in the same call is "new-<change number>", e.g. "after": "new-1".
                    {"action": "remove", "block": <id>} deletes a block.
                    {"action": "move", "block": <id>, "after": <block id>} or "before".
                    {"action": "update-item", "block": <id>, "item": "<item id>", "content": {...}} merges into one item of a repeating block (feature-cards, accordion, stats, …).
                    {"action": "add-item", "block": <id>, "content": {...}, "after": "<item id>"} adds an item ("before", or neither for the end); it gets an id unless you give one.
                    {"action": "remove-item", "block": <id>, "item": "<item id>"} deletes an item.
                    Text fields take a {"en": "…"} map or a plain string for the site's default language. See the block-types resource for content shapes.
                    TEXT)
                ->required(),
        ];
    }
}
