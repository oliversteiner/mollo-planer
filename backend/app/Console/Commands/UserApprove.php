<?php

namespace App\Console\Commands;

use App\Actions\ReviewRegistration;
use App\Console\Concerns\ManagesUserAccounts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;

/**
 * Releases a self-registered account that is waiting for approval, the way an admin does in the app
 * (approveUser): the same action, so the person gets the same mail in the language of the account.
 * An account that is released already stays as it is and gets no second mail.
 */
#[Signature('app:user-approve {user : E-mail address or id of the user}')]
#[Description('Release a waiting account (sends the approval mail to the person)')]
class UserApprove extends Command implements PromptsForMissingInput
{
    use ManagesUserAccounts;

    public function handle(ReviewRegistration $review): int
    {
        $user = $this->userOrFail();

        if ($user === null) {
            return self::FAILURE;
        }

        if ($user->isApproved()) {
            $this->components->info(__('console.user_approve.already', ['name' => $user->name]));

            return self::SUCCESS;
        }

        if (! $this->requireTerminal() || ! $this->confirmWrite(__('console.user_approve.confirm', ['name' => $user->name, 'email' => $user->email]))) {
            return self::FAILURE;
        }

        $review->approve($user);

        $this->logIntervention($user, 'approve');
        $this->components->info(__('console.user_approve.done', ['name' => $user->name]));

        if (! $user->hasVerifiedEmail()) {
            $this->components->warn(__('console.user_approve.unverified'));
        }

        return self::SUCCESS;
    }
}
