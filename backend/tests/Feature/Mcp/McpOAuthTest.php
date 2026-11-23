<?php

namespace Tests\Feature\Mcp;

use App\Core\Identity\OAuthClients;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use App\Support\StrongAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Tests\TestCase;

/**
 * Claude connects as a custom connector with OAuth: metadata, registration of the client by itself, the flow, and what a
 * token of the OAuth server may do at /mcp (docs/oauth-sso.md).
 */
class McpOAuthTest extends TestCase
{
    use RefreshDatabase;

    private const REDIRECT_URI = 'https://claude.ai/api/mcp/auth_callback';

    private const HEADERS = ['Accept' => 'application/json, text/event-stream'];

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        config([
            'passport.private_key' => $private,
            'passport.public_key' => openssl_pkey_get_details($key)['key'],
            'app.url' => 'https://api.planer.test',
            'mcp.authorization_server' => 'https://api.planer.test',
            'app.frontend_url' => 'https://planer.test',
        ]);
    }

    public function test_the_metadata_names_the_server_and_the_authorization_server_of_this_environment(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJsonPath('authorization_servers', ['https://api.planer.test'])
            ->assertJsonPath('scopes_supported', ['mcp:use']);

        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('issuer', 'https://api.planer.test')
            ->assertJsonPath('authorization_endpoint', url('/oauth/authorize'))
            ->assertJsonPath('token_endpoint', url('/oauth/token'))
            ->assertJsonPath('registration_endpoint', url('/oauth/register'))
            ->assertJsonPath('code_challenge_methods_supported', ['S256']);
    }

    public function test_a_client_registers_itself_as_a_public_client_for_the_addresses_of_claude_and_localhost(): void
    {
        $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [self::REDIRECT_URI]])
            ->assertCreated()
            ->assertJsonPath('token_endpoint_auth_method', 'none')
            ->assertJsonMissingPath('client_secret');

        $this->postJson('/oauth/register', ['client_name' => 'Claude Code', 'redirect_uris' => ['http://localhost:33418/callback']])->assertCreated();

        $client = Client::query()->where('name', 'Claude')->firstOrFail();
        $this->assertFalse($client->confidential());
        $this->assertSame([self::REDIRECT_URI], $client->redirect_uris);
    }

    public function test_a_client_cannot_register_for_any_other_address(): void
    {
        $this->postJson('/oauth/register', ['client_name' => 'Evil', 'redirect_uris' => ['https://evil.example/callback']])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_redirect_uri');

        $this->assertSame(0, Client::query()->count());
    }

    public function test_on_prod_the_addresses_can_be_restricted_to_claude_so_that_localhost_is_refused(): void
    {
        config(['mcp.redirect_domains' => ['https://claude.ai/', 'https://claude.com/']]);

        $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [self::REDIRECT_URI]])->assertCreated();
        $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => ['https://claude.com/api/mcp/auth_callback']])->assertCreated();
        $this->postJson('/oauth/register', ['client_name' => 'Local', 'redirect_uris' => ['http://localhost:33418/callback']])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_redirect_uri');
        $this->postJson('/oauth/register', ['client_name' => 'Local', 'redirect_uris' => ['http://127.0.0.1:33418/callback']])->assertStatus(400);
    }

    public function test_the_registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/oauth/register', ['client_name' => "C$i", 'redirect_uris' => [self::REDIRECT_URI]])->assertCreated();
        }

        $this->postJson('/oauth/register', ['client_name' => 'C11', 'redirect_uris' => [self::REDIRECT_URI]])->assertStatus(429);
    }

    public function test_without_a_token_the_server_says_where_to_log_in(): void
    {
        $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], self::HEADERS)->assertUnauthorized();

        $this->assertStringContainsString('resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp').'"', $response->headers->get('WWW-Authenticate'));

        $this->withToken('aaa.bbb.ccc')->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], self::HEADERS)->assertUnauthorized();
    }

    public function test_a_token_of_the_oauth_server_works_as_the_person_with_all_tools(): void
    {
        $user = $this->member();
        $token = $this->tokenFor($user, 'mcp:use');

        $names = $this->tools($token);
        $this->assertContains('export_plan', $names);
        $this->assertContains('import_plan', $names);
        $this->assertContains('update_tasks', $names);

        $this->callTool($token, 'list_projects')->assertOk()->assertSee('Mollo');
    }

    public function test_a_token_with_mcp_read_sees_and_calls_only_the_tools_that_read(): void
    {
        $token = $this->tokenFor($this->member(), 'mcp:read');

        $names = $this->tools($token);
        $this->assertContains('export_plan', $names);
        $this->assertContains('list_projects', $names);
        foreach (['import_plan', 'update_tasks', 'add_comment', 'create_idea'] as $writing) {
            $this->assertNotContains($writing, $names);
        }

        $this->app['auth']->forgetGuards();
        $this->callTool($token, 'update_tasks', ['project_id' => '1', 'base_revision' => 1, 'tasks' => [], 'dry_run' => true])
            ->assertJsonPath('error.code', -32602);
    }

    public function test_a_token_with_mcp_write_may_change_things(): void
    {
        $names = $this->tools($this->tokenFor($this->member(), 'mcp:write'));

        $this->assertContains('update_tasks', $names);
    }

    public function test_a_token_without_an_mcp_scope_is_refused(): void
    {
        $token = $this->tokenFor($this->member(), 'openid');

        $this->withToken($token)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], self::HEADERS)->assertForbidden();
    }

    public function test_the_token_stops_working_when_the_account_may_not_work_any_more(): void
    {
        $user = $this->member();
        $token = $this->tokenFor($user, 'mcp:use');
        $user->forceFill(['approved_at' => null])->save();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], self::HEADERS)->assertUnauthorized();
    }

    public function test_revoking_the_client_ends_its_tokens(): void
    {
        $token = $this->tokenFor($this->member(), 'mcp:use');
        $client = Client::query()->firstOrFail();

        app(OAuthClients::class)->revoke(User::factory()->admin()->create(), (string) $client->getKey());

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], self::HEADERS)->assertUnauthorized();
    }

    public function test_a_token_for_the_mcp_server_opens_nothing_else(): void
    {
        $token = $this->tokenFor($this->member(), 'mcp:use');

        $this->app['auth']->forgetGuards();
        $response = $this->withToken($token)->postJson('/api', ['query' => '{ me { id } }']);

        $this->assertNull($response->json('data.me'));
    }

    private function member(): User
    {
        $user = User::factory()->create();
        Project::factory()->withMember($user, ProjectRole::MEMBER)->create(['name' => 'Mollo Planer']);

        return $user;
    }

    /** @return list<string> */
    private function tools(string $token): array
    {
        $this->app['auth']->forgetGuards();
        $response = $this->withToken($token)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], self::HEADERS)->assertOk();

        return array_column($response->json('result.tools'), 'name');
    }

    /** @param  array<string, mixed>  $arguments */
    private function callTool(string $token, string $name, array $arguments = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => $name, 'arguments' => (object) $arguments]], self::HEADERS);
    }

    /** The whole flow of Claude: register, agree, exchange the code; the access token that comes out. */
    private function tokenFor(User $user, string $scope): string
    {
        $clientId = $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [self::REDIRECT_URI]])->assertCreated()->json('client_id');
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $session = [StrongAuthentication::SESSION_KEY => (string) $user->getKey()];

        $this->withSession($session)->actingAs($user)->get('/oauth/authorize?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => $scope,
            'state' => 'xyz',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]))->assertRedirect('https://planer.test/oauth/consent');
        $consent = $this->withSession($session)->actingAs($user)->getJson('/oauth/consent')->json('authToken');
        $redirect = $this->withSession($session)->actingAs($user)->postJson('/oauth/consent/approve', ['auth_token' => $consent])->json('redirect');
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);

        $this->app['auth']->forgetGuards();

        return $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'code' => $query['code'],
            'code_verifier' => $verifier,
        ])->assertOk()->json('access_token');
    }
}
