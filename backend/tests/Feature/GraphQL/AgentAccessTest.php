<?php

namespace Tests\Feature\GraphQL;

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
}
