<?php

namespace App\Core\Identity\Http\Controllers;

use App\Core\Identity\Oidc\OidcKeys;
use Illuminate\Http\JsonResponse;

/** The public key that signs the id_tokens (JSON Web Key Set, RFC 7517). */
class JwksController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['keys' => [OidcKeys::jwk()]])->header('Cache-Control', 'public, max-age=3600');
    }
}
