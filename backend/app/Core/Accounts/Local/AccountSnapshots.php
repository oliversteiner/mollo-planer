<?php

namespace App\Core\Accounts\Local;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Saves the accounts of the LOCAL database to a file outside the repository and puts them back after the database was
 * rebuilt (Auftrag 45 A, docs/lokale-konten.md). Matched by e-mail (ids change when the database is rebuilt), the system
 * role by its key. What is saved: the tables of AccountTables::ACCOUNT. Memberships in projects are content and stay
 * out. Written with the query builder, not the models: no observer and no mail runs while accounts come back.
 *
 * The files hold password hashes, 2FA secrets and token hashes: folder 0700, files 0600, never in the repository.
 */
final class AccountSnapshots
{
    public const FORMAT = 'mollo-planer-accounts-1';

    private const PREFIX = 'konten-';

    /**
     * Saves the accounts. Null when there is nothing to save (no users table yet, for example before the first migrate).
     *
     * @throws RuntimeException when the file cannot be written; a destructive command must then not run
     */
    public function save(): ?string
    {
        LocalOnly::assert();

        if (! Schema::hasTable('users')) {
            return null;
        }

        $roles = Schema::hasTable('roles') ? DB::table('roles')->pluck('key', 'id')->all() : [];
        $emails = DB::table('users')->pluck('email', 'id')->all();
        $users = [];

        foreach (DB::table('users')->orderBy('id')->get() as $row) {
            $columns = (array) $row;
            $id = (int) $columns['id'];
            unset($columns['id'], $columns['role_id']);

            $users[] = [
                'email' => (string) $row->email,
                'role' => $roles[$row->role_id] ?? null,
                'columns' => $columns,
                'passkeys' => $this->rowsOf('passkeys', 'user_id', $id, ['id', 'user_id']),
                'tokens' => $this->tokensOf($id),
            ];
        }

        $snapshot = [
            'format' => self::FORMAT,
            'created_at' => now()->toIso8601String(),
            // Not the key: only whether the 2FA secrets (encrypted with it) can be read again.
            'app_key' => hash('sha256', (string) config('app.key')),
            'users' => $users,
            'password_reset_tokens' => $this->table('password_reset_tokens'),
            'oauth_clients' => $this->clients($emails),
        ];

        return $this->write($snapshot);
    }

    /**
     * Puts the accounts of a snapshot back. An account with the same e-mail is overwritten, a missing one created.
     *
     * @return array{accounts: list<array{email: string, result: string, note?: string}>, warnings: list<string>}
     */
    public function restore(string $path): array
    {
        LocalOnly::assert();

        $snapshot = $this->read($path);
        $warnings = [];

        if (($snapshot['app_key'] ?? null) !== hash('sha256', (string) config('app.key'))) {
            $warnings[] = __('console.local_accounts.other_app_key');
        }

        $report = DB::transaction(function () use ($snapshot): array {
            $userColumns = array_flip(Schema::getColumnListing('users'));
            $systemRoles = DB::table('roles')->where('scope', Role::SCOPE_SYSTEM)->pluck('id', 'key')->all();
            $defaultRole = DB::table('roles')->where('scope', Role::SCOPE_SYSTEM)->where('is_default', true)->value('id');
            $accounts = [];

            foreach ($snapshot['users'] as $entry) {
                $email = (string) $entry['email'];
                $columns = array_intersect_key((array) $entry['columns'], $userColumns);
                unset($columns['id']);
                $note = null;

                if ($entry['role'] !== null && isset($systemRoles[$entry['role']])) {
                    $columns['role_id'] = $systemRoles[$entry['role']];
                } else {
                    $columns['role_id'] = $defaultRole;
                    $note = __('console.local_accounts.role_missing', ['role' => (string) ($entry['role'] ?? '–')]);
                }

                $existing = DB::table('users')->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->value('id');

                if ($existing !== null) {
                    DB::table('users')->where('id', $existing)->update($columns);
                    $id = (int) $existing;
                    $result = 'restored';
                } else {
                    $id = (int) DB::table('users')->insertGetId($columns);
                    $result = 'created';
                }

                $this->replacePasskeys($id, $entry['passkeys'] ?? []);
                $this->replaceTokens($id, $entry['tokens'] ?? []);

                $accounts[] = ['email' => $email, 'result' => $result] + ($note === null ? [] : ['note' => $note]);
            }

            $this->restoreInvitations($snapshot['password_reset_tokens'] ?? []);
            $this->restoreClients($snapshot['oauth_clients'] ?? []);

            return $accounts;
        });

        Role::forgetRegistry();

        return ['accounts' => $report, 'warnings' => $warnings];
    }

    /** The newest snapshot, or null. */
    public function latest(): ?string
    {
        $files = glob($this->directory().'/'.self::PREFIX.'*.json') ?: [];
        rsort($files);

        return $files[0] ?? null;
    }

    public function directory(): string
    {
        return rtrim((string) config('local_accounts.dir'), '/');
    }

    // ─── Saving ──────────────────────────────────────────────────────

