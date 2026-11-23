<?php

namespace App\Tools;

use App\Models\User;

class GetProjectStatistics extends Tool
{
    public function name(): string
    {
        return 'get_project_statistics';
    }

    public function description(): string
    {
        return 'The numbers of a project as on the page Statistik: tasks per status, label and person, due dates, per milestone, done per week. Use it instead of the plan when only numbers matter.';
    }

    public function readOnly(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return Schema::input([
            'project_id' => Schema::string('Id of the project (see list_projects).'),
        ], ['project_id']);
    }

    public function handle(User $actor, array $arguments): ToolResult
    {
        $validated = $this->validate(
            $arguments,
            ['project_id' => ['required']],
            ['project_id.required' => 'Give the project_id (see list_projects).'],
        );

        $statistics = $this->operation($actor, 'ProjectStatistics', ['id' => (string) $validated['project_id']])['projectStatistics'];

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

        return ToolResult::json($statistics);
    }
}
