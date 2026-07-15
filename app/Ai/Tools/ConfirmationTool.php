<?php

declare(strict_types=1);

namespace App\Ai\Tools;

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
}
