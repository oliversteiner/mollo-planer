<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCommented;
use App\Support\ProjectPermissions;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Decides who gets a mail about a task:
 * - new task or new assignment: the assignee
 * - new comment: the assignee and everyone who commented on the task before
 *
 * Never the actor, never someone with mails turned off, never someone who
 * can no longer see the project.
 */
class TaskNotifier
{
    /**
     * Assignment mails are held back while true (bulk import by an MCP
     * tool that asks for it). Comments always notify.
     */
    private bool $assignmentMailsMuted = false;

    /**
     * Runs the callback without assignment mails, for example when an AI
     * client creates a whole project plan at once.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function withoutAssignmentMails(Closure $callback): mixed
    {
        $previous = $this->assignmentMailsMuted;
        $this->assignmentMailsMuted = true;

        try {
            return $callback();
        } finally {
            $this->assignmentMailsMuted = $previous;
        }
    }

    public function assigned(Task $task, User $actor): void
    {
        if ($this->assignmentMailsMuted) {
            return;
        }

        $recipients = $this->filter(collect([$task->assignee]), $task, $actor);

        Notification::send($recipients, new TaskAssigned($task, $actor->name));
    }

    public function commented(Comment $comment, User $actor): void
    {
        $task = $comment->task;

        $commenters = User::query()
            ->whereIn('id', $task->comments()->reorder()->whereKeyNot($comment->getKey())->select('user_id'))
            ->get();

        $recipients = $this->filter($commenters->prepend($task->assignee), $task, $actor);

        Notification::send($recipients, new TaskCommented($task, $actor->name));
    }

    /**
     * @param  Collection<int, User|null>  $users
     * @return Collection<int, User>
     */
    private function filter(Collection $users, Task $task, User $actor): Collection
    {
        $project = $task->owningProject();

        return $users
            ->filter()
            ->unique(fn (User $user) => $user->getKey())
            ->reject(fn (User $user) => $user->is($actor))
            ->filter(fn (User $user) => $user->notify_by_email && ProjectPermissions::canView($user, $project))
            ->values();
    }
}
