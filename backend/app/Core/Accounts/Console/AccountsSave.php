<?php

namespace App\Core\Accounts\Console;

use App\Core\Accounts\Local\AccountSnapshots;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/** Saves the accounts of the local database (Auftrag 45 A). Never prints a password, a secret or a token. */
#[Signature('app:accounts-save')]
#[Description('Local only: save all accounts with their credentials to a file outside the repository (LOCAL_ACCOUNTS_BACKUP_DIR)')]
class AccountsSave extends Command
{
    public function handle(AccountSnapshots $snapshots): int
    {
        try {
            $path = $snapshots->save();
        } catch (Throwable $error) {
            $this->components->error($error->getMessage());

            return self::FAILURE;
        }

        if ($path === null) {
            $this->components->warn(__('console.local_accounts.nothing'));

            return self::SUCCESS;
        }

        $this->components->info(__('console.local_accounts.saved', ['file' => $path]));

        return self::SUCCESS;
    }
}
