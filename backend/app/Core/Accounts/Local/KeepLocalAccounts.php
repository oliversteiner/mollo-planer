<?php

namespace App\Core\Accounts\Local;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Keeps the local accounts over a rebuild of the database (Auftrag 45 B): before migrate:fresh, migrate:refresh,
 * migrate:reset and db:wipe the accounts are saved (if that fails, the command does not run), afterwards they come
 * back. After migrate:reset and db:wipe there is no users table yet: then the next migrate puts them back (a note in
 * the folder of the snapshots says which one). Only local (LocalOnly), never in "testing", off with
 * LOCAL_ACCOUNTS_KEEP=false.
 */
final class KeepLocalAccounts
{
    public const DESTRUCTIVE = ['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe'];

    private const PENDING = '.restore-pending';

    /** The snapshot of the command that is running now. */
    private ?string $saved = null;

    public function __construct(private readonly AccountSnapshots $snapshots) {}

    public static function enabled(): bool
    {
        return (bool) config('local_accounts.keep') && ! app()->environment('testing') && LocalOnly::refusal() === null;
    }

    /**
     * @throws RuntimeException when the accounts cannot be saved: the destructive command must not run
     */
    public function starting(CommandStarting $event): void
    {
        if (! in_array($event->command, self::DESTRUCTIVE, true) || ! self::enabled()) {
            return;
        }

        try {
            $this->saved = $this->snapshots->save();
        } catch (Throwable $error) {
            throw new RuntimeException(__('console.local_accounts.save_failed_abort', ['command' => $event->command, 'reason' => $error->getMessage()]), 0, $error);
        }

        if ($this->saved !== null) {
            $event->output->writeln('<info>'.__('console.local_accounts.saved', ['file' => $this->saved]).'</info>');
        }
    }

    public function finished(CommandFinished $event): void
    {
        if (! self::enabled() || $event->exitCode !== 0) {
            return;
        }

        if (in_array($event->command, self::DESTRUCTIVE, true) && $this->saved !== null) {
            $saved = $this->saved;
            $this->saved = null;

            if (Schema::hasTable('users')) {
                $this->restore($saved, $event->output);
            } else {
                file_put_contents($this->snapshots->directory().'/'.self::PENDING, $saved);
                $event->output->writeln('<comment>'.__('console.local_accounts.pending', ['file' => $saved]).'</comment>');
            }

            return;
        }

        $pending = $this->snapshots->directory().'/'.self::PENDING;

        if ($event->command === 'migrate' && is_file($pending) && Schema::hasTable('users')) {
            $saved = trim((string) file_get_contents($pending));
            @unlink($pending);
            $this->restore($saved, $event->output);
        }
    }

    private function restore(string $file, OutputInterface $output): void
    {
        // The system roles first: the accounts take theirs by key.
        Artisan::call('permissions:sync-defaults', ['--add-missing' => true]);

        $report = $this->snapshots->restore($file);
        $counts = array_count_values(array_column($report['accounts'], 'result'));

        foreach ($report['warnings'] as $warning) {
            $output->writeln('<comment>'.$warning.'</comment>');
        }

        foreach ($report['accounts'] as $account) {
            if (isset($account['note'])) {
                $output->writeln('<comment>'.$account['email'].': '.$account['note'].'</comment>');
            }
        }

        $output->writeln('<info>'.__('console.local_accounts.restored', ['restored' => $counts['restored'] ?? 0, 'created' => $counts['created'] ?? 0, 'file' => $file]).'</info>');
    }
}
