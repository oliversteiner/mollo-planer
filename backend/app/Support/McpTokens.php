<?php

namespace App\Support;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

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

    public static function revokeAll(User $user): void
    {
        $user->tokens()->delete();
    }
}
