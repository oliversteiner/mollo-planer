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

    // ---- helpers ------------------------------------------------------------------------------------------------

    private function client(bool $confidential): Client
    {
        return app(ClientRepository::class)->createAuthorizationCodeGrantClient('Test App', [self::REDIRECT_URI], confidential: $confidential);
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
