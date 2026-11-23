<?php

namespace App\Core\Identity\Oidc;

use DateInterval;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * The code grant of Passport plus what OpenID Connect needs: when a code is issued, the nonce and the way of the
 * login are noted down; when the code is exchanged, they go to the id_token.
 */
class OidcAuthCodeGrant extends AuthCodeGrant
{
    protected function issueAuthCode(
        DateInterval $authCodeTTL,
        ClientEntityInterface $client,
        string $userIdentifier,
        ?string $redirectUri,
        array $scopes = []
    ): AuthCodeEntityInterface {
        $authCode = parent::issueAuthCode($authCodeTTL, $client, $userIdentifier, $redirectUri, $scopes);

        if (request()->hasSession()) {
            AuthorizationClaims::store($authCode->getIdentifier(), request());
        }

        return $authCode;
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        $claims = null;

        try {
            $code = $this->getRequestParameter('code', $request);
            $payload = is_string($code) ? json_decode($this->decrypt($code), true) : null;
            $claims = is_array($payload) && isset($payload['auth_code_id']) ? AuthorizationClaims::load((string) $payload['auth_code_id']) : null;
        } catch (Throwable) {
            // A code that cannot be read is refused by the grant itself with the right error.
        }

        $response = parent::respondToAccessTokenRequest($request, $responseType, $accessTokenTTL);

        if ($response instanceof IdTokenResponse) {
            $response->setClaims($claims);
        }

        return $response;
    }
}
