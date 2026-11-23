<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Support\StrongAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

/**
 * The planer as authorization server: code flow with PKCE from the redirect to the consent page up to the tokens.
 */
class OAuthAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const REDIRECT_URI = 'https://app.example/callback';

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        config([
            'passport.private_key' => $private,
            'passport.public_key' => openssl_pkey_get_details($key)['key'],
            'app.frontend_url' => 'https://planer.test',
        ]);
    }

    public function test_a_guest_is_sent_to_the_login_of_the_frontend_and_comes_back(): void
    {
        $url = $this->authorizeUrl($this->client());

        $response = $this->get($url);

        $response->assertRedirect();
        $this->assertStringStartsWith('https://planer.test/login?redirect=', $response->headers->get('Location'));
        // The way back is a path of the frontend (it takes no other), with the whole request in its query.
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('/oauth/authorize', parse_url($query['redirect'], PHP_URL_PATH));
        parse_str((string) parse_url($query['redirect'], PHP_URL_QUERY), $back);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $original);
        $this->assertEquals($original, $back);
    }

    public function test_a_session_without_the_second_factor_authorizes_nothing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get($this->authorizeUrl($this->client()));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://planer.test/login?redirect=', $response->headers->get('Location'));
    }

    public function test_the_whole_flow_from_the_consent_page_to_the_tokens(): void
    {
        $user = User::factory()->create(['name' => 'Anna']);
        $client = $this->client();
        [$verifier, $challenge] = $this->pkce();
        $session = [StrongAuthentication::SESSION_KEY => (string) $user->getKey()];

        $this->withSession($session)->actingAs($user)->get($this->authorizeUrl($client, $challenge))
            ->assertRedirect('https://planer.test/oauth/consent');

        $consent = $this->withSession($session)->actingAs($user)->getJson('/oauth/consent')
            ->assertOk()
            ->assertJsonPath('client.name', 'Test App')
            ->assertJsonPath('client.redirectHost', 'app.example')
            ->assertJsonPath('user.name', 'Anna')
            ->assertJsonPath('scopes.0.id', 'mcp:use');
        $this->assertNotEmpty($consent->json('authToken'));

        $approved = $this->withSession($session)->actingAs($user)
            ->postJson('/oauth/consent/approve', ['auth_token' => $consent->json('authToken')])
            ->assertOk();
        $redirect = $approved->json('redirect');
        $this->assertStringStartsWith(self::REDIRECT_URI.'?', $redirect);
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);
        $this->assertSame('xyz', $query['state']);

        $tokens = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'redirect_uri' => self::REDIRECT_URI,
            'code' => $query['code'],
            'code_verifier' => $verifier,
        ])->assertOk();

        $this->assertNotEmpty($tokens->json('access_token'));
        $this->assertNotEmpty($tokens->json('refresh_token'));
        $this->assertSame(3600, $tokens->json('expires_in'));
    }

    public function test_the_person_can_refuse(): void
    {
        $user = User::factory()->create();
        $session = [StrongAuthentication::SESSION_KEY => (string) $user->getKey()];

        $this->withSession($session)->actingAs($user)->get($this->authorizeUrl($this->client()));
        $token = $this->withSession($session)->actingAs($user)->getJson('/oauth/consent')->json('authToken');

        $denied = $this->withSession($session)->actingAs($user)->postJson('/oauth/consent/deny', ['auth_token' => $token])->assertOk();

        $this->assertStringContainsString('error=access_denied', $denied->json('redirect'));
    }

    public function test_a_wrong_code_verifier_gets_no_token(): void
    {
        $user = User::factory()->create();
        $client = $this->client();
        [, $challenge] = $this->pkce();
        $session = [StrongAuthentication::SESSION_KEY => (string) $user->getKey()];

        $this->withSession($session)->actingAs($user)->get($this->authorizeUrl($client, $challenge));
        $token = $this->withSession($session)->actingAs($user)->getJson('/oauth/consent')->json('authToken');
        $redirect = $this->withSession($session)->actingAs($user)->postJson('/oauth/consent/approve', ['auth_token' => $token])->json('redirect');
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);

        $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'redirect_uri' => self::REDIRECT_URI,
            'code' => $query['code'],
            'code_verifier' => str_repeat('a', 64),
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_only_the_exact_registered_redirect_uri_is_accepted(): void
    {
        $user = User::factory()->create();
        $client = $this->client();
        [, $challenge] = $this->pkce();
        $session = [StrongAuthentication::SESSION_KEY => (string) $user->getKey()];

        $this->withSession($session)->actingAs($user)
            ->getJson($this->authorizeUrl($client, $challenge, 'https://evil.example/callback'))
            ->assertStatus(401)
            ->assertJsonPath('error', 'invalid_client');
    }

    public function test_the_consent_routes_need_a_login_and_a_pending_request(): void
    {
        $this->getJson('/oauth/consent')->assertUnauthorized();

        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/oauth/consent')->assertNotFound();
    }

    private function client(): Client
    {
        return app(ClientRepository::class)->createAuthorizationCodeGrantClient('Test App', [self::REDIRECT_URI], confidential: false);
    }

    /** @return array{0: string, 1: string} verifier and S256 challenge */
    private function pkce(): array
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');

        return [$verifier, rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
    }

    private function authorizeUrl(Client $client, ?string $challenge = null, string $redirectUri = self::REDIRECT_URI): string
    {
        return '/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'xyz',
            'code_challenge' => $challenge ?? $this->pkce()[1],
            'code_challenge_method' => 'S256',
        ]);
    }
}
