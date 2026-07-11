<?php

declare(strict_types=1);

use App\Mcp\Servers\WireUpServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('wire-up', WireUpServer::class);
