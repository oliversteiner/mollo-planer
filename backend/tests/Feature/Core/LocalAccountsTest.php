<?php

namespace Tests\Feature\Core;

use App\Core\Accounts\Local\AccountSnapshots;
use App\Core\Accounts\Local\KeepLocalAccounts;
use App\Models\Role;
use App\Models\User;
use App\Support\McpTokens;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Local accounts survive a rebuild of the database (Auftrag 45 A, B, D): saved to a file outside the repository,
 * matched by e-mail, the system role by its key; automatic around migrate:fresh and friends; only local.
 */
class LocalAccountsTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'lokales-passwort-123';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/planer-accounts-'.bin2hex(random_bytes(4));
        config(['local_accounts.dir' => $this->dir, 'local_accounts.keep' => true]);
        $this->app['env'] = 'local';
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        $this->app['env'] = 'testing';

        parent::tearDown();
    }

    private function snapshots(): AccountSnapshots
    {
        return app(AccountSnapshots::class);
    }

    /**
     * An admin with password, confirmed 2FA app, a passkey and a connector token.
     *
     * @return array{User, string, string} the account, its 2FA secret and its plain token
     */
    private function account(string $email = 'oliver@example.ch'): array
    {
        $secret = app(Google2FA::class)->generateSecretKey();
        $user = User::factory()->admin()->create(['email' => $email, 'password' => self::PASSWORD, 'locale' => 'de']);
        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['eins', 'zwei'])),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $user->passkeys()->forceCreate(['user_id' => $user->id, 'name' => 'Laptop', 'credential_id' => 'cred-oliver', 'credential' => []]);
        $token = McpTokens::create($user, 'Claude Code')->plainTextToken;

        return [$user, $secret, $token];
    }

    /** What migrate:fresh does to the accounts, without leaving the transaction of the test. */
    private function wipeAccounts(): void
    {
        DB::table('personal_access_tokens')->delete();
        DB::table('passkeys')->delete();
        DB::table('users')->delete();
    }

    public function test_an_account_with_everything_comes_back_and_works_after_a_rebuild(): void
    {
        [$user, $secret, $token] = $this->account();
        $path = $this->snapshots()->save();
        $this->wipeAccounts();
        // A seeder made another account first: the ids are not the ones of the snapshot any more.
        $other = User::factory()->create(['email' => 'seed@example.ch']);

        $report = $this->snapshots()->restore($path);

        $this->assertSame([['email' => 'oliver@example.ch', 'result' => 'created']], array_values(array_filter($report['accounts'], fn ($row) => $row['email'] === 'oliver@example.ch')));
        $restored = User::query()->where('email', 'oliver@example.ch')->sole();
        $this->assertNotSame($user->id, $restored->id, 'a new id');
        $this->assertSame('admin', $restored->role->key);
        $this->assertSame(1, $restored->passkeys()->count());
        $this->assertNotNull($restored->two_factor_confirmed_at);

        // The token of the connector works (asked first: afterwards the session of the login would answer instead).
        $this->app['env'] = 'testing';
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], ['Accept' => 'application/json, text/event-stream', 'Authorization' => "Bearer {$token}"])->assertOk();
        $this->app['auth']->forgetGuards();

        // Sign in with password and the code of the app.
        $this->postJson('/login', ['email' => 'oliver@example.ch', 'password' => self::PASSWORD])->assertOk()->assertJsonPath('two_factor', true);
        $this->postJson('/two-factor-challenge', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertNoContent();
        $this->assertNotNull($other->fresh());
    }

    public function test_an_existing_account_with_the_same_email_is_overwritten_not_doubled(): void
    {
        // Upper and lower case do not matter for the match.
        [, , $token] = $this->account('Oliver@Example.ch');
        $path = $this->snapshots()->save();
        $this->wipeAccounts();
        $seeded = User::factory()->create(['email' => 'oliver@example.ch', 'password' => 'aus-dem-seeder-123']);

        $report = $this->snapshots()->restore($path);

        $this->assertSame('restored', $report['accounts'][0]['result']);
        $this->assertSame(1, User::query()->whereRaw('LOWER(email) = ?', ['oliver@example.ch'])->count());
        $this->assertTrue(password_verify(self::PASSWORD, (string) $seeded->fresh()->password));
        $this->assertSame($seeded->id, DB::table('personal_access_tokens')->where('token', hash('sha256', explode('|', $token)[1]))->value('tokenable_id'));
    }

    public function test_the_system_role_is_taken_by_its_key_and_a_missing_one_falls_back_with_a_note(): void
    {
        $this->account();
        $path = $this->snapshots()->save();
        $snapshot = json_decode((string) file_get_contents($path), true);
        $snapshot['users'][0]['role'] = 'gibts-nicht';
        file_put_contents($path, json_encode($snapshot));
        $this->wipeAccounts();

        $report = $this->snapshots()->restore($path);

        $this->assertSame(Role::defaultOf(Role::SCOPE_SYSTEM)->id, User::query()->sole()->role_id);
        $this->assertStringContainsString('gibts-nicht', $report['accounts'][0]['note']);
    }

    public function test_the_file_is_outside_the_repository_only_for_its_owner_and_the_newest_twenty_stay(): void
    {
        $this->account();
        File::ensureDirectoryExists($this->dir);

        foreach (range(1, 22) as $i) {
            touch(sprintf('%s/konten-2020010%d-0000%02d-aaaaaa.json', $this->dir, $i % 9 + 1, $i));
        }

        $path = $this->snapshots()->save();

        $this->assertStringStartsWith($this->dir, $path);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($path)), -4));
        $this->assertSame('0700', substr(sprintf('%o', fileperms($this->dir)), -4));
        $this->assertCount(20, glob($this->dir.'/konten-*.json'));
        $this->assertContains($path, glob($this->dir.'/konten-*.json'), 'the new one is kept');
    }

    public function test_the_report_of_the_command_names_no_password_secret_or_token(): void
    {
        [$user, , $token] = $this->account();
        $this->snapshots()->save();
        $hash = (string) $user->fresh()->password;

        $this->artisan('app:accounts-restore')
            ->expectsOutputToContain('oliver@example.ch')
            ->doesntExpectOutputToContain($hash)
            ->doesntExpectOutputToContain(explode('|', $token)[1])
            ->assertSuccessful();
    }

    public function test_everything_is_refused_outside_local_and_with_a_database_elsewhere(): void
    {
        $this->account();

        foreach (['staging', 'production', 'testing'] as $environment) {
            $this->app['env'] = $environment;
            $this->artisan('app:accounts-save')->assertFailed();
            $this->artisan('app:local-load-dump', ['file' => __FILE__, '--force' => true])->assertFailed();
        }

        $this->app['env'] = 'local';
        $default = config('database.default');
        config(['database.connections.remote' => ['driver' => 'mariadb', 'host' => 'oliver14.mysql.db.internal', 'database' => 'x'], 'database.default' => 'remote']);

        try {
            $this->snapshots()->save();
            $this->fail('a database on another host must be refused');
        } catch (RuntimeException $refused) {
            $this->assertStringContainsString('oliver14.mysql.db.internal', $refused->getMessage());
        } finally {
            config(['database.default' => $default]);
        }

        $this->assertDirectoryDoesNotExist($this->dir);
    }

    // ─── Automatic (B) ───────────────────────────────────────────────

    private function event(string $class, string $command, int $exitCode = 0): CommandStarting|CommandFinished
    {
        return $class === CommandStarting::class
            ? new CommandStarting($command, new ArrayInput([]), new BufferedOutput)
            : new CommandFinished($command, new ArrayInput([]), new BufferedOutput, $exitCode);
    }

    public function test_migrate_fresh_saves_before_and_restores_after(): void
    {
        $this->account();
        $keep = app(KeepLocalAccounts::class);

        $keep->starting($this->event(CommandStarting::class, 'migrate:fresh'));
        $this->assertCount(1, glob($this->dir.'/konten-*.json'));
        $this->wipeAccounts();
        $keep->finished($this->event(CommandFinished::class, 'migrate:fresh'));

        $restored = User::query()->sole();
        $this->assertSame('oliver@example.ch', mb_strtolower($restored->email));
        $this->assertSame(1, $restored->passkeys()->count());
        $this->assertSame(1, $restored->tokens()->count());
    }

    public function test_a_failed_save_stops_the_destructive_command(): void
    {
        $this->account();
        config(['local_accounts.dir' => '/dev/null/kein-ordner']);

        $this->expectException(RuntimeException::class);

        app(KeepLocalAccounts::class)->starting($this->event(CommandStarting::class, 'db:wipe'));
    }

    public function test_after_a_wipe_without_tables_the_next_migrate_restores(): void
    {
        $this->account();
        $keep = app(KeepLocalAccounts::class);

        $keep->starting($this->event(CommandStarting::class, 'db:wipe'));
        $this->wipeAccounts();
        // After the wipe there is no table users, after the next migrate there is one. Said by the schema instead of
        // renaming the table: on MariaDB a rename is DDL, commits the transaction of the test, and the next test had to
        // build the whole schema again (Auftrag 57).
        $schema = Schema::getFacadeRoot();
        Schema::swap(new class($schema)
        {
            /** @var list<bool> what hasTable('users') answers, one after the other */
            private array $answers = [false, true];

            public function __construct(private readonly object $schema) {}

            public function hasTable(string $table): bool
            {
                return $table === 'users' && $this->answers !== [] ? array_shift($this->answers) : $this->schema->hasTable($table);
            }

            /** @param  array<int, mixed>  $arguments */
            public function __call(string $method, array $arguments): mixed
            {
                return $this->schema->{$method}(...$arguments);
            }
        });
        $keep->finished($this->event(CommandFinished::class, 'db:wipe'));

        $this->assertFileExists($this->dir.'/.restore-pending');
        $this->assertSame(0, User::query()->count());

        $keep->finished($this->event(CommandFinished::class, 'migrate'));

        $this->assertSame(1, User::query()->count());
        $this->assertFileDoesNotExist($this->dir.'/.restore-pending');
        Schema::swap($schema);
    }

    public function test_nothing_happens_in_testing_when_switched_off_or_for_other_commands(): void
    {
        $this->account();

        $this->app['env'] = 'testing';
        app(KeepLocalAccounts::class)->starting($this->event(CommandStarting::class, 'migrate:fresh'));

        $this->app['env'] = 'local';
        config(['local_accounts.keep' => false]);
        app(KeepLocalAccounts::class)->starting($this->event(CommandStarting::class, 'migrate:fresh'));

        config(['local_accounts.keep' => true]);
        app(KeepLocalAccounts::class)->starting($this->event(CommandStarting::class, 'migrate'));

        $this->assertDirectoryDoesNotExist($this->dir);
    }
}
