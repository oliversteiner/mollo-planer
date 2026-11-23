<?php

namespace App\Core\Identity\Oidc;

use App\Core\Identity\OAuthUser;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use SensitiveParameter;

/**
 * Token response with an id_token (OpenID Connect Core 3.1.3.3) when the scope openid was granted. The values that
 * belong to the login (nonce, amr, auth_time) come from the grant for the authorization code in use.
 */
class IdTokenResponse extends BearerTokenResponse
{
    /** @var array{nonce: ?string, amr: list<string>, auth_time: int}|null */
    private ?array $claims = null;

    /** @param  array{nonce: ?string, amr: list<string>, auth_time: int}|null  $claims */
    public function setClaims(?array $claims): void
    {
        $this->claims = $claims;
    }

    protected function getExtraParams(#[SensitiveParameter] AccessTokenEntityInterface $accessToken): array
    {
        $scopes = array_map(fn ($scope): string => $scope->getIdentifier(), $accessToken->getScopes());
        $user = in_array('openid', $scopes, true) ? OAuthUser::query()->find($accessToken->getUserIdentifier()) : null;

        if (! $user instanceof OAuthUser) {
            return [];
        }

        return ['id_token' => $this->idToken($user, $accessToken, $scopes)];
    }

    /** @param  list<string>  $scopes */
    private function idToken(OAuthUser $user, AccessTokenEntityInterface $accessToken, array $scopes): string
    {
        $now = new DateTimeImmutable;
        $builder = Configuration::forAsymmetricSigner(new Sha256, InMemory::plainText(OidcKeys::privatePem()), InMemory::plainText(OidcKeys::publicPem()))
            ->builder()
            ->issuedBy(self::issuer())
            ->permittedFor($accessToken->getClient()->getIdentifier())
            ->relatedTo((string) $user->getKey())
            ->issuedAt($now)
            ->expiresAt($accessToken->getExpiryDateTime())
            ->withHeader('kid', OidcKeys::keyId());

        if ($this->claims !== null) {
            $builder = $builder
                ->withClaim('auth_time', $this->claims['auth_time'])
                ->withClaim('amr', $this->claims['amr']);

            if ($this->claims['nonce'] !== null) {
                $builder = $builder->withClaim('nonce', $this->claims['nonce']);
            }
        }

        foreach (UserClaims::for($user, $scopes) as $name => $value) {
            $builder = $builder->withClaim($name, $value);
        }

        $configuration = Configuration::forAsymmetricSigner(new Sha256, InMemory::plainText(OidcKeys::privatePem()), InMemory::plainText(OidcKeys::publicPem()));

        return $builder->getToken($configuration->signer(), $configuration->signingKey())->toString();
    }

    /** The issuer is the address of the API of this environment (APP_URL), never fixed in the code. */
    public static function issuer(): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}
