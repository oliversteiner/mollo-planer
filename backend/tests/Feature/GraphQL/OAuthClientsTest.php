<?php

namespace Tests\Feature\GraphQL;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use PHPUnit\Framework\Attributes\DataProvider;

/** The applications that may log in through the planer are managed by admins only (docs/oauth-sso.md). */
class OAuthClientsTest extends GraphQLTestCase
{
    private const CREATE = 'mutation ($name: String!, $uris: [String!]!, $confidential: Boolean!) { createOAuthClient(name: $name, redirectUris: $uris, confidential: $confidential) { secret client { id name redirectUris confidential revoked } } }';

    public function test_an_admin_makes_a_confidential_client_and_sees_the_secret_once(): void
    {
        $response = $this->confirmed('admin')->gql(self::CREATE, ['name' => 'Club DB', 'uris' => ['https://clubdb.example/callback'], 'confidential' => true])
            ->assertJsonMissingPath('errors');

        $this->assertNotEmpty($response->json('data.createOAuthClient.secret'));
        $this->assertSame('Club DB', $response->json('data.createOAuthClient.client.name'));
        $this->assertTrue($response->json('data.createOAuthClient.client.confidential'));
        $this->assertSame(['https://clubdb.example/callback'], $response->json('data.createOAuthClient.client.redirectUris'));

        $list = $this->as('admin')->gql('{ oauthClients { name confidential } }')->assertJsonMissingPath('errors');
        $this->assertSame([['name' => 'Club DB', 'confidential' => true]], $list->json('data.oauthClients'));
        $this->assertStringNotContainsString('secret', (string) $list->getContent());
    }

    public function test_a_public_client_has_no_secret_and_may_use_the_scheme_of_an_app(): void
    {
        $response = $this->confirmed('admin')->gql(self::CREATE, ['name' => 'Planer iOS', 'uris' => ['ch.mollo.planer:/oauth/callback'], 'confidential' => false])
            ->assertJsonMissingPath('errors');

        $this->assertNull($response->json('data.createOAuthClient.secret'));
        $this->assertFalse($response->json('data.createOAuthClient.client.confidential'));
    }

    /** @return array<string, array{0: list<string>, 1: bool}> */
    public static function badAddresses(): array
    {
        return [
            'wildcard' => [['https://*.example/callback'], true],
            'http on a real host' => [['http://clubdb.example/callback'], true],
            'fragment' => [['https://clubdb.example/callback#x'], true],
            'login data' => [['https://user:pass@clubdb.example/callback'], true],
            'no host' => [['https:///callback'], true],
            'own scheme for a confidential client' => [['ch.mollo.app:/callback'], true],
            'javascript' => [['javascript:alert(1)'], false],
            'scheme without path' => [['ch.mollo.app:'], false],
            'empty list' => [[], true],
            'too many' => [array_map(fn (int $i): string => "https://a.example/$i", range(1, 11)), true],
        ];
    }

    /** @param  list<string>  $uris */
    #[DataProvider('badAddresses')]
    public function test_only_exact_safe_redirect_addresses_are_accepted(array $uris, bool $confidential): void
    {
        $this->confirmed('admin')->gql(self::CREATE, ['name' => 'Test', 'uris' => $uris, 'confidential' => $confidential])
            ->assertJsonPath('errors.0.extensions.validation.redirectUris.0', fn ($message) => is_string($message) && $message !== '');

        $this->assertSame(0, Client::query()->count());
    }

    public function test_localhost_may_use_http_for_development(): void
    {
        $this->confirmed('admin')->gql(self::CREATE, ['name' => 'Dev', 'uris' => ['http://localhost:3000/callback', 'http://127.0.0.1/cb'], 'confidential' => false])
            ->assertJsonMissingPath('errors');
    }

    public function test_it_needs_a_recent_password_confirmation(): void
    {
        $this->as('admin')->gql(self::CREATE, ['name' => 'Club DB', 'uris' => ['https://clubdb.example/callback'], 'confidential' => true])
            ->assertJsonPath('errors.0.extensions.validation.password.0', fn ($message) => is_string($message) && $message !== '');

        $this->assertSame(0, Client::query()->count());
    }

    public function test_only_admins_see_and_make_clients(): void
    {
        $this->client('Club DB');

        foreach (['owner', 'member', 'stranger', 'projektleitung', 'lesend'] as $name) {
            $this->assertUnauthorized($this->as($name)->gql('{ oauthClients { name } }'));
            $this->assertUnauthorized($this->confirmed($name)->gql(self::CREATE, ['name' => 'Test App', 'uris' => ['https://a.example/cb'], 'confidential' => true]));
            $this->assertUnauthorized($this->confirmed($name)->gql('mutation { revokeOAuthClient(id: "x") { id } }'));
        }

        $this->assertSame(1, Client::query()->count());
    }

    public function test_revoking_a_client_revokes_its_tokens_too(): void
    {
        $client = $this->client('Club DB');
        DB::table('oauth_access_tokens')->insert(['id' => 'a1', 'user_id' => $this->users['member']->id, 'client_id' => $client->getKey(), 'revoked' => false, 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('oauth_refresh_tokens')->insert(['id' => 'r1', 'access_token_id' => 'a1', 'revoked' => false, 'expires_at' => now()->addDays(30)]);

        $this->confirmed('admin')->gql('mutation ($id: ID!) { revokeOAuthClient(id: $id) { id revoked } }', ['id' => $client->getKey()])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.revokeOAuthClient.revoked', true);

        $this->assertTrue((bool) DB::table('oauth_access_tokens')->where('id', 'a1')->value('revoked'));
        $this->assertTrue((bool) DB::table('oauth_refresh_tokens')->where('id', 'r1')->value('revoked'));
    }

    public function test_an_unknown_client_gives_null(): void
    {
        $this->confirmed('admin')->gql('mutation { revokeOAuthClient(id: "does-not-exist") { id } }')
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.revokeOAuthClient', null);
    }

    private function client(string $name): Client
    {
        return app(ClientRepository::class)->createAuthorizationCodeGrantClient($name, ['https://clubdb.example/callback']);
    }

    /** A strong session with a password confirmation of a moment ago. */
    private function confirmed(string $name): static
    {
        return $this->as($name)->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_the_list_says_which_clients_registered_themselves(): void
    {
        $this->client('Von Hand');
        $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']])->assertCreated();
        $this->confirmed('admin')->gql(self::CREATE, ['name' => 'Club DB', 'uris' => ['https://clubdb.example/callback'], 'confidential' => true])->assertJsonMissingPath('errors');

        $rows = collect($this->as('admin')->gql('{ oauthClients { name selfRegistered } }')->assertJsonMissingPath('errors')->json('data.oauthClients'))->pluck('selfRegistered', 'name');

        $this->assertFalse($rows['Club DB'], 'made by an admin in the interface');
        $this->assertTrue($rows['Claude'], 'registered itself');
        $this->assertTrue($rows['Von Hand'], 'made without an admin (the repository of Passport), so nobody looked at it');
    }
}
