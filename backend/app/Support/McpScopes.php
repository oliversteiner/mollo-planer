<?php

namespace App\Support;

use App\Core\Identity\OAuthUser;
use Illuminate\Http\Request;

/**
 * What an OAuth access token may do at the MCP server. mcp:use is the whole connector (as a personal token);
 * mcp:read only the tools that read; mcp:write also the tools that change something. A personal token (no OAuth) is not
 * limited here: its owner's rights are.
 */
final class McpScopes
{
    public const USE = 'mcp:use';

    public const READ = 'mcp:read';

    public const WRITE = 'mcp:write';

    private const ATTRIBUTE = 'mcp_oauth_scopes';

    /** @return list<string> the MCP scopes of the token */
    public static function granted(OAuthUser $holder): array
    {
        return array_values(array_filter([self::USE, self::READ, self::WRITE], fn (string $scope): bool => $holder->tokenCan($scope)));
    }

    /** @param  list<string>  $scopes */
    public static function remember(Request $request, array $scopes): void
    {
        $request->attributes->set(self::ATTRIBUTE, $scopes);
    }

    /** Whether the current call may use tools that change something: always for a personal token. */
    public static function mayWrite(?Request $request = null): bool
    {
        $scopes = ($request ?? request())->attributes->get(self::ATTRIBUTE);

        return $scopes === null || in_array(self::USE, $scopes, true) || in_array(self::WRITE, $scopes, true);
    }
}
