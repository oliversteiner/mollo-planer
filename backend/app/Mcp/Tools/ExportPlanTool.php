<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\GraphQLRunner;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('export_plan')]
#[Description('Exports the whole plan of a project as a planer-v2 document: project, members, labels, milestones, their todos and the tasks of each todo, with the revision. Use it before any change, so you work on the current state. The ids (m-<id>, t-<id>, k-<id>) are the ones of the app.')]
#[IsReadOnly]
class ExportPlanTool extends Tool
{
    use RunsGraphQL;

    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            ['project_id' => ['required']],
            ['project_id.required' => 'Give the project_id, for example from list_projects.'],
        );

        return $this->respond(function () use ($validated): Response {
            $data = app(GraphQLRunner::class)->run(
                'query ($id: ID!) { exportPlan(projectId: $id) }',
                ['id' => (string) $validated['project_id']],
            );

            return Response::json(['document' => json_decode((string) $data['exportPlan'], true, 512, JSON_THROW_ON_ERROR)]);
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
