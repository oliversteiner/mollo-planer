<?php

use App\Http\Middleware\EnsureMcpToken;
use App\Mcp\Servers\MolloPlanerServer;
use Laravel\Mcp\Facades\Mcp;

// MCP server for Claude: a personal access token from the profile
// (Authorization: Bearer ...), acting with its owner's permissions.
Mcp::web('/mcp', MolloPlanerServer::class)
    ->middleware(['auth:sanctum', EnsureMcpToken::class, 'throttle:mcp']);
