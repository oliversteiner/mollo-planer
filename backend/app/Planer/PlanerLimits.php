<?php

namespace App\Planer;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use Illuminate\Validation\ValidationException;

/**
 * The limits of the planer file (spec/format-v2.md §2 and §8), so that every
 * project can be exported. The one place for the numbers: rules, services and
 * the import read them here; SchemaAgreementTest compares them with
 * spec/planer-v2.schema.json.
 *
 * Elements in the trash do not count.
 */
final class PlanerLimits
{
    public const MILESTONES_PER_PROJECT = 30;

    public const TODOS_PER_MILESTONE = 50;

    public const TASKS_PER_TODO = 50;

    public const MEMBERS_PER_PROJECT = 50;

    public const LABELS_PER_PROJECT = TemplateContent::MAX_LABELS;

    /** Characters of the AI context of a project: its text and the text of its file together. */
    public const AI_CONTEXT_CHARACTERS = 20_000;

    /** Characters of a description as canonical Markdown (project, milestone, todo, task). */
    public const DESCRIPTION_MARKDOWN = 5000;

    private static bool $suspended = false;

    /**
     * Runs the work without the step-by-step checks of the counts. The import
     * checks the result as a whole (LimitCheck): while it works, a todo may
     * be moved before another one leaves.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public static function withoutChecks(callable $work): mixed
    {
        $before = self::$suspended;
        self::$suspended = true;

        try {
            return $work();
        } finally {
            self::$suspended = $before;
        }
    }

    /**
     * @throws ValidationException
     */
    public static function assertMilestoneRoom(int|string $projectId, int $adding = 1): void
    {
        if (self::$suspended) {
            return;
        }

        $count = Milestone::query()->where('project_id', $projectId)->count();

        self::assertRoom($count, $adding, self::MILESTONES_PER_PROJECT, 'projects.limit_milestones');
    }

    /**
     * @throws ValidationException
     */
    public static function assertTodoRoom(int|string $milestoneId, int $adding = 1): void
    {
        if (self::$suspended) {
            return;
        }

        $count = Todo::query()->where('milestone_id', $milestoneId)->count();

        self::assertRoom($count, $adding, self::TODOS_PER_MILESTONE, 'projects.limit_todos');
    }

    /**
     * @throws ValidationException
     */
    public static function assertTaskRoom(int|string $todoId, int $adding = 1): void
    {
        if (self::$suspended) {
            return;
        }

        $count = Task::query()->where('todo_id', $todoId)->count();

        self::assertRoom($count, $adding, self::TASKS_PER_TODO, 'projects.limit_tasks');
    }

    /**
     * @throws ValidationException
     */
    public static function assertMemberRoom(Project $project, int $adding = 1): void
    {
        if (self::$suspended) {
            return;
        }

        self::assertRoom($project->members()->count(), $adding, self::MEMBERS_PER_PROJECT, 'projects.limit_members');
    }

    /**
     * @throws ValidationException
     */
    private static function assertRoom(int $count, int $adding, int $max, string $message): void
    {
        if (self::$suspended) {
            return;
        }

        if ($count + $adding > $max) {
            throw ValidationException::withMessages(['limit' => __($message, ['max' => $max])]);
        }
    }
}
