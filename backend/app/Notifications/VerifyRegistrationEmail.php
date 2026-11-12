<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Confirmation mail after self-registration. Laravel's own mail is English
 * only; this one takes its texts from lang/de and lang/en. The link itself
 * is built by Laravel's VerifyEmail (signed, valid for 60 minutes).
 */
class VerifyRegistrationEmail extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.verify_email.subject'))
            ->greeting(__('mail.greeting', ['name' => $notifiable->name]))
            ->line(__('mail.verify_email.intro', ['app' => config('app.name')]))
            ->action(__('mail.verify_email.action'), $this->verificationUrl($notifiable))
            ->line(__('mail.verify_email.approval'))
            ->line(__('mail.verify_email.ignore'));
    }
}
