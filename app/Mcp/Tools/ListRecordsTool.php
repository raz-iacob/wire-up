<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\ContentStatus;
use App\Mcp\Support\Records;
use App\Models\Record;
use App\Models\RecordType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list-records')]
#[Description('List the records of a content type with their id, title, slug, URL, and publication status. Optionally filter by status or a search term.')]
final class ListRecordsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'type' => ['required', 'string'],
                'status' => ['nullable', 'string', 'in:draft,published,scheduled,private'],
                'search' => ['nullable', 'string'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            ],
            [
                'type.required' => 'Pass the content type key. Use list-content-types to find it.',
                'status.in' => 'Status must be one of: draft, published, scheduled, private.',
            ],
        );

        $type = RecordType::query()->where('key', $validated['type'])->first();

        if ($type === null) {
            return Response::error("No content type with key \"{$validated['type']}\". Use list-content-types to see the available types.");
        }

        $records = Record::query()
            ->where('record_type_id', $type->id)
            ->with(['recordType', 'slugs', 'translations'])
            ->when(($validated['status'] ?? null) !== null, fn (Builder $query): Builder => $query->where('status', ContentStatus::from($validated['status'])))
            ->when(($validated['search'] ?? '') !== '', fn (Builder $query): Builder => $query->matchingSearch($validated['search'], $type))
            ->latest('updated_at')
            ->limit($validated['limit'] ?? 50)
            ->get()
            ->map(Records::recordSummary(...));

        return Records::json([
            'type' => $type->key,
            'records' => $records->all(),
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

            'status' => $schema->string()
                ->enum(['draft', 'published', 'scheduled', 'private'])
                ->description('Only return records with this publication status.'),

            'search' => $schema->string()
                ->description('Filter records whose title or searchable fields match this term.'),

            'limit' => $schema->integer()
                ->description('Maximum number of records to return (1-100).')
                ->default(50),
        ];
    }
}
