<?php

namespace App\Core\Accounts;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the admins that an admin created an agent account in the app. Names only, no link. */
class AgentAccountCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $admin,
        public readonly string $agent,
    ) {
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
        $replace = ['admin' => $this->admin, 'agent' => $this->agent, 'app' => config('app.name')];

        return (new MailMessage)
            ->subject(__('mail.agent_account.created.subject', $replace))
            ->greeting(__('mail.greeting', ['name' => $notifiable->name]))
            ->line(__('mail.agent_account.created.line', $replace))
            ->line(__('mail.agent_access.not_you'));
    }
}
