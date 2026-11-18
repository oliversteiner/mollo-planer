<?php

namespace App\Planer;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Todo;
use App\Planer\RichText\TiptapToMarkdown;
use RuntimeException;

/**
 * The planer-v2 file of a project (docs/auftrag-m4.md §3, spec/format-v2.md):
 * the document as data, and its canonical text. Only what is not in the
 * trash. Reads only, the revision does not change.
 *
 * The result is checked against the schema and the rules (Validator). Since
 * the app enforces the limits of the file (PlanerLimits), a failure can only
 * come from old data; planer:check-limits lists those places.
 */
final class Export
{
    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeException With the paths, when the project breaks the format
     */
    public function document(Project $project): array
    {
        $raw = [
            'format' => 'planer-v2',
            'revision' => (int) $project->planer_revision,
            'project' => $this->project($project),
            'milestones' => $this->milestones($project),
        ];

        // The file has no null and no empty label list: what is checked is what is written.
        $document = json_decode(Canonical::text($raw), true, 512, JSON_THROW_ON_ERROR);
        $errors = Validator::errors($document);

        if ($errors !== []) {
            throw new RuntimeException('The project cannot be exported: '.implode('; ', array_map(
                fn (array $issue): string => "{$issue['path']}: {$issue['message']}",
                array_slice($errors, 0, 10),
            )));
        }

        return $document;
    }

    public function text(Project $project): string
    {
        return Canonical::text($this->document($project));
    }

    /**
     * @return array<string, mixed>
     */
    private function project(Project $project): array
    {
        $members = $project->members()->get()
            ->sortBy(fn ($user): string => mb_strtolower($user->name).'|'.mb_strtolower($user->email))
            ->map(fn ($user): array => [
                'email' => $user->email,
                'role' => Role::cached($user->membership->role_id)?->key ?? throw new RuntimeException("Member {$user->email} has no role."),
            ])
            ->values()
            ->all();

        $labels = $project->labels()->orderBy('position')->orderBy('id')->get()
            ->map(fn ($label): array => [
                'key' => $label->key,
                'names' => array_map('strval', (array) $label->names),
                'category' => $label->category === '' ? null : $label->category,
                'color' => $label->color->value,
            ])
            ->all();

        return [
            'name' => $project->name,
            'description' => $this->markdown($project->description),
            'locale' => $project->locale->value,
            'members' => $members,
            'labels' => $labels,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function milestones(Project $project): array
    {
        $labelOrder = $project->labels()->orderBy('position')->orderBy('id')->pluck('key')->flip()->all();

        $milestones = Milestone::query()
            ->where('project_id', $project->getKey())
            ->orderBy('position')->orderBy('id')
            ->with(['todos.tasks' => fn ($tasks) => $tasks->with(['labels', 'assignee'])])
            ->get();

        return $milestones->map(fn (Milestone $milestone): array => [
            'id' => "m-{$milestone->getKey()}",
            'title' => $milestone->title,
            'description' => $this->markdown($milestone->description),
            'dueDate' => $milestone->due_date?->format('Y-m-d'),
            'release' => $milestone->release_version === null || $milestone->release_status === null
                ? null
                : ['version' => $milestone->release_version, 'status' => $milestone->release_status->value],
            'todos' => $milestone->todos->map(fn (Todo $todo): array => [
                'id' => "t-{$todo->getKey()}",
                'title' => $todo->title,
                'description' => $this->markdown($todo->description),
                'dueDate' => $todo->due_date?->format('Y-m-d'),
                'tasks' => $todo->tasks->map(fn (Task $task): array => $this->task($task, $labelOrder))->values()->all(),
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * @param  array<string, int>  $labelOrder  Label key => place in the project
     * @return array<string, mixed>
     */
    private function task(Task $task, array $labelOrder): array
    {
        return [
            'id' => "k-{$task->getKey()}",
            'title' => $task->title,
            'description' => $this->markdown($task->description),
            'dueDate' => $task->due_date?->format('Y-m-d'),
            'status' => $task->status->value,
            'assigneeEmail' => $task->assignee?->email,
            'labels' => $task->labels
                ->map(fn ($label): string => $label->key)
                ->filter(fn (string $key): bool => isset($labelOrder[$key]))
                ->sortBy(fn (string $key): int => $labelOrder[$key])
                ->values()
                ->all(),
            'commit' => $task->commit,
            'userStory' => $task->user_story === null ? null : [
                'role' => $task->user_story['role'],
                'goal' => $task->user_story['goal'],
                'benefit' => $task->user_story['benefit'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $document
     */
    private function markdown(?array $document): ?string
    {
        if ($document === null) {
            return null;
        }

        $text = TiptapToMarkdown::convert($document);

        return $text === '' ? null : $text;
    }
}
