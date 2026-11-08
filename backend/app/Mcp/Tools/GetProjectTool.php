<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_project')]
#[Description('Shows one project with its whole plan: milestones, their todos and the tasks of each todo (status, due date, assignee). Use it before adding to a plan, so nothing is created twice.')]
#[IsReadOnly]
class GetProjectTool extends Tool
{
    use RunsGraphQL;

    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            ['project_id' => ['required']],
            ['project_id.required' => 'Give the project_id, for example from list_projects.'],
        );

        return $this->respond(function () use ($validated) {
            $project = $this->graphQL('query ($id: ID!) {
                project(id: $id) {
                    id name description viewerRole viewerAbilities
                    members { role user { id name email } }
                    milestones {
                        id title dueDate status
                        todos {
                            id title dueDate status
                            tasks { id title status dueDate assignee { name email } }
                        }
                    }
                }
            }', ['id' => (string) $validated['project_id']])['project'] ?? null;

            return $project === null
                ? Response::error('This project does not exist, or you have no access to it.')
                : Response::json($project);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Id of the project (from list_projects).')->required(),
        ];
    }
}
