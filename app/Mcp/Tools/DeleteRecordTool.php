<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\DeleteRecordAction;
use App\Ai\Contracts\RequiresConfirmation;
use App\Mcp\Support\Pages;
use App\Models\Record;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('delete-record')]
#[Description('Permanently delete a record along with its blocks, field values, web addresses and category links. This cannot be undone — prefer publish-record with status "draft" to take it offline while keeping it.')]
final class DeleteRecordTool extends Tool implements RequiresConfirmation
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'record' => ['required', 'integer'],
                'confirm' => ['accepted'],
            ],
            [
                'record.required' => 'Pass the record id. Use list-records to find it.',
                'confirm.accepted' => 'Deleting a record is permanent and cannot be undone. Check with the site owner first, then call this again with "confirm": true.',
            ],
        );

        $record = Record::query()->with(['recordType', 'slugs'])->find($validated['record']);

        if ($record === null) {
            return Response::error("No record with id {$validated['record']}. Use list-records to see the available records.");
        }

        $title = $record->title;
        $type = $record->recordType->key;

        resolve(DeleteRecordAction::class)->handle($record);

        return Pages::json([
            'deleted' => ['id' => $validated['record'], 'title' => $title, 'type' => $type],
            'hint' => 'Collection blocks that hand-picked this record by id will silently show one fewer item — check any that used source "manual".',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'confirm' => $schema->boolean()
                ->required()
                ->description('Must be true. Deleting is permanent, so confirm with the site owner before setting it.'),
            'record' => $schema->integer()
                ->required()
                ->description('The id of the record to delete, as returned by list-records.'),
        ];
    }
}
