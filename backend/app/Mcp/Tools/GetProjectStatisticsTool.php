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

#[Name('get_project_statistics')]
#[Description('The numbers of one project (project_id), the same as the page "Statistik" in the app: milestones (released, planned, without version), todos, tasks, the share of done tasks, tasks per status, per label (also 0; label null = without label), per person (open and done; user null = not assigned; isAgent marks agent accounts), open tasks by due date (overdue, due within 7 days, without date), one row per milestone in plan order (m-<id>, release, todos, tasks, progress), tasks with a commit and the number of comments. "Open" means neither done nor an idea. Percentages are whole numbers. Items in the trash do not count. Read only; use it instead of reading the whole plan when only the numbers matter.')]
#[IsReadOnly]
class GetProjectStatisticsTool extends Tool
{
    use RunsGraphQL;

    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            ['project_id' => ['required']],
            ['project_id.required' => 'Give the project_id (see list_projects).'],
        );

        return $this->respond(function () use ($validated): Response {
            $statistics = $this->operation('ProjectStatistics', ['id' => (string) $validated['project_id']])['projectStatistics'];

            $statistics['tasksByStatus'] = array_map(fn (array $row): array => ['status' => strtolower($row['status'])] + $row, $statistics['tasksByStatus']);
            $statistics['perMilestone'] = array_map(function (array $row): array {
                $milestone = $row['milestone'];
                $release = $milestone['release'];

                return [
                    'id' => "m-{$milestone['id']}",
                    'title' => $milestone['title'],
                    'release' => $release === null ? null : ['version' => $release['version'], 'status' => strtolower($release['status']), 'date' => $release['date']],
                    'todos' => $row['todos'],
                    'tasks' => $row['tasks'],
                    'progress' => $row['progress'],
                ];
            }, $statistics['perMilestone']);

            return Response::json($statistics);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Id of the project (see list_projects).')->required(),
        ];
    }
}
