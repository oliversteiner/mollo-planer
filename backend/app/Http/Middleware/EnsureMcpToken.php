<?php

namespace App\Http\Middleware;

use App\Support\McpTokens;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * The MCP server (routes/ai.php) only accepts personal access tokens made
 * for Claude (ability "mcp", created in the profile). auth:sanctum runs
 * first; any other token gets 403, also one with all abilities ("*").
 */
class EnsureMcpToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        // Exactly "mcp": a token with all abilities ("*") was not made for Claude.
        if (! $token instanceof PersonalAccessToken || ! in_array(McpTokens::ABILITY, $token->abilities ?? [], true)) {
            return response()->json(['message' => 'This token cannot be used for the MCP server.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
