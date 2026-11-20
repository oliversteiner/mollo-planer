<?php

namespace App\Tools;

use App\Models\User;

class ListTemplates extends Tool
{
    public function name(): string
    {
        return 'list_templates';
    }

    public function description(): string
    {
        return 'Lists the templates for new projects (built-in ones first): id, name, description, language, the labels and the outline of milestones, todos and tasks. Pass a template id as template_id to create_project to start a project with the labels and the outline of the template.';
    }

    public function readOnly(): bool
    {
        return true;
    }

    public function handle(User $actor, array $arguments): ToolResult
    {
        return ToolResult::json($this->operation($actor, 'ListTemplates')['templates'] ?? []);
    }
}
