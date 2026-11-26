<?php

namespace App\Core\Identity\Oidc;

use DateInterval;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * The code grant of Passport plus what OpenID Connect needs: when a code is issued, the nonce and the way of the
 * login are noted down; when the code is exchanged, they go to the id_token.
 */
class OidcAuthCodeGrant extends AuthCodeGrant
{
    /**
     * PKCE for every client, and only S256 (OAuth 2.1): league also accepts the method "plain" (the default when none is
     * named) and leaves PKCE optional for a confidential client. The discovery document says S256 only; this makes it true.
     *
     * @throws OAuthServerException
     */
    public function validateAuthorizationRequest(ServerRequestInterface $request): AuthorizationRequestInterface
    {
        $authRequest = parent::validateAuthorizationRequest($request);

        if ($authRequest->getCodeChallenge() === null) {
            throw $this->refusal('code_challenge', 'A code challenge is required (PKCE with S256).', $authRequest);
        }

        if ($authRequest->getCodeChallengeMethod() !== 'S256') {
            throw $this->refusal('code_challenge_method', 'Only the code challenge method S256 is supported.', $authRequest);
        }

        return $authRequest;
    }

    /** The error goes back to the application (redirect address and state of the request, already checked). */
    private function refusal(string $parameter, string $hint, AuthorizationRequestInterface $authRequest): OAuthServerException
    {
        $exception = new OAuthServerException(
            'The request is missing a required parameter, includes an invalid parameter value, includes a parameter more than once, or is otherwise malformed.',
            3,
            'invalid_request',
            400,
            "Check the `{$parameter}` parameter. {$hint}",
            (string) $authRequest->getRedirectUri(),
        );

        if ($authRequest->getState() !== null) {
            $exception->setPayload([...$exception->getPayload(), 'state' => $authRequest->getState()]);
        }

        return $exception;
    }

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
