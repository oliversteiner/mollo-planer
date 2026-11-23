<?php

namespace App\Core\Identity\Oidc;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * The refresh grant of Passport already rotates (a refresh token works once). If a refresh token that was used
 * already comes back, someone else may hold a copy: then every token of that person for that application is revoked,
 * so the copy and the original are both locked out and the person has to log in again (OAuth 2.1, 4.3.1).
 */
class RotatingRefreshTokenGrant extends RefreshTokenGrant
{
    protected function validateOldRefreshToken(ServerRequestInterface $request, string $clientId): array
    {
        try {
            return parent::validateOldRefreshToken($request, $clientId);
        } catch (OAuthServerException $exception) {
            if ($exception->getHint() === 'Token has been revoked') {
                $this->revokeAllOfThePerson($request);
            }

            throw $exception;
        }
    }

    private function revokeAllOfThePerson(ServerRequestInterface $request): void
    {
        try {
            $payload = json_decode($this->decrypt((string) $this->getRequestParameter('refresh_token', $request)), true);
            $userId = $payload['user_id'] ?? null;
            $clientId = $payload['client_id'] ?? null;
        } catch (Throwable) {
            return;
        }

        if ($userId === null || $clientId === null) {
            return;
        }

        DB::transaction(function () use ($userId, $clientId): void {
            $tokens = DB::table('oauth_access_tokens')->where('user_id', $userId)->where('client_id', $clientId);
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', (clone $tokens)->select('id'))->update(['revoked' => true]);
            $tokens->update(['revoked' => true]);
        });

        Log::warning('OAuth: a used refresh token came back, all tokens of the person for the application were revoked.', ['user_id' => $userId, 'client_id' => $clientId]);
    }
}
