<?php

namespace App\Actions;

use App\Models\User;
use App\Notifications\AccountApproved;
use App\Notifications\AccountRejected;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The decision of an admin about a self-registered account (docs: Auftrag 22 O8). The person hears of it by
 * mail, in the language of the account (users.locale, otherwise APP_LOCALE, not the admin's), whichever way
 * the decision is made: the mail belongs to the decision, not to one mutation.
 */
class ReviewRegistration
{
    /** Releases the account. A second call changes nothing and sends no second mail. */
    public function approve(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            if ($user->approved_at === null) {
                $user->forceFill(['approved_at' => now()])->save();
                $user->notify((new AccountApproved)->locale($user->accountLocale()));
            }

            return $user;
        });
    }

    /**
     * Deletes the waiting account and tells the person. The mail is queued after the commit and carries
     * what it needs (name, address, language) itself, because the account is gone when it is sent.
     */
    public function reject(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            Notification::route('mail', [$user->email => $user->name])
                ->notify((new AccountRejected($user->name))->locale($user->accountLocale()));

            $user->delete();

            return $user;
        });
    }
}
