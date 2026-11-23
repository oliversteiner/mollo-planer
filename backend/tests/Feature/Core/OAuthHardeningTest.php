<?php

namespace Tests\Feature\Core;

use App\Models\User;
use App\Support\StrongAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

/**
 * What the authorization server refuses (acceptance of Auftrag 41 A and B): PKCE only with S256 and for every client, who may
 * authorize, no grant without a person, a self-registered client is marked.
 */
class OAuthHardeningTest extends TestCase
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

    // ---- F1: PKCE, only S256, for every client --------------------------------------------------------------

    public function test_the_method_plain_is_refused(): void
    {
        $user = User::factory()->create();
        $client = $this->client(false);

        $location = $this->authorize($user, $client, ['code_challenge' => $this->challenge(), 'code_challenge_method' => 'plain'])
            ->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith(self::REDIRECT_URI.'?', $location);
        $this->assertStringContainsString('error=invalid_request', $location);
        $this->assertStringContainsString('state=xyz', $location);
    }

    public function test_without_a_method_plain_is_the_default_of_the_library_and_is_refused_too(): void
    {
        $location = $this->authorize(User::factory()->create(), $this->client(false), ['code_challenge' => $this->challenge()])
            ->assertRedirect()->headers->get('Location');

        $this->assertStringContainsString('error=invalid_request', $location);
    }

    public function test_a_confidential_client_needs_a_code_challenge_too(): void
    {
        $location = $this->authorize(User::factory()->create(), $this->client(true), [])
            ->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith(self::REDIRECT_URI.'?', $location);
        $this->assertStringContainsString('error=invalid_request', $location);
        $this->assertStringContainsString('code_challenge', urldecode($location));
    }

    public function test_a_public_client_without_a_code_challenge_is_refused(): void
    {
        $response = $this->authorize(User::factory()->create(), $this->client(false), [])->assertStatus(400);

        $this->assertStringContainsString('invalid_request', (string) $response->getContent());
    }

    public function test_s256_works_for_a_confidential_client_as_well(): void
    {
        $this->authorize(User::factory()->create(), $this->client(true), ['code_challenge' => $this->challenge(), 'code_challenge_method' => 'S256'])
            ->assertRedirect('https://planer.test/oauth/consent');
    }

    // ---- F2: who may authorize -----------------------------------------------------------------------------

    public function test_a_person_without_a_confirmed_address_may_not_authorize(): void
    {
        $this->authorize(User::factory()->unverified()->create(), $this->client(true), $this->s256())
            ->assertRedirect('https://planer.test/oauth/consent?error=email_unverified');
    }

    public function test_an_agent_account_may_not_authorize(): void
    {
        $this->authorize(User::factory()->agent()->create(), $this->client(true), $this->s256())
            ->assertRedirect('https://planer.test/oauth/consent?error=agent');
    }

    public function test_the_approval_of_an_admin_is_needed_for_the_scopes_that_open_the_planer(): void
    {
        $user = User::factory()->unapproved()->create();
        $this->assertNotNull($user->email_verified_at, 'confirmed, but not approved');

        foreach (['mcp:use', 'mcp:read', 'mcp:write', 'api', 'openid api', 'openid profile mcp:use'] as $scope) {
            $this->authorize($user, $this->client(true), $this->s256(), $scope)
                ->assertRedirect('https://planer.test/oauth/consent?error=not_approved');
        }
    }

    public function test_for_the_identity_alone_the_confirmed_address_is_enough(): void
    {
        $user = User::factory()->unapproved()->create();

        $this->authorize($user, $this->client(true), $this->s256(), 'openid profile email')
            ->assertRedirect('https://planer.test/oauth/consent');
    }

    public function test_an_approved_person_may_authorize_every_scope(): void
    {
        $this->authorize(User::factory()->create(), $this->client(true), $this->s256(), 'openid email mcp:use api')
            ->assertRedirect('https://planer.test/oauth/consent');
    }

    public function test_the_consent_page_is_checked_again_when_the_account_changes_meanwhile(): void
    {
        $user = User::factory()->create();
        $session = [StrongAuthentication::SESSION_KEY => (string) $user->getKey()];
        $this->authorize($user, $this->client(true), $this->s256())->assertRedirect('https://planer.test/oauth/consent');
        $token = $this->withSession($session)->actingAs($user)->getJson('/oauth/consent')->assertOk()->json('authToken');

        $user->forceFill(['email_verified_at' => null])->save();

        $this->withSession($session)->actingAs($user)->getJson('/oauth/consent')
            ->assertForbidden()->assertJsonPath('error', 'account_not_allowed')->assertJsonPath('reason', 'email_unverified');
        $this->withSession($session)->actingAs($user)->postJson('/oauth/consent/approve', ['auth_token' => $token])
            ->assertForbidden()->assertJsonPath('reason', 'email_unverified');
    }

    // ---- helpers ------------------------------------------------------------------------------------------------

    private function client(bool $confidential): Client
    {
        return app(ClientRepository::class)->createAuthorizationCodeGrantClient('Test App', [self::REDIRECT_URI], confidential: $confidential);
    }

    /** @return array{code_challenge: string, code_challenge_method: string} */
    private function s256(): array
    {
        return ['code_challenge' => $this->challenge(), 'code_challenge_method' => 'S256'];
    }

    private function challenge(): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', 'a-verifier-of-enough-length-for-pkce-0123456789', true)), '+/', '-_'), '=');
    }

    /** @param  array<string, string>  $extra */
    private function authorize(User $user, Client $client, array $extra, string $scope = 'mcp:use')
    {
        $url = '/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => $scope,
            'state' => 'xyz',
            ...$extra,
        ]);

        return $this->withSession([StrongAuthentication::SESSION_KEY => (string) $user->getKey()])->actingAs($user)->get($url);
    }
}
