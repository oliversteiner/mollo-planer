<?php

namespace App\Mcp\Servers;

use App\Mcp\ServerInstructions;
use App\Mcp\ToolAdapter;
use App\Models\User;
use App\Tools\Tool;
use App\Tools\ToolRegistry;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\ServerContext;

/**
 * MCP server of the app (POST /mcp, routes/ai.php): lets AI clients read a
 * project as a planer-v2 document (export_plan) and write changes back
 * (import_plan / update_tasks), acting as the owner of the personal access
 * token with their exact permissions.
 */
#[Name('mollo-planer')]
#[Version('2.0.0')]
class MolloPlanerServer extends Server
{
    /**
     * Filled from the registry: the tools are described once (App\Tools), this server only offers them.
     *
     * @var array<int, Server\Tool>
     */
    protected array $tools = [];

    public function __construct(Transport $transport)
    {
        parent::__construct($transport);

        $this->tools = array_map(fn (Tool $tool): ToolAdapter => new ToolAdapter($tool), ToolRegistry::all());
    }

    /**
     * The instructions are made per request: they name the account of the token (ServerInstructions).
     */
    public function createContext(): ServerContext
    {
        $user = request()->user();
        $this->instructions = ServerInstructions::for($user instanceof User ? $user : null);

        return parent::createContext();
    }

    protected array $resources = [];

    protected array $prompts = [];
}
