<?php

namespace Tests\Feature;

use App\Console\Commands\PermissionsSyncDefaults;
use App\Core\Rights\DefaultsAddMissing;
use App\Support\RightsDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * permissions:sync-defaults --add-missing brings only what is new into the rights tables and overwrites nothing (Auftrag 40 B).
 */
class RightsAddMissingTest extends TestCase
{
    use RefreshDatabase;

    private function functionId(string $key, string $scope = 'project'): int
    {
        return (int) DB::table('functions')->where(['scope' => $scope, 'key' => $key])->value('id');
    }

    private function roleId(string $key, string $scope = 'project'): int
    {
        return (int) DB::table('roles')->where(['scope' => $scope, 'key' => $key])->value('id');
    }

    private function hasRight(string $functionKey, string $right): bool
    {
        return DB::table('function_rights')->where(['function_id' => $this->functionId($functionKey), 'right' => $right])->exists();
    }

    /** @return array<string, mixed> everything of the four tables, to see that nothing changed */
    private function tables(): array
    {
        return [
            'functions' => DB::table('functions')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'function_rights' => DB::table('function_rights')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'roles' => DB::table('roles')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'role_functions' => DB::table('role_functions')->orderBy('role_id')->orderBy('function_id')->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }

    public function test_a_missing_standard_role_is_created_with_its_standard_functions_and_an_existing_one_stays_as_it_is(): void
    {
        DB::table('roles')->where(['scope' => 'project', 'key' => 'agent'])->delete();
        DB::table('functions')->where(['scope' => 'project', 'key' => 'contribute'])->update(['names' => json_encode(['de' => 'Eigener Name', 'en' => 'Own name'])]);

        $this->artisan('permissions:sync-defaults --add-missing')->expectsOutputToContain('project.role.agent')->assertSuccessful();

        $agent = $this->roleId('agent');
        $this->assertGreaterThan(0, $agent);
        $this->assertTrue(DB::table('role_functions')->where(['role_id' => $agent, 'function_id' => $this->functionId('agent-work')])->exists());
        $this->assertSame(['de' => 'Eigener Name', 'en' => 'Own name'], json_decode((string) DB::table('functions')->where('id', $this->functionId('contribute'))->value('names'), true), 'a name changed in the settings stays');
    }

    public function test_a_missing_standard_function_is_created_with_its_rights(): void
    {
        DB::table('functions')->where(['scope' => 'project', 'key' => 'agent-work'])->delete();

        $this->artisan('permissions:sync-defaults --add-missing')->expectsOutputToContain('project.function.agent-work')->assertSuccessful();

        foreach (config('permissions.functions.project.agent-work.rights') as $right) {
            $this->assertTrue($this->hasRight('agent-work', $right), $right);
        }
    }

    public function test_a_right_that_an_admin_removed_after_the_first_run_is_not_added_again(): void
    {
        $this->artisan('permissions:sync-defaults --add-missing')->assertSuccessful();
        $right = config('permissions.functions.project.contribute.rights')[0];
        DB::table('function_rights')->where(['function_id' => $this->functionId('contribute'), 'right' => $right])->delete();

        $this->artisan('permissions:sync-defaults --add-missing')->expectsOutputToContain('Nichts zu tun')->assertSuccessful();

        $this->assertFalse($this->hasRight('contribute', $right));
    }

    public function test_a_function_that_an_admin_removed_from_a_role_is_not_added_again(): void
    {
        $this->artisan('permissions:sync-defaults --add-missing')->assertSuccessful();
        DB::table('role_functions')->where(['role_id' => $this->roleId('member'), 'function_id' => $this->functionId('contribute')])->delete();

        $this->artisan('permissions:sync-defaults --add-missing')->assertSuccessful();

        $this->assertFalse(DB::table('role_functions')->where(['role_id' => $this->roleId('member'), 'function_id' => $this->functionId('contribute')])->exists());
    }

    public function test_a_new_right_in_the_file_is_added_once_and_not_again_after_an_admin_removed_it(): void
    {
        $this->artisan('permissions:sync-defaults --add-missing')->assertSuccessful();
        config(['permissions.functions.project.contribute.rights' => [...config('permissions.functions.project.contribute.rights'), 'new.thing']]);

        $this->artisan('permissions:sync-defaults --add-missing')->expectsOutputToContain('project.function.contribute + new.thing')->assertSuccessful();
        $this->assertTrue($this->hasRight('contribute', 'new.thing'));

        DB::table('function_rights')->where(['function_id' => $this->functionId('contribute'), 'right' => 'new.thing'])->delete();
        $this->artisan('permissions:sync-defaults --add-missing')->expectsOutputToContain('Nichts zu tun')->assertSuccessful();

        $this->assertFalse($this->hasRight('contribute', 'new.thing'));
    }

    public function test_a_new_function_in_a_standard_role_is_added_once(): void
    {
        $this->artisan('permissions:sync-defaults --add-missing')->assertSuccessful();
        config(['permissions.roles.project.guest.functions' => [...config('permissions.roles.project.guest.functions'), 'contribute']]);

        $this->artisan('permissions:sync-defaults --add-missing')->expectsOutputToContain('project.role.guest + function contribute')->assertSuccessful();
        $this->assertTrue(DB::table('role_functions')->where(['role_id' => $this->roleId('guest'), 'function_id' => $this->functionId('contribute')])->exists());

        DB::table('role_functions')->where(['role_id' => $this->roleId('guest'), 'function_id' => $this->functionId('contribute')])->delete();
        $this->artisan('permissions:sync-defaults --add-missing')->assertSuccessful();

        $this->assertFalse(DB::table('role_functions')->where(['role_id' => $this->roleId('guest'), 'function_id' => $this->functionId('contribute')])->exists());
    }

    private function pairsInTheFile(): int
    {
        return collect(config('permissions.functions'))->flatten(1)->sum(fn (array $function): int => count($function['rights']))
            + collect(config('permissions.roles'))->flatten(1)->sum(fn (array $role): int => count($role['functions']));
    }

    public function test_the_first_run_on_an_existing_database_changes_nothing_and_records_every_pair_of_the_file(): void
    {
        $this->assertSame(0, DB::table('rights_defaults_applied')->count(), 'an existing database starts with an empty list');
        $before = $this->tables();

        $this->artisan('permissions:sync-defaults --add-missing')->expectsOutputToContain('Nichts zu tun')->assertSuccessful();

        $this->assertSame($before, $this->tables());
        $this->assertSame($this->pairsInTheFile(), DB::table('rights_defaults_applied')->count());
    }

    public function test_the_first_run_adds_no_missing_pair_to_an_existing_function_or_role_but_says_which(): void
    {
        $right = config('permissions.functions.project.contribute.rights')[0];
        DB::table('function_rights')->where(['function_id' => $this->functionId('contribute'), 'right' => $right])->delete();
        DB::table('role_functions')->where(['role_id' => $this->roleId('member'), 'function_id' => $this->functionId('contribute')])->delete();
        $before = $this->tables();

        $this->artisan('permissions:sync-defaults --add-missing')
            ->expectsOutputToContain("project.function.contribute + $right")
            ->expectsOutputToContain('project.role.member + function contribute')
            ->expectsOutputToContain('Nichts zu tun')
            ->assertSuccessful();

        $this->assertSame($before, $this->tables(), 'removed on purpose before the table existed');
        $this->assertSame($this->pairsInTheFile(), DB::table('rights_defaults_applied')->count(), 'even the missing pairs count as offered');

        $this->artisan('permissions:sync-defaults --add-missing')->expectsOutputToContain('Nichts zu tun')->assertSuccessful();
        $this->assertSame($before, $this->tables(), 'and the second run does not add them either');
    }

    public function test_the_first_run_still_creates_whole_missing_roles_and_functions(): void
    {
        DB::table('roles')->where(['scope' => 'project', 'key' => 'agent'])->delete();
        DB::table('functions')->where(['scope' => 'project', 'key' => 'agent-work'])->delete();

        $this->artisan('permissions:sync-defaults --add-missing')
            ->expectsOutputToContain('project.function.agent-work')
            ->expectsOutputToContain('project.role.agent')
            ->assertSuccessful();

        $this->assertTrue($this->hasRight('agent-work', config('permissions.functions.project.agent-work.rights')[0]));
        $this->assertTrue(DB::table('role_functions')->where(['role_id' => $this->roleId('agent'), 'function_id' => $this->functionId('agent-work')])->exists());
    }

    public function test_a_pair_that_is_new_after_the_first_run_is_added(): void
    {
        $right = config('permissions.functions.project.contribute.rights')[0];
        DB::table('function_rights')->where(['function_id' => $this->functionId('contribute'), 'right' => $right])->delete();
        $this->artisan('permissions:sync-defaults --add-missing')->assertSuccessful();
        config(['permissions.functions.project.contribute.rights' => [...config('permissions.functions.project.contribute.rights'), 'new.thing']]);

        $this->artisan('permissions:sync-defaults --add-missing')->expectsOutputToContain('project.function.contribute + new.thing')->assertSuccessful();

        $this->assertTrue($this->hasRight('contribute', 'new.thing'));
        $this->assertFalse($this->hasRight('contribute', $right), 'the one from before the first run stays out');
    }

    public function test_check_before_the_first_run_shows_separately_what_would_be_added_and_what_would_not(): void
    {
        $right = config('permissions.functions.project.contribute.rights')[0];
        DB::table('function_rights')->where(['function_id' => $this->functionId('contribute'), 'right' => $right])->delete();

        // Only pairs that are not added: information, no warning (exit 0).
        $this->artisan('permissions:sync-defaults --check')
            ->expectsOutputToContain('NICHT ergänzt')
            ->expectsOutputToContain("project.function.contribute + $right")
            ->assertSuccessful();

        // A whole missing role would be added: exit 2, and the skipped pair is still named apart.
        DB::table('roles')->where(['scope' => 'project', 'key' => 'agent'])->delete();
        $before = $this->tables();
        $this->artisan('permissions:sync-defaults --check')
            ->expectsOutputToContain('project.role.agent')
            ->expectsOutputToContain('NICHT ergänzt')
            ->assertExitCode(PermissionsSyncDefaults::EXIT_MISSING);

        $this->assertSame(0, DB::table('rights_defaults_applied')->count(), 'check writes nothing');
        $this->assertSame($before, $this->tables());
    }

    public function test_own_roles_functions_and_assignments_stay_and_are_reported(): void
    {
        $function = DB::table('functions')->insertGetId(['scope' => 'project', 'key' => 'my-own', 'names' => json_encode(['de' => 'Meine', 'en' => 'Mine']), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('function_rights')->insert(['function_id' => $function, 'right' => 'task.create']);
        $role = DB::table('roles')->insertGetId(['scope' => 'project', 'key' => 'my-role', 'names' => json_encode(['de' => 'Meine Rolle', 'en' => 'My role']), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_functions')->insert(['role_id' => $role, 'function_id' => $function]);
        $before = $this->tables();

        $this->artisan('permissions:sync-defaults --add-missing')->expectsOutputToContain('project.function.my-own, project.role.my-role')->assertSuccessful();

        $this->assertSame($before, $this->tables());
        $this->assertSame(['project.function.my-own', 'project.role.my-role'], DefaultsAddMissing::notInTheFile());
    }

    public function test_the_second_run_has_nothing_to_do(): void
    {
        DB::table('roles')->where(['scope' => 'project', 'key' => 'agent'])->delete();

        $this->artisan('permissions:sync-defaults --add-missing')->expectsOutputToContain('1 Standardeinträge ergänzt')->assertSuccessful();
        $this->artisan('permissions:sync-defaults --add-missing')->expectsOutputToContain('Nichts zu tun')->assertSuccessful();
    }

    public function test_the_reset_records_every_standard_pair_as_offered(): void
    {
        DB::table('rights_defaults_applied')->delete();

        RightsDefaults::apply();

        $this->assertSame($this->pairsInTheFile(), DB::table('rights_defaults_applied')->count());
    }

    public function test_check_also_shows_what_add_missing_would_add_and_writes_nothing(): void
    {
        $this->artisan('permissions:sync-defaults --add-missing')->assertSuccessful();
        config(['permissions.functions.project.contribute.rights' => [...config('permissions.functions.project.contribute.rights'), 'new.thing']]);
        $before = $this->tables();

        $this->artisan('permissions:sync-defaults --check')
            ->expectsOutputToContain('project.function.contribute + new.thing')
            ->assertExitCode(PermissionsSyncDefaults::EXIT_MISSING);

        $this->assertSame($before, $this->tables());
    }

    public function test_every_added_entry_is_logged_without_a_person(): void
    {
        Log::spy();
        DB::table('roles')->where(['scope' => 'project', 'key' => 'agent'])->delete();

        $this->artisan('permissions:sync-defaults --add-missing')->assertSuccessful();

        Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => str_starts_with($message, 'Standard rights added: project.role.agent'))->once();
    }
}
