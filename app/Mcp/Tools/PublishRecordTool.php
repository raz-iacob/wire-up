<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\UpdateRecordAction;
use App\Ai\Contracts\RequiresConfirmation;
use App\Enums\ContentStatus;
use App\Mcp\Support\Records;
use App\Models\Record;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('publish-record')]
#[Description('Publish a record so it is publicly visible, or set it back to draft.')]
final class PublishRecordTool extends Tool implements RequiresConfirmation
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'record' => ['required', 'integer'],
                'status' => ['nullable', 'string', 'in:published,draft'],
            ],
            [
                'record.required' => 'Pass the record id. Use list-records to find it.',
                'record.integer' => 'The record id must be an integer. Use list-records to find it.',
                'status.in' => 'Status must be "published" or "draft".',
            ],
        );

        $record = Record::query()->with(['recordType', 'slugs', 'translations'])->find($validated['record']);

        if ($record === null) {
            return Response::error("No record with id {$validated['record']}. Use list-records to see the available records.");
        }

        $status = ($validated['status'] ?? 'published') === 'published'
            ? ContentStatus::PUBLISHED
            : ContentStatus::DRAFT;

        new UpdateRecordAction()->handle($record, ['status' => $status]);

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

            'status' => $schema->string()
                ->enum(['published', 'draft'])
                ->description('The publication status to set.')
                ->default('published'),
        ];
    }
}
