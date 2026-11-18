<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

/**
 * "Something about your sign-in changed" (docs: Auftrag 22 O5): the password, the second factor or the
 * recovery codes. It names what changed and says what to do when it was not the person. No password, no
 * link (a link in such a mail would be what a fraudulent mail looks like).
 */
class SecurityChange extends Notification implements ShouldQueue
{
    use Queueable;

    public const PASSWORD = 'password';

    public const AUTHENTICATOR_ADDED = 'authenticator_added';

    public const AUTHENTICATOR_REMOVED = 'authenticator_removed';

    public const PASSKEY_ADDED = 'passkey_added';

    public const PASSKEY_REMOVED = 'passkey_removed';

    public const RECOVERY_CODES = 'recovery_codes';

    public const ADMIN_RESET = 'admin_reset';

    public function __construct(public readonly string $kind)
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
            ->subject(__("mail.security_change.{$this->kind}.subject", ['app' => config('app.name')]))
            ->greeting(__('mail.greeting', ['name' => $notifiable->name]))
            ->line(__("mail.security_change.{$this->kind}.line", ['app' => config('app.name')]))
            ->line(__(Lang::has("mail.security_change.{$this->kind}.not_you") ? "mail.security_change.{$this->kind}.not_you" : 'mail.security_change.not_you'));
    }
}