    /**
     * @param  list<string>  $without
     * @return list<array<string, mixed>>
     */
    private function rowsOf(string $table, string $column, int $id, array $without): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)->where($column, $id)->orderBy('id')->get()
            ->map(fn (object $row): array => array_diff_key((array) $row, array_flip($without)))
            ->values()->all();
    }

    /**
     * The tokens with their id: a Sanctum token reads "<id>|<secret>" and is looked up by that id first, so a token
     * that came back under another id would not work any more.
     *
     * @return list<array<string, mixed>>
     */
    private function tokensOf(int $id): array
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return [];
        }

        return DB::table('personal_access_tokens')
            ->where('tokenable_type', (new User)->getMorphClass())
            ->where('tokenable_id', $id)
            ->orderBy('id')->get()
            ->map(fn (object $row): array => array_diff_key((array) $row, array_flip(['tokenable_id'])))
            ->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function table(string $table): array
    {
        return Schema::hasTable($table) ? DB::table($table)->get()->map(fn (object $row): array => (array) $row)->values()->all() : [];
    }

    /**
     * The clients with the e-mail of their owner and of who registered them, not the ids.
     *
     * @param  array<int, string>  $emails
     * @return list<array<string, mixed>>
     */
    private function clients(array $emails): array
    {
        return array_map(function (array $client) use ($emails): array {
            $client['owner_email'] = $emails[$client['owner_id'] ?? 0] ?? null;
            $client['registered_by_email'] = $emails[$client['registered_by'] ?? 0] ?? null;
            unset($client['owner_id'], $client['registered_by']);

            return $client;
        }, $this->table('oauth_clients'));
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function write(array $snapshot): string
    {
        $directory = $this->directory();

        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException(__('console.local_accounts.dir_failed', ['dir' => $directory]));
        }

        @chmod($directory, 0700);
        $path = sprintf('%s/%s%s-%s.json', $directory, self::PREFIX, now()->format('Ymd-His'), substr(bin2hex(random_bytes(3)), 0, 6));
        $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Created with 0600 from the start: the content is never readable for others, not even for a moment.
        $previous = umask(0077);

        try {
            if (@file_put_contents($path, $json, LOCK_EX) === false) {
                throw new RuntimeException(__('console.local_accounts.write_failed', ['file' => $path]));
            }
        } finally {
            umask($previous);
        }

        @chmod($path, 0600);
        $this->prune();

        return $path;
    }

    private function prune(): void
    {
        $files = glob($this->directory().'/'.self::PREFIX.'*.json') ?: [];
        rsort($files);

        foreach (array_slice($files, (int) config('local_accounts.retain', 20)) as $old) {
            @unlink($old);
        }
    }

    // ─── Restoring ───────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException(__('console.local_accounts.no_file', ['file' => $path]));
        }

        $snapshot = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($snapshot) || ($snapshot['format'] ?? null) !== self::FORMAT || ! is_array($snapshot['users'] ?? null)) {
            throw new RuntimeException(__('console.local_accounts.bad_file', ['file' => $path]));
        }

        return $snapshot;
    }

    /**
     * @param  list<array<string, mixed>>  $passkeys
     */
    private function replacePasskeys(int $id, array $passkeys): void
    {
        DB::table('passkeys')->where('user_id', $id)->delete();

        foreach ($passkeys as $passkey) {
            // A credential is unique: one that another account holds now (from a dump) gives way to the saved one.
            DB::table('passkeys')->where('credential_id', $passkey['credential_id'])->delete();
            DB::table('passkeys')->insert(['user_id' => $id] + $passkey);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $tokens
     */
    private function replaceTokens(int $id, array $tokens): void
    {
        $type = (new User)->getMorphClass();
        DB::table('personal_access_tokens')->where('tokenable_type', $type)->where('tokenable_id', $id)->delete();

        foreach ($tokens as $token) {
            // Same id and same hash as before (the plain token names its id); whatever holds them now gives way.
            DB::table('personal_access_tokens')->where('id', $token['id'])->orWhere('token', $token['token'])->delete();
            DB::table('personal_access_tokens')->insert(['tokenable_id' => $id] + $token);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function restoreInvitations(array $rows): void
    {
        foreach ($rows as $row) {
            DB::table('password_reset_tokens')->updateOrInsert(['email' => $row['email']], $row);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $clients
     */
    private function restoreClients(array $clients): void
    {
        if (! Schema::hasTable('oauth_clients')) {
            return;
        }

        $columns = array_flip(Schema::getColumnListing('oauth_clients'));

        foreach ($clients as $client) {
            $client['owner_id'] = $this->idOf($client['owner_email'] ?? null);
            $client['registered_by'] = $this->idOf($client['registered_by_email'] ?? null);

            if ($client['owner_id'] === null) {
                $client['owner_type'] = null;
            }

            $values = array_intersect_key($client, $columns);
            DB::table('oauth_clients')->updateOrInsert(['id' => $client['id']], $values);
        }
    }

    private function idOf(?string $email): ?int
    {
        if ($email === null) {
            return null;
        }

        $id = DB::table('users')->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->value('id');

        return $id === null ? null : (int) $id;
    }
}
