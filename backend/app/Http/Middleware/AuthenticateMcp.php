<?php

namespace App\Http\Middleware;

use App\Core\Identity\OAuthUser;
use App\Models\User;
use App\Support\McpScopes;
use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Who calls the MCP server (routes/ai.php): a personal access token made for Claude (Sanctum, as before) or an access
 * token of the OAuth server (a JWT, scope mcp:use, mcp:read or mcp:write), so that Claude can connect as a custom
 * connector without a token. Both act as the person, with their exact permissions. An OAuth token counts only here: the
 * scopes of the MCP server open nothing else.
 */
class AuthenticateMcp
{
    public function __construct(private readonly Authenticate $sanctum, private readonly EnsureMcpToken $mcpToken) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        // A personal token looks like "12|abc…"; an OAuth access token is a JWT with two dots.
        if (is_string($bearer) && substr_count($bearer, '.') === 2) {
            return $this->viaOAuth($request, $next);
        }

        return $this->sanctum->handle($request, fn (Request $inner): Response => $this->mcpToken->handle($inner, $next), 'sanctum');
    }

    private function viaOAuth(Request $request, Closure $next): Response
    {
        $holder = Auth::guard('oauth')->user();

        if (! $holder instanceof OAuthUser) {
            return $this->unauthenticated();
        }

        $scopes = McpScopes::granted($holder);

        if ($scopes === []) {
            return response()->json(['message' => 'This token cannot be used for the MCP server.'], Response::HTTP_FORBIDDEN);
        }

        // The account must still be one that may work: confirmed address, approved, a person (an agent has its own token).
        $user = User::query()->find($holder->getKey());

        if (! $user instanceof User || ! $user->hasVerifiedEmail() || ! $user->isApproved() || $user->isAgent()) {
            return $this->unauthenticated();
        }

        Auth::guard()->setUser($user);
        $request->setUserResolver(fn () => $user);
        McpScopes::remember($request, $scopes);

        return $next($request);
    }

    private function unauthenticated(): Response
    {
        return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
    }
}
