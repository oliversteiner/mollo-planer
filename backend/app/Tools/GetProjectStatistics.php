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
        return 'The numbers of one project (project_id), the same as the page "Statistik" in the app: milestones (released, planned, without version), todos, tasks, the share of done tasks, tasks per status, per label (also 0; label null = without label), per person (open and done; user null = not assigned; isAgent marks agent accounts), open tasks by due date (overdue, due within 7 days, without date), one row per milestone in plan order (m-<id>, release, todos, tasks, progress), done tasks per week of the last 12 weeks (done_by_week: doneByWeek, weekStart is the Monday; tasks done before the time was recorded are not in it), tasks with a commit and the number of comments. "Open" means neither done nor an idea. Percentages are whole numbers. Items in the trash do not count. Read only; use it instead of reading the whole plan when only the numbers matter.';
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
