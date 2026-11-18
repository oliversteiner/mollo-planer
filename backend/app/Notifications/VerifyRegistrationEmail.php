<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

/**
 * Confirmation mail after self-registration. Laravel's own mail is English
 * only; this one takes its texts from lang/de and lang/en. Queued like the
 * other mails (the registration does not wait for the mail server).
 *
 * The link is signed and valid for auth.verification.expire minutes (60). It
 * always starts from APP_URL: in the queue there is no request whose host could
 * be used, and a mail sent in a request must carry the same signature base as
 * one sent from the queue. It points at the API; the API hands over to the
 * frontend page (EmailVerificationController).
 */
class VerifyRegistrationEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.verify_email.subject'))
            ->greeting(__('mail.greeting', ['name' => $notifiable->name]))
            ->line(__('mail.verify_email.intro', ['app' => config('app.name')]))
            ->action(__('mail.verify_email.action'), $this->verificationUrl($notifiable))
            ->line(__('mail.verify_email.expiry', ['minutes' => (int) config('auth.verification.expire', 60)]))
            ->line(__('mail.verify_email.approval'))
            ->line(__('mail.verify_email.ignore'));
    }

    protected function verificationUrl($notifiable): string
    {
        $base = (string) config('app.url');

        URL::forceRootUrl($base);
        URL::forceScheme(parse_url($base, PHP_URL_SCHEME) ?: 'https');

        try {
            return parent::verificationUrl($notifiable);
        } finally {
            URL::forceRootUrl(null);
            URL::forceScheme(null);
        }
    }
}
