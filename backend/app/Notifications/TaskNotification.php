<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\ProjectPermissions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Base for mails about a task. Queued, sent after the transaction commits,
 * in the language of the project (not of the recipient).
 *
 * The recipients are chosen by App\Services\TaskNotifier. When the queued
 * job runs, shouldSend() checks again with fresh data, because the
 * recipient may have turned mails off or left the project in the meantime.
 */
abstract class TaskNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The task may be deleted before the queue runs; then drop the job.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * @param  string  $actorName  Stored as text, the actor may be deleted later.
     */
    public function __construct(public readonly Task $task, public readonly string $actorName)
    {
        $this->afterCommit();
        $this->locale($task->owningProject()->locale->value);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $notifiable instanceof User
            && $notifiable->notify_by_email
            && ProjectPermissions::canView($notifiable, $this->project());
    }

    protected function project(): Project
    {
        return $this->task->owningProject();
    }

    /**
     * Kanban of the project with the task opened. The frontend route is
     * defined here only, change it here when the frontend is built.
     */
    protected function taskUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/')
            .'/projects/'.$this->project()->getKey().'/kanban?'.http_build_query(['task' => $this->task->getKey()]);
    }

    /**
     * Placeholders shared by the mail texts.
     *
     * @return array<string, string>
     */
    protected function replacements(): array
    {
        return [
            'actor' => $this->actorName,
            'task' => $this->task->title,
            'todo' => $this->task->todo->title,
            'project' => $this->project()->name,
        ];
    }
}
