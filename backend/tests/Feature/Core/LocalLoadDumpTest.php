<?php

namespace Tests\Feature\Core;

use App\Core\Accounts\Local\DumpLoader;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Support\McpTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A dump of TEST in the local database (Auftrag 45 C): the local accounts keep their credentials, everything that grants
 * access on TEST is gone, people only on TEST cannot sign in, content keeps pointing at the right accounts. The dump is a
 * small fixture made by a fake loader: no MariaDB client and no real data of TEST in the tests.
 */
class LocalLoadDumpTest extends TestCase
{
    use RefreshDatabase;

    private const LOCAL_PASSWORD = 'lokales-passwort-123';

    public const TEST_PASSWORD = 'passwort-auf-test-456';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/planer-accounts-'.bin2hex(random_bytes(4));
        config(['local_accounts.dir' => $this->dir]);
        $this->app['env'] = 'local';
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        $this->app['env'] = 'testing';

        parent::tearDown();
    }

    public function test_the_dump_of_test_comes_in_and_the_local_accounts_keep_their_credentials(): void
    {
        $local = User::factory()->admin()->create(['email' => 'oliver@example.ch', 'password' => self::LOCAL_PASSWORD]);
        $local->passkeys()->forceCreate(['user_id' => $local->id, 'name' => 'Laptop', 'credential_id' => 'cred-local', 'credential' => []]);
        $localToken = McpTokens::create($local, 'Lokal')->plainTextToken;
        $test = [];

        // The fake dump: the database of TEST, with other ids, its own credentials and content.
        $this->app->instance(DumpLoader::class, new class($test) implements DumpLoader
        {
            /** @param array<string, mixed> $test */
            public function __construct(public array &$test) {}

            public function replaceWith(string $file): void
            {
                DB::table('personal_access_tokens')->delete();
                DB::table('passkeys')->delete();
                DB::table('users')->delete();

                $oliver = User::factory()->admin()->create(['id' => 500, 'email' => 'oliver@example.ch', 'password' => LocalLoadDumpTest::TEST_PASSWORD]);
                $only = User::factory()->create(['id' => 501, 'email' => 'lindita@example.ch', 'password' => LocalLoadDumpTest::TEST_PASSWORD]);
                $only->forceFill(['two_factor_secret' => 'auf-test', 'two_factor_confirmed_at' => now(), 'remember_token' => 'erinnert'])->save();
                $oliver->passkeys()->forceCreate(['user_id' => 500, 'name' => 'TEST', 'credential_id' => 'cred-test', 'credential' => []]);
                $this->test['token'] = McpTokens::create($oliver, 'Auf TEST')->plainTextToken;
                DB::table('sessions')->insert(['id' => 'sitzung-test', 'user_id' => 500, 'payload' => 'x', 'last_activity' => time()]);
                DB::table('password_reset_tokens')->insert(['email' => 'neu@example.ch', 'token' => 'einladung-test', 'created_at' => now()]);

                $project = Project::factory()->create(['name' => 'Von TEST']);
                $todo = Todo::factory()->for(Milestone::factory()->for($project))->create();
                $this->test['task'] = Task::factory()->for($todo)->create(['assigned_user_id' => 500])->id;
                // Written as the dump has it (the observer would set the person signed in).
                DB::table('tasks')->where('id', $this->test['task'])->update(['created_by' => 501]);
            }
        });

        $this->artisan('app:local-load-dump', ['file' => __FILE__, '--force' => true])
            ->expectsOutputToContain('oliver@example.ch')
            ->assertSuccessful();

        // The shared account keeps the id of the dump (content points at it) and gets the local credentials.
        $oliver = User::query()->where('email', 'oliver@example.ch')->sole();
        $this->assertSame(500, $oliver->id);
        $this->assertTrue(Hash::check(self::LOCAL_PASSWORD, $oliver->password));
        $this->assertSame(['cred-local'], $oliver->passkeys()->pluck('credential_id')->all());
        $this->assertSame(500, Task::query()->find($test['task'])->assigned_user_id);

        // Nothing of TEST grants access here.
        $this->assertSame(1, DB::table('personal_access_tokens')->count(), 'only the local token');
        $this->assertSame($oliver->id, DB::table('personal_access_tokens')->where('id', (int) explode('|', $localToken)[0])->value('tokenable_id'));
        $this->assertFalse(DB::table('personal_access_tokens')->where('id', (int) explode('|', $test['token'])[0])->where('name', 'Auf TEST')->exists());
        $this->assertSame(0, DB::table('sessions')->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
        $this->assertSame(0, DB::table('passkeys')->where('credential_id', 'cred-test')->count());

        // A person only on TEST stays (author of content) but cannot sign in.
        $only = User::query()->where('email', 'lindita@example.ch')->sole();
        $this->assertFalse(Hash::check(self::TEST_PASSWORD, $only->password));
        $this->assertNull($only->two_factor_secret);
        $this->assertNull($only->remember_token);
        $this->assertSame(501, Task::query()->find($test['task'])->created_by);
    }

    public function test_without_a_dump_nothing_happens(): void
    {
        config(['local_accounts.test_dumps' => $this->dir.'/leer']);

        $this->artisan('app:local-load-dump', ['--force' => true])->assertFailed();
    }
}
