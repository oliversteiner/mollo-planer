<?php

namespace App\Assistant;

use App\Models\User;
use App\Tools\Tool;
use App\Tools\ToolRegistry;
use App\Tools\ToolResult;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Support\ValidationMessages;

/**
 * The assistant side of the tools (task 34): the tools of the registry in the format of Claude tool use, and the
 * execution of a call as a given person. Built and tested here; the assistant does not use it yet (phase 1 of
 * docs/assistent-ausbau.md). Same tools, same rights, same texts as the MCP server: only the wrapping differs.
 */
final class ToolBridge
{
    /**
     * The tool definitions for the "tools" of a Claude request.
     *
     * @return list<array{name: string, description: string, input_schema: array<string, mixed>}>
     */
    public function definitions(): array
    {
        return array_map(fn (Tool $tool): array => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'input_schema' => $this->inputSchema($tool),
        ], ToolRegistry::all());
    }

    /**
     * Runs one call of Claude as the person: their rights apply, exactly as for a person using the app. Whatever goes
     * wrong (an unknown tool, arguments that do not fit, a refusal) is an error result Claude can read, never an
     * exception.
     *
     * @param  array<string, mixed>  $input
     */
    public function call(User $actor, string $name, array $input): ToolResult
    {
        $tool = ToolRegistry::find($name);

        if ($tool === null) {
            return ToolResult::error("Unknown tool [{$name}].");
        }

        try {
            return $tool->call($actor, $input);
        } catch (ValidationException $invalid) {
            return ToolResult::error(ValidationMessages::from($invalid));
        }
    }

    /**
     * The JSON Schema of the input; a tool without arguments has an empty object as its properties (the API of Claude
     * wants an object there, not a list).
     *
     * @return array<string, mixed>
     */
    private function inputSchema(Tool $tool): array
    {
        $schema = $tool->schema();

        if (($schema['properties'] ?? []) === []) {
            $schema['properties'] = new \stdClass;
        }

        return $schema;
    }
}
