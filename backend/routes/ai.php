<?php

use App\Http\Middleware\AuthenticateMcp;
use App\Mcp\Servers\MolloPlanerServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;

// OAuth for the connector: the metadata Claude looks for and the registration of a client by itself (public clients with
// PKCE only, to the addresses of config/mcp.php, rate limited). The authorization server is the one of the planer
// (App\Core\Identity, docs/oauth-sso.md); the registered clients show up in System > Login service.
Route::middleware('throttle:oauth-register')->group(fn () => Mcp::oauthRoutes());

// MCP server for Claude: a personal access token from the profile or an access token of the OAuth server
// (Authorization: Bearer ...), acting with its owner's permissions.
Mcp::web('/mcp', MolloPlanerServer::class)
    ->middleware([AddWwwAuthenticateHeader::class, AuthenticateMcp::class, 'throttle:mcp']);
