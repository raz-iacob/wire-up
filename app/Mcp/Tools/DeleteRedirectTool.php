<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Ai\Contracts\RequiresConfirmation;
use App\Mcp\Support\Pages;
use App\Models\Redirect;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('delete-redirect')]
#[Description('Permanently delete a redirect, so its old address shows the not found page again. This cannot be undone. Find the id with list-redirects.')]
final class DeleteRedirectTool extends Tool implements RequiresConfirmation
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'redirect' => ['required', 'integer'],
                'confirm' => ['accepted'],
            ],
            [
                'redirect.required' => 'Pass the redirect id. Use list-redirects to find it.',
                'confirm.accepted' => 'Deleting a redirect is permanent and cannot be undone. Check with the site owner first, then call this again with "confirm": true.',
            ],
        );

        $redirect = Redirect::query()->find($validated['redirect']);

        if ($redirect === null) {
            return Response::error("No redirect with id {$validated['redirect']}. Use list-redirects to see them.");
        }

        $redirect->delete();

        return Pages::json([
            'deleted' => ['id' => $redirect->id, 'from' => $redirect->from, 'to' => $redirect->to],
            'hint' => 'The old address no longer redirects.',
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
            'redirect' => $schema->integer()
                ->required()
                ->description('The id of the redirect to delete, as returned by list-redirects.'),
        ];
    }
}
