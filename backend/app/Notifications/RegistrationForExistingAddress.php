<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Somebody registered with an address that already has an account (docs: Auftrag 22 O3). The
 * answer of the registration is the same as for a new address; this mail is how the owner hears of it.
 * The account is not changed, so a stranger cannot take over a registration that is still waiting.
 *
 * Confirmed account: "you already have an account", links to sign in and to "forgot password".
 * Account never confirmed (maybe by somebody else): the registration is still open, the links lead
 * to the page that sends the confirmation mail again (/verify-email?status=resend, no error message) and to "forgot password".
 */
class RegistrationForExistingAddress extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly bool $confirmed)
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
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $key = $this->confirmed ? 'mail.already_registered' : 'mail.registration_pending';
        $primary = $this->confirmed ? $frontend.'/login' : $frontend.'/verify-email?status=resend';

        return (new MailMessage)
            ->subject(__("$key.subject", ['app' => config('app.name')]))
            ->greeting(__('mail.greeting', ['name' => $notifiable->name]))
            ->line(__("$key.intro", ['app' => config('app.name')]))
            ->action(__("$key.action"), $primary)
            ->line(__('mail.already_registered.forgot', ['url' => $frontend.'/forgot-password']))
            ->line(__('mail.already_registered.ignore'));
    }
}
