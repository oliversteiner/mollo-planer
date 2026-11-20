<?php

namespace App\Support;

use App\Models\User;
use InvalidArgumentException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Personal access tokens for Claude (MCP server under /mcp). A token acts
 * as its owner with the owner's permissions; it is shown once at creation
 * and expires after 90 days. Resetting the second factor or deleting the
 * user removes all tokens.
 */
final class McpTokens
{
    public const ABILITY = 'mcp';

    public const VALID_DAYS = 90;

    public static function create(User $user, string $name): NewAccessToken
    {
        return $user->createToken($name, [self::ABILITY], now()->addDays(self::VALID_DAYS));
    }

    /**
     * The one way a token for an agent account is made: the command app:user-token and the admin mutation both
     * use it. A person makes their own token (create), nobody makes one in their name.
     *
     * @throws InvalidArgumentException when the account is not an agent
     */
    public static function createForAgent(User $agent, string $name): NewAccessToken
    {
        if (! $agent->isAgent()) {
            throw new InvalidArgumentException('A token is made for an agent account only.');
        }

        return self::create($agent, $name);
    }

    /** Revokes one connector token of the user; null when it is not one of theirs. */
    public static function revoke(User $user, int|string $tokenId): ?PersonalAccessToken
    {
        $token = $user->tokens()->whereKey($tokenId)->whereJsonContains('abilities', self::ABILITY)->first();
        $token?->delete();

        return $token;
    }

    /** Returns how many tokens were removed. */
    public static function revokeAll(User $user): int
    {
        return $user->tokens()->delete();
    }
}
