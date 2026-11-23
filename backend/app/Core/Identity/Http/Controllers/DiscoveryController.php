<?php

namespace App\Core\Identity\Http\Controllers;

use App\Core\Identity\IdentityServiceProvider;
use App\Core\Identity\Oidc\IdTokenResponse;
use Illuminate\Http\JsonResponse;

/** OpenID Provider Configuration (OpenID Connect Discovery 1.0). The addresses come from the routes, the issuer from APP_URL. */
class DiscoveryController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'issuer' => IdTokenResponse::issuer(),
            'authorization_endpoint' => route('passport.authorizations.authorize'),
            'token_endpoint' => route('passport.token'),
            'userinfo_endpoint' => route('oidc.userinfo'),
            'jwks_uri' => route('oidc.jwks'),
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => IdentityServiceProvider::SCOPES,
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
            'code_challenge_methods_supported' => ['S256'],
            'claims_supported' => ['sub', 'iss', 'aud', 'exp', 'iat', 'auth_time', 'nonce', 'amr', 'name', 'email', 'email_verified'],
        ]);
    }
}
