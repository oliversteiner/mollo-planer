<?php

namespace App\Mcp;

use App\Models\User;
use App\Support\McpScopes;
use App\Tools\Tool as AgentTool;
use App\Tools\ToolResult;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool as McpTool;

/**
 * The MCP side of a tool (task 34): one thin adapter for every tool of the registry. It lends Laravel MCP the name,
 * description, schema and the "read only" mark of the tool, hands the arguments and the person of the token to
 * AgentTool::call() and turns the ToolResult into a Laravel MCP response. A ValidationException of the tool goes
 * on to Laravel MCP, which shows it the way it always did.
 *
 * Chosen over one class per tool: a new tool needs no MCP class at all, and what the client sees (name, title,
 * description, schema, annotations) is checked by tests/Fixtures/mcp-tools-list.json.
 */
final class ToolAdapter extends McpTool
{
    public function __construct(private readonly AgentTool $tool) {}

    public function name(): string
    {
        return $this->tool->name();
    }

    public function description(): string
    {
        return $this->tool->description();
    }

    /**
     * @return array<string, mixed>
     */
    public function annotations(): array
    {
        return $this->tool->readOnly() ? ['readOnlyHint' => true] : [];
    }

    /**
     * The tool's own JSON Schema, as it is, instead of one built from the library's schema objects.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = [...parent::toArray(), 'inputSchema' => $this->tool->schema()];
        // The title would only repeat the name ("List Projects Tool"): it costs characters in every tools/list.
        unset($array['title']);

        return $array;
    }

    /**
     * A token of the OAuth server with only mcp:read does not even see the tools that change something.
     */
    public function shouldRegister(): bool
    {
        return $this->tool->readOnly() || McpScopes::mayWrite();
    }

    public function handle(Request $request): Response
    {
        $actor = $request->user();

        if (! $this->tool->readOnly() && ! McpScopes::mayWrite()) {
            return Response::error('This token may only read (scope mcp:read).');
        }

        if (! $actor instanceof User) {
            return Response::error('Not signed in.');
        }

        $startedAt = hrtime(true);
        $result = null;

        try {
            $result = $this->tool->call($actor, $request->all());
        } finally {
            ToolUsage::record($this->tool->name(), $actor->getKey(), $result, $startedAt);
        }

        return match ($result->kind) {
            ToolResult::ERROR => Response::error((string) $result->data),
            ToolResult::TEXT => Response::text((string) $result->data),
            default => Response::json($result->data),
        };
    }
}
