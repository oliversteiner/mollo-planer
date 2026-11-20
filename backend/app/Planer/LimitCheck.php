<?php

namespace App\Planer;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Planer\RichText\TiptapToMarkdown;
use Illuminate\Database\Eloquent\Model;

/**
 * Finds where existing data breaks the limits of the planer file
 * (PlanerLimits). Reads only. Used by planer:check-limits; a project with
 * findings cannot be exported until they are cleaned up.
 */
final class LimitCheck
{
    /**
     * @return list<array{path: string, message: string}> The place in the project and what is wrong
     */
    public function forProject(Project $project): array
    {
        $found = [];
        $add = function (string $path, string $key, array $parameters) use (&$found): void {
            $found[] = ['path' => $path, 'message' => __($key, $parameters)];
        };

        $this->description($project, 'project', $add);

        $milestones = Milestone::query()->where('project_id', $project->getKey())->orderBy('position')->get();

        if ($milestones->count() > PlanerLimits::MILESTONES_PER_PROJECT) {
            $add('project', 'projects.limit_milestones', ['max' => PlanerLimits::MILESTONES_PER_PROJECT]);
        }

        if ($project->members()->count() > PlanerLimits::MEMBERS_PER_PROJECT) {
            $add('project.members', 'projects.limit_members', ['max' => PlanerLimits::MEMBERS_PER_PROJECT]);
        }

        if ($project->labels()->count() > PlanerLimits::LABELS_PER_PROJECT) {
            $add('project.labels', 'labels.too_many', ['max' => PlanerLimits::LABELS_PER_PROJECT]);
        }

        // All todos and tasks of the project in one query each, grouped here (not one query per milestone and per todo).
        $todosByMilestone = Todo::query()->where('project_id', $project->getKey())->orderBy('position')->get()->groupBy('milestone_id');
        $tasksByTodo = Task::query()->where('project_id', $project->getKey())->get()->groupBy('todo_id');

        foreach ($milestones as $mi => $milestone) {
            $this->description($milestone, "milestones[$mi]", $add);
            $todos = $todosByMilestone->get($milestone->getKey(), collect());

            if ($todos->count() > PlanerLimits::TODOS_PER_MILESTONE) {
                $add("milestones[$mi]", 'projects.limit_todos', ['max' => PlanerLimits::TODOS_PER_MILESTONE]);
            }

            foreach ($todos as $ti => $todo) {
                $this->description($todo, "milestones[$mi].todos[$ti]", $add);
                $tasks = $tasksByTodo->get($todo->getKey(), collect());

                if ($tasks->count() > PlanerLimits::TASKS_PER_TODO) {
                    $add("milestones[$mi].todos[$ti]", 'projects.limit_tasks', ['max' => PlanerLimits::TASKS_PER_TODO]);
                }

                foreach ($tasks as $ki => $task) {
                    $this->description($task, "milestones[$mi].todos[$ti].tasks[$ki]", $add);
                }
            }
        }

        return $found;
    }

    /**
     * @param  callable(string, string, array<string, mixed>): void  $add
     */
    private function description(Model $model, string $path, callable $add): void
    {
        $document = $model->getAttribute('description');

        if (! is_array($document)) {
            return;
        }

        $length = mb_strlen(TiptapToMarkdown::convert($document));

        if ($length > PlanerLimits::DESCRIPTION_MARKDOWN) {
            $add("$path.description", 'richtext.markdown_too_long', ['attribute' => 'description', 'max' => PlanerLimits::DESCRIPTION_MARKDOWN, 'length' => $length]);
        }
    }
}
