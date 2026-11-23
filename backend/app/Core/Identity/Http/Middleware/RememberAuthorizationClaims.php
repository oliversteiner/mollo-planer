<?php

namespace App\Core\Identity\Http\Middleware;

use App\Core\Identity\Oidc\AuthorizationClaims;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Notes the nonce of the authorization request; it goes into the id_token once the person has agreed. */
class RememberAuthorizationClaims
{
    public function handle(Request $request, Closure $next): Response
    {
        AuthorizationClaims::remember($request);

        return $next($request);
    }
}
