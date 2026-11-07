<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Someone commented on a task the recipient is assigned to or has
 * commented on. The comment text is not part of the mail.
 */
class TaskCommented extends TaskNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        $replace = $this->replacements();

        return (new MailMessage)
            ->subject(__('mail.task_commented.subject', $replace))
            ->greeting(__('mail.greeting', ['name' => $notifiable->name]))
            ->line(__('mail.task_commented.intro', $replace))
            ->line(__('mail.task.location', $replace))
            ->action(__('mail.task_commented.action'), $this->taskUrl())
            ->line(__('mail.settings_hint'));
    }
}
