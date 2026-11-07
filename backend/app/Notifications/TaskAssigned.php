<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * A task was created for or assigned to the recipient.
 */
class TaskAssigned extends TaskNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        $replace = $this->replacements();

        $mail = (new MailMessage)
            ->subject(__('mail.task_assigned.subject', $replace))
            ->greeting(__('mail.greeting', ['name' => $notifiable->name]))
            ->line(__('mail.task_assigned.intro', $replace))
            ->line(__('mail.task.location', $replace));

        if ($this->task->due_date !== null) {
            $mail->line(__('mail.task.due', ['date' => $this->task->due_date->format(__('mail.date_format'))]));
        }

        return $mail
            ->action(__('mail.task.action'), $this->taskUrl())
            ->line(__('mail.settings_hint'));
    }
}
