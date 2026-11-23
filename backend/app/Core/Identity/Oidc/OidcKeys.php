<?php

namespace App\Core\Identity\Oidc;

use Laravel\Passport\Passport;
use RuntimeException;

/** The signing key of the tokens (the one of Passport) as it is needed for the id_token and the JWKS. */
final class OidcKeys
{
    public static function privatePem(): string
    {
        return self::read('passport.private_key', 'oauth-private.key');
    }

    public static function publicPem(): string
    {
        return self::read('passport.public_key', 'oauth-public.key');
    }

    /** Key id: thumbprint (RFC 7638) of the public key, so a new key gets a new id by itself. */
    public static function keyId(): string
    {
        $jwk = self::publicJwk();

        return self::base64Url(hash('sha256', json_encode(['e' => $jwk['e'], 'kty' => 'RSA', 'n' => $jwk['n']], JSON_THROW_ON_ERROR), true));
    }

    /** @return array{kty: string, use: string, alg: string, kid: string, n: string, e: string} */
    public static function jwk(): array
    {
        return ['kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => self::keyId(), ...self::publicJwk()];
    }

    /** @return array{n: string, e: string} */
    private static function publicJwk(): array
    {
        $key = openssl_pkey_get_public(self::publicPem());
        $rsa = $key === false ? null : (openssl_pkey_get_details($key)['rsa'] ?? null);

        if ($rsa === null) {
            throw new RuntimeException('The public key of the OAuth server is not a usable RSA key.');
        }

        return ['n' => self::base64Url($rsa['n']), 'e' => self::base64Url($rsa['e'])];
    }

    private static function read(string $configKey, string $file): string
    {
        $value = config($configKey);

        if (is_string($value) && $value !== '') {
            return str_replace('\n', "\n", $value);
        }

        $path = Passport::keyPath($file);

        return is_file($path) ? (string) file_get_contents($path) : throw new RuntimeException("OAuth key file $path is missing (php artisan passport:keys).");
    }

    private static function base64Url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
