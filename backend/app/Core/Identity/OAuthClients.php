<?php

namespace App\Core\Identity;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Token;

/**
 * The applications that may log in through the planer. Made and revoked only here (never by a seeder or in code), by
 * an admin; every step is logged (who, which client, never a secret).
 */
class OAuthClients
{
    public const MAX_REDIRECT_URIS = 10;

    /** URL schemes of an app's own redirect that must never be accepted. */
    private const FORBIDDEN_SCHEMES = ['http', 'https', 'javascript', 'data', 'file', 'ftp', 'vbscript', 'about', 'blob'];

    public function __construct(private readonly ClientRepository $clients) {}

    /**
     * @param  list<string>  $redirectUris
     *
     * @throws ValidationException
     */
    public function create(User $admin, string $name, array $redirectUris, bool $confidential): Client
    {
        $uris = array_values(array_unique(array_map('trim', $redirectUris)));

        if ($uris === [] || count($uris) > self::MAX_REDIRECT_URIS) {
            throw ValidationException::withMessages(['redirectUris' => __('oauth.client.redirect_count', ['max' => self::MAX_REDIRECT_URIS])]);
        }

        foreach ($uris as $uri) {
            $this->assertValidRedirectUri($uri, $confidential);
        }

        $client = $this->clients->createAuthorizationCodeGrantClient(trim($name), $uris, $confidential);

        Log::warning('OAuth client made.', ['admin_id' => $admin->getKey(), 'client_id' => $client->getKey(), 'name' => $client->name, 'confidential' => $confidential]);

        return $client;
    }

    /** Null when there is no such client. */
    public function revoke(User $admin, string $id): ?Client
    {
        $client = $this->clients->find($id);

        if ($client === null) {
            return null;
        }

        DB::transaction(function () use ($client): void {
            $client->tokens()->with('refreshToken')->each(function (Token $token): void {
                $token->refreshToken?->revoke();
                $token->revoke();
            });
            $client->forceFill(['revoked' => true])->save();
        });

        Log::warning('OAuth client revoked.', ['admin_id' => $admin->getKey(), 'client_id' => $client->getKey(), 'name' => $client->name]);

        return $client;
    }

    /**
     * The address must be exact: no wildcard, no fragment, no login data. https; http only for the own machine; a
     * scheme of an own (a reverse domain such as ch.mollo.planer:/oauth/callback) only for public clients, the apps.
     *
     * @throws ValidationException
     */
    private function assertValidRedirectUri(string $uri, bool $confidential): void
    {
        $parts = parse_url($uri);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        $valid = $parts !== false
            && strlen($uri) <= 1024
            && ! str_contains($uri, '*')
            && ! isset($parts['fragment']) && ! isset($parts['user']) && ! isset($parts['pass'])
            && ! str_contains($uri, '#')
            && match (true) {
                $scheme === 'https' => $host !== '',
                $scheme === 'http' => in_array($host, ['localhost', '127.0.0.1', '[::1]'], true),
                default => ! $confidential
                    && preg_match('/^[a-z][a-z0-9+.-]*$/', $scheme) === 1
                    && ! in_array($scheme, self::FORBIDDEN_SCHEMES, true)
                    && str_starts_with((string) ($parts['path'] ?? ''), '/'),
            };

        if (! $valid) {
            throw ValidationException::withMessages(['redirectUris' => __('oauth.client.redirect_invalid', ['uri' => mb_substr($uri, 0, 80)])]);
        }
    }
}
