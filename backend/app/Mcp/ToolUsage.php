<?php

namespace App\Mcp;

use App\Tools\ToolResult;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Notes a tool call of an AI client: the tool, the account, the characters of the answer, the time. No arguments and no
 * answer. A failing note never fails the call.
 */
final class ToolUsage
{
    public static function record(string $tool, int|string|null $userId, ?ToolResult $result, float $startedAt): void
    {
        try {
            DB::table('mcp_tool_calls')->insert([
                'tool' => $tool,
                'user_id' => $userId,
                'chars' => $result === null ? 0 : self::chars($result),
                'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
                'ok' => $result !== null && ! $result->isError(),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private static function chars(ToolResult $result): int
    {
        return $result->kind === ToolResult::JSON
            ? strlen((string) json_encode($result->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            : strlen((string) $result->data);
    }
}
