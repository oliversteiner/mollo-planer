<?php

namespace App\Services;

use App\Enums\ReleaseStatus;
use App\Enums\TaskStatus;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\Todo;
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

    /**
     * @throws ValidationException
     */
    public static function checkMilestone(Milestone $milestone): void
    {
        $errors = [];
        $version = $milestone->release_version;
        $status = $milestone->release_status;

        if (($version === null) !== ($status === null)) {
            $errors['release_status'] = __('release.pair');
        }

        if ($version !== null && preg_match(self::VERSION, $version) !== 1) {
            $errors['release_version'] = __('release.version_format');
        }

        if ($status === ReleaseStatus::RELEASED && $milestone->exists) {
            $tasks = Task::query()->whereIn('todo_id', Todo::query()->where('milestone_id', $milestone->getKey())->select('id'));

            if ($tasks->clone()->where('status', '!=', TaskStatus::DONE->value)->exists()) {
                $errors['release_status'] = __('release.not_done');
            } elseif ($tasks->clone()->where('status', TaskStatus::DONE->value)->whereNull('commit')->exists()) {
                $errors['release_status'] = __('release.commit_missing');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @throws ValidationException
     */
    public static function checkTask(Task $task): void
    {
        $errors = [];
        $commit = $task->commit;

        if ($commit !== null && $commit !== 'none' && preg_match(self::HASH, $commit) !== 1) {
            $errors['commit'] = __('release.commit_format');
        }

        if ($commit === 'none' && $task->status !== TaskStatus::DONE) {
            $errors['commit'] = __('release.none_only_done');
        }

        $milestoneStatus = DB::table('todos')
            ->join('milestones', 'milestones.id', '=', 'todos.milestone_id')
            ->where('todos.id', $task->todo_id)
            ->value('milestones.release_status');

        if ($milestoneStatus === ReleaseStatus::RELEASED->value) {
            if ($task->status !== TaskStatus::DONE) {
                $errors['status'] = __('release.task_not_done');
            } elseif ($commit === null) {
                $errors['commit'] = __('release.commit_missing');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
