<?php

namespace App\Core\Accounts\Console;

use App\Core\Accounts\Local\AccountSnapshots;
use App\Core\Accounts\Local\DumpLoader;
use App\Core\Accounts\Local\LocalOnly;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Loads a dump of TEST into the LOCAL database and keeps the local accounts (Auftrag 45 C, docs/lokale-konten.md):
 * save the local accounts → load the dump → migrate → remove what grants access on TEST (tokens, sessions, passkeys of
 * the domain of TEST, logins of applications, invitations) → put the local accounts back by e-mail.
 *
 * An account in both keeps its row and its id, so everything of the dump that points at it (tasks, comments, …) stays
 * with it; only the credentials are the local ones. A person only on TEST stays as an account without credentials
 * (random password, no second factor): content keeps its author, nobody can sign in as them.
 */
#[Signature('app:local-load-dump {file? : Dump (.sql or .sql.gz; default: the newest of TEST in LOCAL_TEST_DUMPS_DIR)} {--force : Do not ask}')]
#[Description('Local only: replace the local database with a dump of TEST and keep the local accounts and their credentials')]
class LocalLoadDump extends Command
{
    /** Credentials of TEST that must not work here (the dev server is reachable in the local network). */
    private const FOREIGN_ACCESS = [
        'personal_access_tokens', 'passkeys', 'sessions', 'password_reset_tokens',
        'oauth_access_tokens', 'oauth_refresh_tokens', 'oauth_auth_codes', 'oauth_auth_code_claims', 'oauth_device_codes',
    ];

    public function handle(AccountSnapshots $snapshots, DumpLoader $loader): int
    {
        $refusal = LocalOnly::refusal();

        if ($refusal !== null) {
            $this->components->error($refusal);

            return self::FAILURE;
        }

        $file = $this->argument('file') ?? $this->newestDump();

        if (! is_string($file) || ! is_file($file)) {
            $this->components->error(__('console.local_accounts.no_dump', ['dir' => (string) config('local_accounts.test_dumps')]));

            return self::FAILURE;
        }

        $database = (string) config('database.connections.'.config('database.default').'.database');

        if (! $this->option('force') && ! $this->confirm(__('console.local_accounts.confirm_dump', ['database' => $database, 'file' => basename($file)]))) {
            $this->components->warn(__('console.user.aborted'));

            return self::FAILURE;
        }

        $saved = null;
        $replacing = false;

        try {
            $saved = $snapshots->save();
            $this->components->info(__('console.local_accounts.saved', ['file' => (string) $saved]));

            // From here the tables may be gone: if it fails, the person needs to know the way back (Auftrag 45, R2).
            $replacing = true;
            $loader->replaceWith($file);
            Artisan::call('migrate', ['--force' => true]);

            $removed = $this->removeForeignAccess();
            $local = $saved === null ? [] : array_map('mb_strtolower', array_column(json_decode((string) file_get_contents($saved), true, 512, JSON_THROW_ON_ERROR)['users'], 'email'));
            $locked = $this->lockOthers($local);

            Artisan::call('permissions:sync-defaults', ['--add-missing' => true]);
            $report = $saved === null ? ['accounts' => [], 'warnings' => []] : $snapshots->restore($saved);
        } catch (Throwable $error) {
            $this->components->error($error->getMessage());

            if ($replacing) {
                $this->wayBack($saved);
            }

            return self::FAILURE;
        }

        AccountsRestore::report($this, $report);
        $this->components->info(__('console.local_accounts.removed', ['list' => collect($removed)->map(fn (int $count, string $table): string => "{$table} {$count}")->implode(', ')]));
        $this->components->info(__('console.local_accounts.locked', ['count' => $locked]));
        $this->components->info(__('console.local_accounts.references'));

        return self::SUCCESS;
    }

    /**
     * The dump failed after the database may have been emptied: say what state it is in and how to get the accounts
     * back, with the path of the snapshot made a moment ago (it is not touched by the failure).
     */
    private function wayBack(?string $saved): void
    {
        $this->newLine();
        $this->components->warn(__('console.local_accounts.load_failed'));

        if ($saved === null) {
            $this->line(__('console.local_accounts.load_failed_no_snapshot'));

            return;
        }

        $this->line(__('console.local_accounts.load_failed_steps', ['file' => $saved]));
    }

    private function newestDump(): ?string
    {
        $files = glob(rtrim((string) config('local_accounts.test_dumps'), '/').'/*.sql.gz') ?: [];
        usort($files, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $files[0] ?? null;
    }

    /**
     * @return array<string, int> table => rows removed
     */
    private function removeForeignAccess(): array
    {
        $removed = [];

        foreach (self::FOREIGN_ACCESS as $table) {
            if (Schema::hasTable($table)) {
                $removed[$table] = DB::table($table)->delete();
            }
        }

        return $removed;
    }

    /**
     * People of the dump that have no local account: no password anyone knows, no second factor, no remembered login.
     *
     * @param  list<string>  $local  e-mails of the local accounts (lower case)
     */
    private function lockOthers(array $local): int
    {
        $others = DB::table('users')->get(['id', 'email'])->reject(fn (object $user): bool => in_array(mb_strtolower((string) $user->email), $local, true));

        foreach ($others as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'password' => Hash::make(Str::password(64)),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => null,
            ]);
        }

        return $others->count();
    }
}
