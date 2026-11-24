<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use App\Support\DeploymentEnvironment;
use Illuminate\Database\Console\Seeds\SeedCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The commands that empty or replace the database refuse to run on the two server environments (Auftrag 37 D): TEST holds
 * the real planning data. The rule is set when the app boots (AppServiceProvider), so the test boots the provider again
 * under the environment it wants to check.
 */
class DestructiveCommandsProhibitedTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        DB::prohibitDestructiveCommands(false);
        SeedCommand::prohibit(false);

        parent::tearDown();
    }

    /** @return array<string, array{0: string, 1: array<string, mixed>}> */
    public static function destructiveCommands(): array
    {
        return [
            'migrate:fresh' => ['migrate:fresh', []],
            'migrate:fresh --force' => ['migrate:fresh', ['--force' => true]],
            'migrate:fresh --seed' => ['migrate:fresh', ['--seed' => true, '--force' => true]],
            'migrate:refresh' => ['migrate:refresh', ['--force' => true]],
            'migrate:reset' => ['migrate:reset', ['--force' => true]],
            'migrate:rollback' => ['migrate:rollback', ['--force' => true]],
            'db:wipe' => ['db:wipe', ['--force' => true]],
            'db:seed' => ['db:seed', ['--force' => true]],
        ];
    }

    /** @param  array<string, mixed>  $options */
    #[DataProvider('destructiveCommands')]
    public function test_a_destructive_command_is_refused_on_a_server_environment(string $command, array $options): void
    {
        foreach (['staging', 'production'] as $environment) {
            $this->bootAs($environment);
            DB::table('users')->insert([
                'name' => 'Keep',
                'email' => $environment.'@example.com',
                'password' => 'x',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->artisan($command, $options)
                ->expectsOutputToContain('prohibited')
                ->assertFailed();

            $this->assertTrue(Schema::hasTable('users'), "$command left the tables in place on $environment");
            $this->assertSame(1, DB::table('users')->where('email', $environment.'@example.com')->count(), "$command left the data in place on $environment");
        }
    }

    public function test_on_a_development_machine_the_commands_are_allowed(): void
    {
        $this->bootAs('local');

        $this->assertFalse(DeploymentEnvironment::isLive());
        // A seeder that does not exist: the command gets as far as looking for it, so it is not refused.
        $this->expectExceptionMessage('NoSuchSeeder');

        $this->withoutMockingConsoleOutput();
        Artisan::call('db:seed', ['--class' => 'NoSuchSeeder', '--force' => true]);
    }

    public function test_migrate_itself_still_runs_on_a_server_environment(): void
    {
        $this->bootAs('staging');

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    private function bootAs(string $environment): void
    {
        $this->app['env'] = $environment;
        (new AppServiceProvider($this->app))->boot();
    }
}
