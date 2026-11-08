<?php

use App\Http\Middleware\EnsureMcpToken;
use App\Mcp\Servers\ProjektverwaltungServer;
use Laravel\Mcp\Facades\Mcp;

// MCP server for Claude: a personal access token from the profile
// (Authorization: Bearer ...), acting with its owner's permissions.
Mcp::web('/mcp', ProjektverwaltungServer::class)
    ->middleware(['auth:sanctum', EnsureMcpToken::class, 'throttle:mcp']);
