<?php

namespace App\Mcp;

use App\Models\User;
use App\Tools\Tool as AgentTool;
use App\Tools\ToolResult;
use Illuminate\Support\Str;
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

    /** As before, when the title came from the class name: "list_projects" is "List Projects Tool". */
    public function title(): string
    {
        return Str::headline(Str::studly($this->tool->name()).'Tool');
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
        return [...parent::toArray(), 'inputSchema' => $this->tool->schema()];
    }

    public function handle(Request $request): Response
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            return Response::error('Not signed in.');
        }

        $result = $this->tool->call($actor, $request->all());

        return match ($result->kind) {
            ToolResult::ERROR => Response::error((string) $result->data),
            ToolResult::TEXT => Response::text((string) $result->data),
            default => Response::json($result->data),
        };
    }
}
