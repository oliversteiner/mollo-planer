<?php

namespace App\Tools;

use App\Models\User;

class ListProjects extends Tool
{
    public function name(): string
    {
        return 'list_projects';
    }

    public function description(): string
    {
        return 'Lists the projects you can see, with your role, what you may do there (abilities), the progress and the members (name, e-mail, role). Use it first to find a project id or the people of a project. Archived projects (read only) are left out unless you ask for them with archived: true.';
    }

    public function readOnly(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return Schema::input([
            'archived' => Schema::boolean('true: only archived projects (read only). Default false: the others.'),
        ]);
    }

    public function handle(User $actor, array $arguments): ToolResult
    {
        $validated = $this->validate(
            $arguments,
            ['archived' => ['sometimes', 'boolean']],
            ['archived.boolean' => 'archived is true (only archived projects) or false (the others, default).'],
        );

        return ToolResult::json($this->operation($actor, 'ListProjects', ['archived' => (bool) ($validated['archived'] ?? false)])['projects'] ?? []);
    }
}
