<?php

namespace App\Core\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The token endpoint gives tokens only for the two grants the discovery document names: authorization_code and refresh_token.
 * Passport always enables more (client_credentials, which would give a confidential client a token without any person); they are
 * refused here with the answer of the specification, whatever Passport has switched on.
 */
class RefuseUnsupportedGrants
{
    public const GRANTS = ['authorization_code', 'refresh_token'];

    public function handle(Request $request, Closure $next): Response
    {
        $grant = $request->input('grant_type');

        if (is_string($grant) && ! in_array($grant, self::GRANTS, true)) {
            return response()->json([
                'error' => 'unsupported_grant_type',
                'error_description' => 'The authorization grant type is not supported by the authorization server.',
                'hint' => 'Supported: '.implode(', ', self::GRANTS).'.',
            ], Response::HTTP_BAD_REQUEST, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
        }

        return $next($request);
    }
}
