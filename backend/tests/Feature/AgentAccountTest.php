<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Enums\Right;
use App\Http\Middleware\EnsureNotAgent;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\McpTokens;
use App\Support\Rights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * An agent account (task 31): no way into the browser, only the connector (token), and the rights of the project role Agent.
 */
class AgentAccountTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'a-Long-Passw0rd-for-tests!';

    private function agent(): User
    {
        return User::factory()->agent()->create(['email' => 'claude@example.com', 'password' => self::PASSWORD]);
    }

    public function test_the_password_login_of_an_agent_fails_like_a_wrong_password(): void
    {
        $agent = $this->agent();
        $person = User::factory()->create(['email' => 'anna@example.com', 'password' => self::PASSWORD]);

        $wrong = $this->postJson('/login', ['email' => 'anna@example.com', 'password' => 'wrong-password']);
        $refused = $this->postJson('/login', ['email' => $agent->email, 'password' => self::PASSWORD]);

        $refused->assertUnprocessable();
        $this->assertSame($wrong->json('errors.email'), $refused->json('errors.email'), 'the answer tells nothing about the account');
        $this->assertGuest('web');

        $this->postJson('/login', ['email' => $person->email, 'password' => self::PASSWORD])->assertSuccessful();
    }

    public function test_no_session_login_of_an_agent_by_any_way(): void
    {
        $agent = $this->agent();

        try {
            Auth::guard('web')->login($agent);
            $this->fail('A session of an agent was started.');
        } catch (ValidationException) {
            $this->assertGuest('web');
        }
    }

    public function test_forgot_password_answers_the_same_and_sends_nothing_to_an_agent(): void
    {
        Notification::fake();
        $agent = $this->agent();
        $person = User::factory()->create(['email' => 'anna@example.com']);

        $forAgent = $this->postJson('/forgot-password', ['email' => $agent->email]);
        $forPerson = $this->postJson('/forgot-password', ['email' => $person->email]);

        $forAgent->assertOk();
        $this->assertSame($forPerson->getContent(), $forAgent->getContent());
        Notification::assertNotSentTo($agent, ResetPasswordNotification::class);
        Notification::assertSentTo($person, ResetPasswordNotification::class);
    }

    public function test_a_session_of_an_agent_that_existed_all_the_same_stays_locked_out_of_the_api(): void
    {
        $this->actingAs($this->agent(), 'web');

        $this->postJson('/api', ['query' => '{ me { id } }'])
            ->assertForbidden()
            ->assertJsonPath('errors.0.extensions.code', EnsureNotAgent::CODE);
    }

    public function test_the_token_of_an_agent_works_for_the_connector_only(): void
    {
        $agent = $this->agent();
        $token = McpTokens::create($agent, 'Claude')->plainTextToken;
        $headers = ['Accept' => 'application/json, text/event-stream', 'Authorization' => "Bearer {$token}"];

        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], $headers)->assertOk()->assertSee('export_plan');

        $this->app['auth']->forgetGuards();
        $this->postJson('/api', ['query' => '{ me { id } }'], ['Authorization' => "Bearer {$token}"])
            ->assertForbidden()
            ->assertJsonPath('errors.0.extensions.code', EnsureNotAgent::CODE);
    }

    public function test_the_project_role_agent_may_exchange_the_plan_but_not_manage_the_project(): void
    {
        $agent = $this->agent();
        $project = Project::factory()->withMember($agent, ProjectRole::AGENT)->create();

        foreach ([Right::PLANER_EXPORT, Right::PLANER_IMPORT, Right::TASK_UPDATE, Right::TASK_MOVE, Right::COMMENT_CREATE, Right::MILESTONE_CREATE] as $right) {
            $this->assertTrue(Rights::allows($agent, $right, $project), $right->value);
        }

        foreach ([Right::MEMBERS_MANAGE, Right::PROJECT_UPDATE, Right::PROJECT_ARCHIVE, Right::PROJECT_DELETE, Right::TASK_DELETE, Right::PROJECTS_MANAGE_ANY] as $right) {
            $this->assertFalse(Rights::allows($agent, $right, $project), $right->value);
        }
    }

    public function test_an_agent_is_not_an_admin_and_is_released_and_verified(): void
    {
        $agent = $this->agent();

        $this->assertTrue($agent->isAgent());
        $this->assertTrue($agent->isApproved());
        $this->assertTrue($agent->hasVerifiedEmail());
        $this->assertNotSame('admin', $agent->role->key);
    }
}
