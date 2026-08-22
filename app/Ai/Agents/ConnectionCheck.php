<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

final class ConnectionCheck implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'Reply with the single word OK. Nothing else.';
    }
}
