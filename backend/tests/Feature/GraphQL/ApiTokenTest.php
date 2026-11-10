<?php

namespace Tests\Feature\GraphQL;

use App\GraphQL\Mutations\ResetUserTwoFactor;
use App\Support\McpTokens;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Access tokens for Claude (MCP): own only, shown once, revoked with the
 * second factor or the account.
 */
class ApiTokenTest extends GraphQLTestCase
{
    public function test_a_new_token_is_shown_once_and_works_for_the_mcp_server(): void
    {
        $plain = $this->as('owner')->gql('mutation { createApiToken(name: "Claude Laptop") { plainTextToken token { name expiresAt } } }')
            ->assertJsonPath('data.createApiToken.token.name', 'Claude Laptop')
            ->json('data.createApiToken.plainTextToken');

        $this->app['auth']->forgetGuards();
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], [
            'Accept' => 'application/json, text/event-stream',
            'Authorization' => "Bearer {$plain}",
        ])->assertOk();

        $this->as('owner')->gql('{ me { apiTokens { name } } }')
            ->assertJsonPath('data.me.apiTokens', [['name' => 'Claude Laptop']]);
    }

    public function test_the_list_shows_only_own_tokens_for_claude(): void
    {
        McpTokens::create($this->users['manager'], 'Fremd');
        $this->users['owner']->createToken('Kein Claude-Token');
        McpTokens::create($this->users['owner'], 'Meins');

        $this->as('owner')->gql('{ me { apiTokens { name } } }')
            ->assertJsonPath('data.me.apiTokens', [['name' => 'Meins']]);

        $users = $this->as('admin')->gql('{ users { id apiTokens { name } } }')->json('data.users');
        foreach ($users as $user) {
            if ($user['id'] !== (string) $this->users['admin']->id) {
                $this->assertNull($user['apiTokens']);
            }
        }
    }

    public function test_someone_elses_token_cannot_be_revoked(): void
    {
        $foreign = McpTokens::create($this->users['manager'], 'Fremd')->accessToken;
        $own = McpTokens::create($this->users['owner'], 'Meins')->accessToken;
        $delete = 'mutation ($id: ID!) { deleteApiToken(id: $id) { name } }';

        $this->as('owner')->gql($delete, ['id' => $foreign->id])->assertJsonPath('data.deleteApiToken', null);
        $this->as('owner')->gql($delete, ['id' => $own->id])->assertJsonPath('data.deleteApiToken.name', 'Meins');

        $this->assertModelExists($foreign);
        $this->assertModelMissing($own);
    }

    public function test_resetting_two_factor_and_deleting_the_user_revoke_all_tokens(): void
    {
        McpTokens::create($this->users['member'], 'Laptop');
        McpTokens::create($this->users['stranger'], 'Laptop');

        (new ResetUserTwoFactor)(null, ['id' => $this->users['member']->id]);
        $this->as('admin')->gql('mutation ($id: ID!) { deleteUser(id: $id) { id } }', ['id' => $this->users['stranger']->id])
            ->assertJsonMissingPath('errors');

        $this->assertSame(0, PersonalAccessToken::count());
    }
}
