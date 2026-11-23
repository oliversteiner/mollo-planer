<?php

namespace App\Core\Identity\Http\Controllers;

use App\Core\Identity\OAuthUser;
use App\Core\Identity\Oidc\UserClaims;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

/** Claims about the person of an access token with the scope openid (OpenID Connect Core 5.3). */
class UserInfoController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user('oauth');

        if (! $user instanceof OAuthUser || ! $user->tokenCan('openid')) {
            return response()->json(['error' => 'insufficient_scope'], Response::HTTP_FORBIDDEN, ['WWW-Authenticate' => 'Bearer error="insufficient_scope", scope="openid"']);
        }

        $scopes = array_keys(array_filter(Passport::$scopes, fn (string $description, string $id): bool => $user->tokenCan($id), ARRAY_FILTER_USE_BOTH));

        return response()->json(['sub' => (string) $user->getKey(), ...UserClaims::for($user, $scopes)]);
    }
}
