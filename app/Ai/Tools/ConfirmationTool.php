<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Tools\McpServerTool;
use Laravel\Ai\Tools\Request;

final class ConfirmationTool extends McpServerTool
{
    public function handle(Request $request): string
    {
        return json_encode([
            'status' => 'awaiting_confirmation',
            'message' => 'This action needs the site owner to approve it. It has been shown to them as a confirmation prompt — do not call it again; tell them it is ready and ask them to confirm.',
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = parent::schema($schema);

        unset($properties['confirm']);

        return $properties;
    }
}
