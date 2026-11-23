<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the admins that an admin made or revoked a connector access of an agent account. Names only: never
 * the token, no link.
 */
class AgentAccessChange extends Notification implements ShouldQueue
{
    use Queueable;

    public const CREATED = 'created';

    public const REVOKED = 'revoked';

    public function __construct(
        public readonly string $kind,
        public readonly string $admin,
        public readonly string $agent,
        public readonly string $access,
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
        $replace = ['admin' => $this->admin, 'agent' => $this->agent, 'access' => $this->access, 'app' => config('app.name')];

        return (new MailMessage)
            ->subject(__("mail.agent_access.{$this->kind}.subject", $replace))
            ->greeting(__('mail.greeting', ['name' => $notifiable->name]))
            ->line(__("mail.agent_access.{$this->kind}.line", $replace))
            ->line(__('mail.agent_access.not_you'));
    }
}
