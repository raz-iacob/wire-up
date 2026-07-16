<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\Records;
use App\Models\Record;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get-record')]
#[Description('Get a record in full: its metadata, field values (data), attached media, categories, and ordered content blocks. Also includes the content type field blueprint so you know what each data key means.')]
final class GetRecordTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            ['record' => ['required', 'integer']],
            [
                'record.required' => 'Pass the record id. Use list-records to find it.',
                'record.integer' => 'The record id must be an integer. Use list-records to find it.',
            ],
        );

        $record = Record::query()
            ->with(['recordType', 'slugs', 'translations', 'blocks', 'media', 'categories'])
            ->find($validated['record']);

        if ($record === null) {
            return Response::error("No record with id {$validated['record']}. Use list-records to see the available records.");
        }

        return Records::json(['record' => Records::recordDetailed($record)]);
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
        ];
    }
}
