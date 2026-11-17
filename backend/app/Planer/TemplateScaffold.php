<?php

namespace App\Planer;

use App\Enums\TaskStatus;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Template;
use App\Models\Todo;
use App\Planer\RichText\InvalidMarkdown;
use App\Planer\RichText\MarkdownToTiptap;
use App\Planer\RichText\Mode;
use App\Services\KanbanOrdering;
use App\Services\TaskLabels;
use Illuminate\Validation\ValidationException;

/**
 * Lays out the outline of a template in a new project: milestones, todos and
 * tasks in the order of the template (position), every task "planned" and one
 * after the other in the column "planned" of the board, with the labels of the
 * project. Descriptions are Markdown and become rich text (strict). Meant for the
 * transaction that creates the project, which then counts as one change; the
 * import of a planer file (I2) takes this over.
 */
class TemplateScaffold
{
    public function __construct(
        private readonly TaskLabels $labels,
        private readonly KanbanOrdering $kanban,
    ) {}

    /**
     * @throws ValidationException When a description of the template is not valid
     */
    public function apply(Project $project, Template $template): void
    {
        $labelIds = $project->labels()->pluck('id', 'key');
        $content = $template->content ?? [];

        foreach ((array) ($content['milestones'] ?? []) as $m => $milestone) {
            $item = new Milestone([
                'title' => (string) $milestone['title'],
                'description' => $this->document($milestone['description'] ?? null, "milestones.{$m}"),
                'icon' => $milestone['icon'] ?? null,
            ]);
            $item->forceFill([
                'project_id' => $project->getKey(),
                'release_version' => $milestone['release']['version'] ?? null,
                'release_status' => isset($milestone['release']) ? ($milestone['release']['status'] ?? 'planned') : null,
            ])->save();

            foreach ((array) ($milestone['todos'] ?? []) as $t => $todo) {
                $todoItem = new Todo([
                    'milestone_id' => $item->getKey(),
                    'title' => (string) $todo['title'],
                    'description' => $this->document($todo['description'] ?? null, "milestones.{$m}.todos.{$t}"),
                    'icon' => $todo['icon'] ?? null,
                ]);
                $todoItem->save();

                foreach ((array) ($todo['tasks'] ?? []) as $k => $task) {
                    $this->task($todoItem, $task, "milestones.{$m}.todos.{$t}.tasks.{$k}", $labelIds->all(), $project->getKey());
                }
            }
        }
    }

    /**
     * The description of the template as the description of a project that has none of its own.
     *
     * @return array<string, mixed>|null
     *
     * @throws ValidationException
     */
    public function description(Template $template): ?array
    {
        return $this->document($template->content['project']['description'] ?? null, 'project');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $labelIds  By key
     */
    private function task(Todo $todo, array $data, string $path, array $labelIds, int|string $projectId): void
    {
        $task = new Task([
            'todo_id' => $todo->getKey(),
            'title' => (string) $data['title'],
            'description' => $this->document($data['description'] ?? null, $path),
            'icon' => $data['icon'] ?? null,
            'user_story' => $data['userStory'] ?? null,
        ]);
        $task->forceFill([
            'status' => TaskStatus::PLANNED,
            'board_position' => $this->kanban->nextPosition($projectId, TaskStatus::PLANNED),
        ])->save();

        $ids = [];
        foreach ((array) ($data['labels'] ?? []) as $key) {
            if (isset($labelIds[$key])) {
                $ids[] = $labelIds[$key];
            }
        }

        if ($ids !== []) {
            $this->labels->set($task, $ids);
        }
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws ValidationException
     */
    private function document(?string $markdown, string $path): ?array
    {
        if ($markdown === null || trim($markdown) === '') {
            return null;
        }

        try {
            $doc = MarkdownToTiptap::convert($markdown, Mode::Strict)->doc;
        } catch (InvalidMarkdown $error) {
            throw ValidationException::withMessages(['input.templateId' => __('labels.template_invalid', ['path' => "{$path}.description", 'problem' => $error->getMessage()])]);
        }

        return $doc['content'] === [] ? null : $doc;
    }
}
