<?php

namespace App\Core\Accounts\Console;

use App\Core\Accounts\Local\AccountSnapshots;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Puts the accounts of a snapshot back into the local database (Auftrag 45 A): by e-mail, the system role by its key.
 * The report names e-mails and what happened, never a password, a secret or a token.
 */
#[Signature('app:accounts-restore {file? : Snapshot (default: the newest in LOCAL_ACCOUNTS_BACKUP_DIR)}')]
#[Description('Local only: put the accounts of a snapshot back (by e-mail; existing accounts are overwritten, missing ones created)')]
class AccountsRestore extends Command
{
    public function handle(AccountSnapshots $snapshots): int
    {
        $file = $this->argument('file') ?? $snapshots->latest();

        if (! is_string($file)) {
            $this->components->error(__('console.local_accounts.no_snapshot', ['dir' => $snapshots->directory()]));

            return self::FAILURE;
        }

        try {
            $report = $snapshots->restore($file);
        } catch (Throwable $error) {
            $this->components->error($error->getMessage());

            return self::FAILURE;
        }

        self::report($this, $report);

        return self::SUCCESS;
    }

    /**
     * @param  array{accounts: list<array{email: string, result: string, note?: string}>, warnings: list<string>}  $report
     */
    public static function report(Command $command, array $report): void
    {
        foreach ($report['warnings'] as $warning) {
            $command->components->warn($warning);
        }

        $command->table(
            [__('console.local_accounts.email'), __('console.local_accounts.result'), __('console.local_accounts.note')],
            array_map(fn (array $row): array => [$row['email'], __('console.local_accounts.results.'.$row['result']), $row['note'] ?? ''], $report['accounts']),
        );
    }
}
