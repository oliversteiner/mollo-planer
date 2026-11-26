<?php

namespace Tests\Feature\GraphQL;

use App\Core\Accounts\AgentAccountCreated;
use App\Models\User;
use App\Notifications\AgentAccessChange;
use App\Support\McpTokens;
use App\Support\StrongAuthentication;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * An admin makes and revokes connector accesses of an agent account in the user administration (task 32):
 * only for agents, only with the right, a strong session and a recent password confirmation. The token is
 * shown once and is in no list, log or mail.
 */
class AgentAccessTest extends GraphQLTestCase
{
    private const CREATE = 'mutation ($id: ID!, $name: String!) { createAgentToken(userId: $id, name: $name) { plainTextToken token { id name } } }';

    private const REVOKE = 'mutation ($id: ID!, $token: ID!) { revokeAgentToken(userId: $id, tokenId: $token) { id name } }';

    private const CREATE_AGENT = 'mutation ($name: String!) { createAgent(input: {name: $name}) { id name email isAgent approved emailVerified role { key } } }';

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent()->create(['name' => 'Kevin']);
    }

    /** The admin with a strong session and a password confirmed a moment ago. */
    private function asConfirmedAdmin(): static
    {
        return $this->as('admin')->withSession([
            StrongAuthentication::SESSION_KEY => (string) $this->users['admin']->id,
            'auth.password_confirmed_at' => time(),
        ]);
    }

    public function test_an_admin_makes_an_access_shown_once_that_works_for_the_connector(): void
    {
        Notification::fake();

        $plain = $this->asConfirmedAdmin()->gql(self::CREATE, ['id' => $this->agent->id, 'name' => 'Kevin Server'])
            ->assertJsonPath('data.createAgentToken.token.name', 'Kevin Server')
            ->json('data.createAgentToken.plainTextToken');

        $this->app['auth']->forgetGuards();
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], [
            'Accept' => 'application/json, text/event-stream',
            'Authorization' => "Bearer {$plain}",
        ])->assertOk();

        $this->assertSame(1, $this->agent->tokens()->count());
        $this->assertNotSame($plain, $this->agent->tokens()->first()->token, 'only the hash is stored');
    }

    public function test_the_plain_token_is_in_no_list_log_or_mail(): void
    {
        Notification::fake();
        Log::spy();

        $plain = $this->asConfirmedAdmin()->gql(self::CREATE, ['id' => $this->agent->id, 'name' => 'Kevin Server'])
            ->json('data.createAgentToken.plainTextToken');
        $secret = explode('|', $plain)[1];

        $list = $this->asConfirmedAdmin()->gql('{ users { id apiTokens { id name createdAt lastUsedAt expiresAt } } }');
        $this->assertStringNotContainsString($secret, $list->getContent());
        $agent = collect($list->json('data.users'))->firstWhere('id', (string) $this->agent->id);
        $this->assertSame('Kevin Server', $agent['apiTokens'][0]['name']);

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) use ($secret): bool {
            return $context['action'] === 'created'
                && $context['admin_id'] === $this->users['admin']->id
                && $context['agent_id'] === $this->agent->id
                && $context['access_name'] === 'Kevin Server'
                && ! str_contains(json_encode($context), $secret);
        });

        Notification::assertSentTo($this->users['admin'], AgentAccessChange::class, function (AgentAccessChange $mail) use ($secret): bool {
            $text = implode(' ', $mail->toMail($this->users['admin'])->introLines).$mail->toMail($this->users['admin'])->subject;

            return ! str_contains($text, $secret) && $mail->access === 'Kevin Server' && $mail->kind === 'created';
        });
    }

    public function test_all_admins_hear_about_it_in_their_own_language(): void
    {
        Notification::fake();
        $second = User::factory()->admin()->create(['locale' => 'en']);

        $this->asConfirmedAdmin()->gql(self::CREATE, ['id' => $this->agent->id, 'name' => 'Kevin Server'])->assertJsonMissingPath('errors');

        Notification::assertSentTo([$this->users['admin'], $second], AgentAccessChange::class);
        Notification::assertNotSentTo($this->users['owner'], AgentAccessChange::class);
        Notification::assertSentTo($second, AgentAccessChange::class, fn ($mail, $channels, $notification) => $notification->locale === 'en');
    }

    public function test_an_admin_revokes_an_access_and_it_stops_working(): void
    {
        Notification::fake();
        $token = McpTokens::createForAgent($this->agent, 'Kevin Server');

        $this->asConfirmedAdmin()->gql(self::REVOKE, ['id' => $this->agent->id, 'token' => $token->accessToken->id])
            ->assertJsonPath('data.revokeAgentToken.name', 'Kevin Server');

        $this->assertModelMissing($token->accessToken);
        Notification::assertSentTo($this->users['admin'], AgentAccessChange::class, fn (AgentAccessChange $mail) => $mail->kind === 'revoked');
    }

    public function test_a_person_account_is_refused_with_a_clear_message(): void
    {
        Notification::fake();

        $this->asConfirmedAdmin()->gql(self::CREATE, ['id' => $this->users['member']->id, 'name' => 'Fremd'])
            ->assertJsonPath('errors.0.extensions.validation.userId.0', __('graphql.agent_access.not_an_agent', ['name' => 'David Member']));

        $this->assertSame(0, $this->users['member']->tokens()->count());

        $own = McpTokens::create($this->users['member'], 'Meins')->accessToken;
        $this->asConfirmedAdmin()->gql(self::REVOKE, ['id' => $this->users['member']->id, 'token' => $own->id])
            ->assertJsonPath('errors.0.extensions.validation.userId.0', __('graphql.agent_access.not_an_agent', ['name' => 'David Member']));
        $this->assertModelExists($own);
        Notification::assertNothingSent();
    }

    public function test_a_token_of_somebody_else_cannot_be_revoked_through_an_agent(): void
    {
        $foreign = McpTokens::create($this->users['member'], 'Meins')->accessToken;
        $plain = $this->agent->createToken('Kein Connector-Token');

        $this->asConfirmedAdmin()->gql(self::REVOKE, ['id' => $this->agent->id, 'token' => $foreign->id])
            ->assertJsonPath('data.revokeAgentToken', null);
        $this->asConfirmedAdmin()->gql(self::REVOKE, ['id' => $this->agent->id, 'token' => $plain->accessToken->id])
            ->assertJsonPath('data.revokeAgentToken', null);

        $this->assertModelExists($foreign);
        $this->assertModelExists($plain->accessToken);
    }

    public function test_without_the_right_it_is_refused(): void
    {
        foreach (['owner', 'manager', 'member', 'koordination'] as $name) {
            $this->as($name)->withSession([StrongAuthentication::SESSION_KEY => (string) $this->users[$name]->id, 'auth.password_confirmed_at' => time()])
                ->gql(self::CREATE, ['id' => $this->agent->id, 'name' => 'Kevin Server'])
                ->assertJsonPath('data.createAgentToken', null)
                ->assertGraphQLErrorMessage('You are not allowed to do this.');
        }

        $this->assertSame(0, $this->agent->tokens()->count());
    }

    public function test_without_a_strong_session_it_is_refused(): void
    {
        $this->actingAs($this->users['admin'])
            ->withSession(['auth.password_confirmed_at' => time()])
            ->withHeader('Referer', 'http://localhost');

        $this->gql(self::CREATE, ['id' => $this->agent->id, 'name' => 'Kevin Server'])->assertForbidden();

        $this->assertSame(0, $this->agent->tokens()->count());
    }

    public function test_without_a_recent_password_confirmation_it_is_refused(): void
    {
        $token = McpTokens::createForAgent($this->agent, 'Kevin Server')->accessToken;

        $this->as('admin')->gql(self::CREATE, ['id' => $this->agent->id, 'name' => 'Kevin Neu'])
            ->assertJsonPath('errors.0.extensions.validation.password.0', __('graphql.password_confirmation_required'));
        $this->as('admin')->withSession([StrongAuthentication::SESSION_KEY => (string) $this->users['admin']->id, 'auth.password_confirmed_at' => time() - config('auth.password_timeout') - 60])
            ->gql(self::REVOKE, ['id' => $this->agent->id, 'token' => $token->id])
            ->assertJsonPath('errors.0.extensions.validation.password.0', __('graphql.password_confirmation_required'));

        $this->assertSame(1, $this->agent->tokens()->count());
    }

    public function test_the_name_has_two_to_sixty_characters(): void
    {
        foreach (['K', str_repeat('x', 61)] as $name) {
            $this->asConfirmedAdmin()->gql(self::CREATE, ['id' => $this->agent->id, 'name' => $name])
                ->assertJsonPath('data.createAgentToken', null);
        }

        $this->assertSame(0, $this->agent->tokens()->count());
    }

    public function test_the_list_of_an_agent_is_visible_for_admins_only_and_a_persons_list_for_nobody_else(): void
    {
        McpTokens::createForAgent($this->agent, 'Kevin Server');
        McpTokens::create($this->users['member'], 'Meins');

        $query = '{ users { id apiTokens { name } } }';
        $admin = collect($this->as('admin')->gql($query)->json('data.users'))->keyBy('id')->all();
        $this->assertSame([['name' => 'Kevin Server']], $admin[(string) $this->agent->id]['apiTokens']);
        $this->assertNull($admin[(string) $this->users['member']->id]['apiTokens']);

        // Somebody without the right cannot list users at all, so not the accesses of an agent either.
        $this->assertUnauthorized($this->as('member')->gql($query));
    }

    public function test_the_list_only_shows_connector_tokens(): void
    {
        McpTokens::createForAgent($this->agent, 'Kevin Server');
        $this->agent->createToken('Kein Connector-Token');

        $users = $this->as('admin')->gql('{ users { id apiTokens { name } } }')->json('data.users');

        $this->assertSame([['name' => 'Kevin Server']], collect($users)->firstWhere('id', (string) $this->agent->id)['apiTokens']);
    }

    public function test_a_token_is_made_for_an_agent_only(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        McpTokens::createForAgent($this->users['member'], 'Nope');
    }

    public function test_tokens_count_is_unchanged_for_persons_after_all_refusals(): void
    {
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_an_admin_creates_an_agent_account_logged_and_told_to_the_admins(): void
    {
        Notification::fake();
        Log::spy();
        $second = User::factory()->admin()->create(['locale' => 'en']);

        $this->asConfirmedAdmin()->gql(self::CREATE_AGENT, ['name' => ' Claude Club DB '])
            ->assertJsonPath('data.createAgent.name', 'Claude Club DB')
            ->assertJsonPath('data.createAgent.email', 'claude-club-db@agent.invalid')
            ->assertJsonPath('data.createAgent.isAgent', true)
            ->assertJsonPath('data.createAgent.approved', true)
            ->assertJsonPath('data.createAgent.emailVerified', true)
            ->assertJsonPath('data.createAgent.role.key', 'mitglied');

        $agent = User::query()->where('email', 'claude-club-db@agent.invalid')->sole();
        $this->assertTrue($agent->isAgent());
        $this->assertSame(0, $agent->tokens()->count());

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $message === 'Agent account created.'
            && $context['admin_id'] === $this->users['admin']->id
            && $context['agent_id'] === $agent->id
            && ! str_contains(json_encode($context), 'claude-club-db@agent.invalid'));
        Notification::assertSentTo([$this->users['admin'], $second], AgentAccountCreated::class);
        Notification::assertSentTo($second, AgentAccountCreated::class, fn ($mail, $channels, $notification) => $notification->locale === 'en');
        Notification::assertNotSentTo($this->users['owner'], AgentAccountCreated::class);
        Notification::assertNothingSentTo($agent);
    }

    public function test_creating_an_agent_needs_the_right(): void
    {
        foreach (['owner', 'manager', 'member', 'koordination'] as $name) {
            $this->as($name)->withSession([StrongAuthentication::SESSION_KEY => (string) $this->users[$name]->id, 'auth.password_confirmed_at' => time()])
                ->gql(self::CREATE_AGENT, ['name' => "Claude {$name}"])
                ->assertJsonPath('data.createAgent', null)
                ->assertGraphQLErrorMessage('You are not allowed to do this.');
        }

        $this->assertSame(1, User::query()->where('is_agent', true)->count(), 'only the agent of setUp');
    }

    public function test_creating_an_agent_needs_a_recent_password_confirmation(): void
    {
        $this->as('admin')->gql(self::CREATE_AGENT, ['name' => 'Claude'])
            ->assertJsonPath('errors.0.extensions.validation.password.0', __('graphql.password_confirmation_required'));

        $this->assertFalse(User::query()->where('email', 'claude@agent.invalid')->exists());
    }

    public function test_creating_an_agent_needs_a_strong_session(): void
    {
        $this->actingAs($this->users['admin'])
            ->withSession(['auth.password_confirmed_at' => time()])
            ->withHeader('Referer', 'http://localhost');
        $this->gql(self::CREATE_AGENT, ['name' => 'Claude'])->assertForbidden();

        $this->assertFalse(User::query()->where('email', 'claude@agent.invalid')->exists());
    }

    public function test_creating_an_agent_refuses_a_short_name(): void
    {
        Notification::fake();

        $this->asConfirmedAdmin()->gql(self::CREATE_AGENT, ['name' => 'C'])
            ->assertJsonPath('data.createAgent', null);

        $this->assertSame(1, User::query()->where('is_agent', true)->count());
        Notification::assertNothingSent();
    }

    public function test_the_identifier_is_made_from_the_name_and_stays_unique(): void
    {
        Notification::fake();

        $first = $this->asConfirmedAdmin()->gql(self::CREATE_AGENT, ['name' => 'Jürg Müller'])->json('data.createAgent.email');
        $second = $this->asConfirmedAdmin()->gql(self::CREATE_AGENT, ['name' => 'JÜRG müller'])->json('data.createAgent.email');
        $third = $this->asConfirmedAdmin()->gql(self::CREATE_AGENT, ['name' => '!!'])->json('data.createAgent.email');

        $this->assertSame('juerg-mueller@agent.invalid', $first);
        $this->assertSame('juerg-mueller-2@agent.invalid', $second);
        $this->assertSame('agent@agent.invalid', $third);
    }

    public function test_a_person_never_gets_an_identifier_of_an_agent(): void
    {
        $this->as('admin')->gql('mutation { createUser(input: {name: "Mensch", email: "mensch@agent.invalid"}) { id } }')
            ->assertJsonPath('errors.0.extensions.validation', ['input.email' => [__('graphql.agent_identifier_reserved')]]);
        $this->as('admin')->gql('mutation ($id: ID!) { updateUser(id: $id, input: {email: "Mensch@Agent.Invalid"}) { id } }', ['id' => $this->users['member']->id])
            ->assertJsonPath('errors.0.extensions.validation', ['input.email' => [__('graphql.agent_identifier_reserved')]]);

        // An agent account may get one.
        $this->as('admin')->gql('mutation ($id: ID!) { updateUser(id: $id, input: {email: "kevin@agent.invalid"}) { email } }', ['id' => $this->agent->id])
            ->assertJsonPath('data.updateUser.email', 'kevin@agent.invalid');

        $this->assertFalse(User::query()->where('is_agent', false)->where('email', 'like', '%@agent.invalid')->exists());
    }
}
