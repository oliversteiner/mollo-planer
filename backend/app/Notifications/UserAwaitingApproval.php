<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the admins that a self-registered account has confirmed its mail
 * address and waits for approval.
 */
class UserAwaitingApproval extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $name)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/admin/users';

        return (new MailMessage)
            ->subject(__('mail.awaiting_approval.subject', ['app' => config('app.name')]))
            ->greeting(__('mail.greeting', ['name' => $notifiable->name]))
            ->line(__('mail.awaiting_approval.intro', ['name' => $this->name]))
            ->action(__('mail.awaiting_approval.action'), $url);
    }
}
