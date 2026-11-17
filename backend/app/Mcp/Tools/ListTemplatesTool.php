<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_templates')]
#[Description('Lists the templates for new projects (built-in ones first): id, name, description, language, the labels and the outline of milestones, todos and tasks. Pass a template id as template_id to create_project to start a project with the labels and the outline of the template.')]
#[IsReadOnly]
class ListTemplatesTool extends Tool
{
    use RunsGraphQL;

    public function handle(Request $request): Response
    {
        return $this->respond(fn () => Response::json($this->operation('ListTemplates')['templates'] ?? []));
    }
}
