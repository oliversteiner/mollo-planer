<?php

namespace App\Tools;

use App\Models\User;
use App\Services\TaskCommentListing;
use App\Services\TaskCommentListingRefused;

class ListComments extends Tool
{
    public function name(): string
    {
        return 'list_comments';
    }

    public function description(): string
    {
        return 'Lists the comments of one task (task_id) or of the tasks of a project (project_id), per task, the newest last. For a project the filters of export_plan apply (version, milestone_ids, task_status, include_released) and only tasks with comments are listed. Each comment has the author (name, e-mail, isAgent: true for an agent account such as Claude), the time and the text as Markdown. Comments are not part of the plan file: export_plan and import_plan never carry them.';
    }

    public function readOnly(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return Schema::input([
            'task_id' => Schema::string('Id of one task, k-<id> or the number.'),
            'project_id' => Schema::string('Id of a project: the comments of its tasks.'),
            'version' => Schema::string('With project_id: only the milestone of this release version.'),
            'milestone_ids' => Schema::list(Schema::string(), 'With project_id: only these milestones.'),
            'task_status' => Schema::list(Schema::string(null, ['idea', 'planned', 'open', 'in_progress', 'review', 'done']), 'With project_id: only tasks with these statuses.'),
            'include_released' => Schema::boolean('With project_id: false leaves out released milestones.'),
        ]);
    }

    public function handle(User $actor, array $arguments): ToolResult
    {
        $validated = $this->validate(
            $arguments,
            [
                'task_id' => ['required_without:project_id'],
                'project_id' => ['required_without:task_id'],
                'version' => ['sometimes', 'nullable', 'string'],
                'milestone_ids' => ['sometimes', 'array'],
                'task_status' => ['sometimes', 'array'],
                'task_status.*' => ['in:idea,planned,open,in_progress,review,done'],
                'include_released' => ['sometimes', 'boolean'],
            ],
            ['task_id.required_without' => 'Give a task_id, or a project_id for the tasks of a project.'],
        );

        try {
            return ToolResult::json(['tasks' => app(TaskCommentListing::class)->list($actor, $validated)]);
        } catch (TaskCommentListingRefused $refused) {
            return ToolResult::error($refused->getMessage());
        }
    }
}
