<?php

namespace App\Core\Identity;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Passport\Bridge\Client;
use Laravel\Passport\Bridge\Scope;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Throwable;

/**
 * Who may authorize an application (acceptance of Auftrag 41): a person with a confirmed address; never an agent account.
 * The approval of an admin is needed only for the scopes that open the planer itself (api, mcp:*): for the identity alone
 * (openid, profile, email) the confirmed address is enough, because the application only learns who the person is.
 */
final class AuthorizationEligibility
{
    public const EMAIL_UNVERIFIED = 'email_unverified';

    public const AGENT = 'agent';

    public const NOT_APPROVED = 'not_approved';

    /**
     * @param  list<string>  $scopes
     * @return string|null why the person may not, or null
     */
    public static function problem(User $user, array $scopes): ?string
    {
        return match (true) {
            $user->isAgent() => self::AGENT,
            ! $user->hasVerifiedEmail() => self::EMAIL_UNVERIFIED,
            ! $user->isApproved() && self::opensThePlaner($scopes) => self::NOT_APPROVED,
            default => null,
        };
    }

    /** @param  list<string>  $scopes */
    public static function opensThePlaner(array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if ($scope === 'api' || str_starts_with($scope, 'mcp:')) {
                return true;
            }
        }

        return false;
    }

    /**
     * What the request asks for: the scope parameter of the authorization request, or, on the consent routes, the scopes of the
     * request that waits in the session.
     *
     * @return list<string>
     */
    public static function requestedScopes(Request $request): array
    {
        if ($request->query->has('scope')) {
            return array_values(array_filter(explode(' ', (string) $request->query('scope'))));
        }

        $serialized = $request->hasSession() ? $request->session()->get('authRequest') : null;

        if (! is_string($serialized)) {
            return [];
        }

        try {
            $authRequest = unserialize($serialized, ['allowed_classes' => [
                AuthorizationRequest::class,
                Client::class,
                Scope::class,
                \Laravel\Passport\Bridge\User::class,
            ]]);
        } catch (Throwable) {
            return [];
        }

        return $authRequest instanceof AuthorizationRequest
            ? array_values(array_map(fn ($scope): string => $scope->getIdentifier(), $authRequest->getScopes()))
            : [];
    }
}
