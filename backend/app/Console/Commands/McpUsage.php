<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * What the AI clients cost: calls and characters per tool and per account over the last days (about four characters are one
 * token). Reads the table mcp_tool_calls (never the content of a call); changes nothing.
 */
#[Signature('app:mcp-usage {--days=7 : How many days back}')]
#[Description('Sum up the tool calls of AI clients: calls and characters per tool and per account')]
class McpUsage extends Command
{
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $since = now()->subDays($days);

        $perTool = DB::table('mcp_tool_calls')->where('created_at', '>=', $since)
            ->selectRaw('tool, count(*) as calls, sum(chars) as chars, max(chars) as biggest, sum(case when ok then 0 else 1 end) as failed')
            ->groupBy('tool')->orderByDesc('chars')->get();

        if ($perTool->isEmpty()) {
            $this->components->info(__('console.mcp_usage.none', ['days' => $days]));

            return self::SUCCESS;
        }

        $this->components->info(__('console.mcp_usage.per_tool', ['days' => $days]));
        $this->table(['tool', 'calls', 'chars', '~tokens', 'avg chars', 'biggest', 'failed'], $perTool->map(fn ($row): array => [
            $row->tool, $row->calls, $row->chars, intdiv((int) $row->chars, 4), intdiv((int) $row->chars, (int) $row->calls), $row->biggest, $row->failed,
        ])->all());

        $perAccount = DB::table('mcp_tool_calls')->where('mcp_tool_calls.created_at', '>=', $since)
            ->leftJoin('users', 'users.id', '=', 'mcp_tool_calls.user_id')
            ->selectRaw('users.name as name, users.is_agent as agent, mcp_tool_calls.tool as tool, count(*) as calls, sum(mcp_tool_calls.chars) as chars')
            ->groupBy('users.name', 'users.is_agent', 'mcp_tool_calls.tool')
            ->orderBy('users.name')->orderByDesc('chars')->get();

        $this->components->info(__('console.mcp_usage.per_account', ['days' => $days]));
        $this->table(['account', 'agent', 'tool', 'calls', 'chars', '~tokens'], $perAccount->map(fn ($row): array => [
            $row->name ?? '-', $row->agent ? 'yes' : 'no', $row->tool, $row->calls, $row->chars, intdiv((int) $row->chars, 4),
        ])->all());

        return self::SUCCESS;
    }
}
