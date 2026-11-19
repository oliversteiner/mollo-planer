<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your registration was not released": no reason, no link, and no name or address of an admin. The name is
 * kept in the notification, because the account does not exist any more when the mail is sent
 * (it goes to an address, not to a model: App\Actions\ReviewRegistration).
 */
class AccountRejected extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject(__('mail.account_rejected.subject', ['app' => config('app.name')]))
            ->greeting(__('mail.greeting', ['name' => $this->name]))
            ->line(__('mail.account_rejected.line', ['app' => config('app.name')]))
            ->line(__('mail.account_rejected.contact'));
    }
}
