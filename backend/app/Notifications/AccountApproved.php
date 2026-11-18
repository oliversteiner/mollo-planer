<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Your account is released": the person can sign in. (App\Actions\ReviewRegistration) */
class AccountApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
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
        return (new MailMessage)
            ->subject(__('mail.account_approved.subject', ['app' => config('app.name')]))
            ->greeting(__('mail.greeting', ['name' => $notifiable->name]))
            ->line(__('mail.account_approved.line'))
            ->action(__('mail.account_approved.action'), rtrim((string) config('app.frontend_url'), '/').'/login');
    }
}
