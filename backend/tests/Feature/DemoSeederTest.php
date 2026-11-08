<?php

namespace Tests\Feature;

use App\Enums\ItemStatus;
use App\Enums\ProjectRole;
use App\Enums\TaskStatus;
use App\Models\Comment;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Support\AppSettings;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Demo-Passwort-7';

    private const QUESTION = 'Password for all demo users (empty = generate one)';

    public function test_seeds_the_demo_projects_with_computed_statuses(): void
    {
        $this->seedDemo();

        $this->assertSame([4, 2, 5, 6, 8, 3], [User::count(), Project::count(), Milestone::count(), Todo::count(), Task::count(), Comment::count()]);
        $this->assertSame(['anna.keller@example.ch'], User::where('is_admin', true)->pluck('email')->all());

        $relaunch = Project::where('name', 'Website-Relaunch')->firstOrFail();
        $chiara = User::where('email', 'chiara.rossi@example.ch')->firstOrFail();
        $this->assertSame(ProjectRole::MANAGER, $relaunch->roleOf($chiara));
        $this->assertSame(ProjectRole::OWNER, Project::where('name', 'Sommerfest 2027')->firstOrFail()->roleOf($chiara));

        $this->assertSame(
            ['Mockup Desktop', 'Veraltete Seiten markieren', 'Hosting bestellen'],
            Task::query()->inProject($relaunch->id)->where('status', TaskStatus::OPEN)->orderBy('position')->pluck('title')->all(),
        );
        $this->assertSame([ItemStatus::OPEN], Todo::pluck('status')->unique()->values()->all());
        $this->assertSame('bulletList', Todo::where('title', 'Styleguide erstellen')->firstOrFail()->description['content'][1]['type']);
    }

    public function test_demo_data_starts_with_two_factor_off_so_the_password_is_enough(): void
    {
        $this->seedDemo();
        $ben = User::where('email', 'ben.mueller@example.ch')->firstOrFail();
        $this->withHeader('Origin', 'http://localhost:3000');

        $this->assertFalse(AppSettings::twoFactorEnabled());
        $this->postJson('/login', ['email' => $ben->email, 'password' => self::PASSWORD])
            ->assertOk()->assertJsonPath('two_factor', false);

        $this->postJson('/graphql', ['query' => '{ projects { name viewerRole } }'])
            ->assertOk()
            ->assertJsonPath('data.projects', [['name' => 'Website-Relaunch', 'viewerRole' => 'OWNER']]);
    }

    public function test_after_switching_two_factor_on_the_seeded_totp_code_works(): void
    {
        $this->seedDemo();
        $ben = User::where('email', 'ben.mueller@example.ch')->firstOrFail();
        $secret = Fortify::currentEncrypter()->decrypt($ben->two_factor_secret);
        AppSettings::setTwoFactorEnabled(true, $ben);
        $this->withHeader('Origin', 'http://localhost:3000');

        $this->postJson('/login', ['email' => $ben->email, 'password' => self::PASSWORD])
            ->assertOk()->assertJsonPath('two_factor', true);
        $this->postJson('/two-factor-challenge', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
            ->assertNoContent();

        $this->postJson('/graphql', ['query' => '{ me { email } }'])
            ->assertOk()
            ->assertJsonPath('data.me.email', 'ben.mueller@example.ch');
    }

    public function test_without_a_terminal_a_password_is_generated_and_shown(): void
    {
        $this->artisan('db:seed', ['--class' => DemoSeeder::class, '--no-interaction' => true])
            ->expectsOutputToContain('Password for all demo users: ')
            ->expectsOutputToContain('Two-factor authentication is OFF')
            ->expectsOutputToContain('2FA secret:')
            ->assertSuccessful();

        $this->assertSame(4, User::count());
    }

    public function test_refuses_a_database_that_is_not_empty(): void
    {
        User::factory()->create();

        $this->expectExceptionMessage('DemoSeeder needs an empty database.');

        $this->artisan('db:seed', ['--class' => DemoSeeder::class, '--no-interaction' => true])->run();
    }

    private function seedDemo(): void
    {
        $this->artisan('db:seed', ['--class' => DemoSeeder::class])
            ->expectsQuestion(self::QUESTION, self::PASSWORD)
            ->expectsOutputToContain('Demo data created.')
            ->assertSuccessful();
    }
}
