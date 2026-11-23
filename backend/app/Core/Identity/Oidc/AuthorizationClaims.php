<?php

namespace App\Core\Identity\Oidc;

use App\Support\StrongAuthentication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Nonce, authentication methods and time of a login, from the authorization request to the id_token. */
final class AuthorizationClaims
{
    public const SESSION_NONCE = 'oidc.nonce';

    /** Remember what the authorization request says; the session carries it to the approval. */
    public static function remember(Request $request): void
    {
        $nonce = $request->query('nonce');
        $request->session()->put(self::SESSION_NONCE, is_string($nonce) && $nonce !== '' ? mb_substr($nonce, 0, 255) : null);
    }

    public static function store(string $authCodeId, Request $request): void
    {
        DB::table('oauth_auth_code_claims')->updateOrInsert(['auth_code_id' => $authCodeId], [
            'nonce' => $request->session()->get(self::SESSION_NONCE),
            'amr' => json_encode(StrongAuthentication::methods($request), JSON_THROW_ON_ERROR),
            'auth_time' => StrongAuthentication::authenticatedAt($request),
            'created_at' => now(),
        ]);
    }

    /** @return array{nonce: ?string, amr: list<string>, auth_time: int}|null */
    public static function load(string $authCodeId): ?array
    {
        $row = DB::table('oauth_auth_code_claims')->where('auth_code_id', $authCodeId)->first();

        if ($row === null) {
            return null;
        }

        return ['nonce' => $row->nonce, 'amr' => json_decode((string) $row->amr, true) ?: [], 'auth_time' => (int) $row->auth_time];
    }
}
