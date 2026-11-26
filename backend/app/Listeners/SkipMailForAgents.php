<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Notifications\Events\NotificationSending;

/**
 * No mail to an agent account, whatever the notification (Auftrag 58 F): its address is an identifier, not a mailbox
 * (<name>@agent.invalid for new agents). The notification sender asks this event before each channel; false stops the
 * mail before it is built. Other channels are left alone.
 */
class SkipMailForAgents
{
    public function handle(NotificationSending $event): ?bool
    {
        if ($event->channel === 'mail' && $event->notifiable instanceof User && $event->notifiable->isAgent()) {
            return false;
        }

        return null;
    }
}
