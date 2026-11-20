<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An agent account (users.is_agent) has no access to the app's API, with a session or with a token: its only way in is
 * the MCP connector (routes/ai.php), which does not run through this route. A session of an agent cannot arise
 * (RefuseAgentLogin); this lock makes sure that nothing opens if one existed all the same.
 */
class EnsureNotAgent
{
    public const CODE = 'AGENT_ACCOUNT';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isAgent()) {
            return response()->json([
                'errors' => [[
                    'message' => __('graphql.agent_account'),
                    'extensions' => ['code' => self::CODE],
                ]],
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
