<?php

namespace App\Services;

use App\Enums\ReleaseStatus;
use App\Enums\TaskStatus;
use App\Models\Milestone;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rules of releases and commits (spec/format-v2.md, §5.3). Checked whenever a
 * milestone or a task is saved, so every way in (GraphQL, import, MCP, seeders)
 * keeps them. Derived statuses are written quietly and do not pass here.
 */
final class ReleaseRules
{
    private const VERSION = '/^\d+\.\d+$/';

    private const HASH = '/^[a-z0-9]{7,40}$/';

    /** How many task titles a message names. */
    private const LISTED = 5;

    private static bool $suspended = false;

    /**
     * Runs the work without the checks at each save. The import checks the
     * result as a whole: a milestone may be released before its last task
     * is written.
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
    public static function checkMilestone(Milestone $milestone, bool $always = false): void
    {
        if (self::$suspended && ! $always) {
            return;
        }

        $errors = [];
        $version = $milestone->release_version;
        $status = $milestone->release_status;

        if (($version === null) !== ($status === null)) {
            $errors['input.releaseStatus'] = __('release.pair');
        }

        if ($version !== null && preg_match(self::VERSION, $version) !== 1) {
            $errors['input.releaseVersion'] = __('release.version_format');
        }

        if ($status === ReleaseStatus::RELEASED && $milestone->exists) {
            $messages = self::releaseBlockers($milestone);

            if ($messages !== []) {
                $errors['input.releaseStatus'] = $messages;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Why the milestone cannot be released, one message per reason, each
     * naming the tasks (docs/fix-m1-abnahme.md F3.8).
     *
     * @return list<string>
     */
    private static function releaseBlockers(Milestone $milestone): array
    {
        $tasks = Task::query()
            ->join('todos', 'todos.id', '=', 'tasks.todo_id')
            ->where('todos.milestone_id', $milestone->getKey())
            ->orderBy('todos.position')
            ->orderBy('tasks.id')
            ->get(['tasks.title', 'tasks.status', 'tasks.commit']);

        $messages = [];

        $open = $tasks->filter(fn (Task $task): bool => $task->status !== TaskStatus::DONE)
            ->map(fn (Task $task): string => in_array($task->status, [TaskStatus::IDEA, TaskStatus::PLANNED], true)
                ? $task->title.' ('.__('release.status.'.$task->status->value).')'
                : $task->title);
        if ($open->isNotEmpty()) {
            $messages[] = __('release.not_done', ['tasks' => self::listOf($open->values()->all())]);
        }

        $withoutCommit = $tasks->filter(fn (Task $task): bool => $task->status === TaskStatus::DONE && $task->commit === null)
            ->map(fn (Task $task): string => $task->title);
        if ($withoutCommit->isNotEmpty()) {
            $messages[] = __('release.commit_missing_tasks', ['tasks' => self::listOf($withoutCommit->values()->all())]);
        }

        return $messages;
    }

    /**
     * At most five titles, then "and 3 more".
     *
     * @param  list<string>  $titles
     */
    private static function listOf(array $titles): string
    {
        $shown = implode(', ', array_slice($titles, 0, self::LISTED));
        $more = count($titles) - self::LISTED;

        return $more > 0 ? __('release.and_more', ['list' => $shown, 'count' => $more]) : $shown;
    }

    /**
     * @throws ValidationException
     */
    public static function checkTask(Task $task, bool $always = false): void
    {
        if (self::$suspended && ! $always) {
            return;
        }

        $errors = [];
        $commit = $task->commit;

        if ($commit !== null && $commit !== 'none' && preg_match(self::HASH, $commit) !== 1) {
            $errors['input.commit'] = __('release.commit_format');
        }

        if ($commit === 'none' && $task->status !== TaskStatus::DONE) {
            $errors['input.commit'] = __('release.none_only_done');
        }

        $milestoneStatus = DB::table('todos')
            ->join('milestones', 'milestones.id', '=', 'todos.milestone_id')
            ->where('todos.id', $task->todo_id)
            ->value('milestones.release_status');

        if ($milestoneStatus === ReleaseStatus::RELEASED->value) {
            if ($task->status !== TaskStatus::DONE) {
                $errors['status'] = __('release.task_not_done');
            } elseif ($commit === null) {
                $errors['input.commit'] = __('release.commit_missing');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
