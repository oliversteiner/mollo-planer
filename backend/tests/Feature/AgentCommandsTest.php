<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * app:create-agent and app:user-token (task 31): the token is shown once at the terminal, only for agents, and never
 * reaches the log.
 */
class AgentCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_agent_makes_a_released_member_without_admin_rights(): void
    {
        Log::spy();

        $this->artisan('app:create-agent', ['name' => 'Claude Club DB', 'email' => 'claude@example.com'])
            ->expectsConfirmation(__('console.create_agent.confirm', ['name' => 'Claude Club DB', 'email' => 'claude@example.com']), 'yes')
            ->assertSuccessful();

        $agent = User::query()->where('email', 'claude@example.com')->sole();
        $this->assertTrue($agent->isAgent());
        $this->assertSame('mitglied', $agent->role->key);
        $this->assertTrue($agent->isApproved());
        $this->assertTrue($agent->hasVerifiedEmail());
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_create_agent_refuses_a_known_address_and_writes_nothing_without_a_terminal(): void
    {
        User::factory()->create(['email' => 'claude@example.com']);

        $this->artisan('app:create-agent', ['name' => 'Claude', 'email' => 'claude@example.com'])->assertFailed();
        $this->artisan('app:create-agent', ['name' => 'Claude', 'email' => 'neu@example.com', '--no-interaction' => true])->assertFailed();

        $this->assertSame(0, User::query()->where('is_agent', true)->count());
    }

    public function test_user_token_shows_the_token_once_and_keeps_it_out_of_the_log(): void
    {
        Log::spy();
        $agent = User::factory()->agent()->create(['name' => 'Claude', 'email' => 'claude@example.com']);

        $this->artisan('app:user-token', ['user' => 'claude@example.com', 'name' => 'Claude Desktop'])
            ->expectsConfirmation(__('console.user_token.confirm', ['name' => 'Claude']), 'yes')
            ->expectsOutputToContain('|')
            ->assertSuccessful();

        $token = $agent->tokens()->sole();
        $this->assertSame('Claude Desktop', $token->name);
        $this->assertSame(['mcp'], $token->abilities);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => ! str_contains(json_encode($context), '|'));
    }

    public function test_user_token_refuses_a_persons_account(): void
    {
        $person = User::factory()->create(['email' => 'anna@example.com']);

        $this->artisan('app:user-token', ['user' => 'anna@example.com'])->assertFailed();

        $this->assertSame(0, $person->tokens()->count());
    }

    public function test_user_token_revokes_all_tokens_of_an_agent(): void
    {
        $agent = User::factory()->agent()->create(['name' => 'Claude', 'email' => 'claude@example.com']);
        $agent->createToken('a', ['mcp']);
        $agent->createToken('b', ['mcp']);

        $this->artisan('app:user-token', ['user' => 'claude@example.com', '--revoke' => true])
            ->expectsConfirmation(__('console.user_token.confirm_revoke', ['name' => 'Claude']), 'yes')
            ->assertSuccessful();

        $this->assertSame(0, $agent->tokens()->count());
    }

    public function test_user_token_needs_a_terminal(): void
    {
        $agent = User::factory()->agent()->create(['email' => 'claude@example.com']);

        $this->artisan('app:user-token', ['user' => 'claude@example.com', '--no-interaction' => true])->assertFailed();

        $this->assertSame(0, $agent->tokens()->count());
    }
}
