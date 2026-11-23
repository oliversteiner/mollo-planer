<?php

namespace Tests;

use App\Mcp\ToolAdapter;
use App\Tools\ToolRegistry;

/**
 * The MCP side of a tool for the tests of the server (task 34): the tools are no classes of their own on the MCP side
 * any more, so a test asks for one by its name, as a client does.
 */
final class McpTools
{
    public static function of(string $name): ToolAdapter
    {
        return new ToolAdapter(ToolRegistry::get($name));
    }
}
