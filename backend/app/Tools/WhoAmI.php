<?php

namespace App\Tools;

use App\Models\User;

/**
 * The account the tool acts as (Auftrag 65): one cheap call at the start of a session shows an agent whether the
 * connector holds its own token and not that of somebody else.
 */
class WhoAmI extends Tool
{
    public function name(): string
    {
        return 'whoami';
    }

    public function description(): string
    {
        return 'The account you act as: id, name, email, is_agent.';
    }

    public function readOnly(): bool
    {
        return true;
    }

    public function handle(User $actor, array $arguments): ToolResult
    {
        return ToolResult::json([
            'id' => (string) $actor->getKey(),
            'name' => $actor->name,
            'email' => $actor->email,
            'is_agent' => (bool) $actor->is_agent,
        ]);
    }
}
