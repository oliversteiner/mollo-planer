<?php

namespace Tests\Feature\Core;

use App\Core\Identity\Oidc\OidcKeys;
use App\Models\User;
use App\Support\StrongAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Tests\TestCase;

/** OpenID Connect on top of the authorization server: discovery, key, id_token, userinfo. */
class OpenIdConnectTest extends TestCase
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
            'app.url' => 'https://api.planer.test',
            'app.frontend_url' => 'https://planer.test',
        ]);
    }

    public function test_the_discovery_document_describes_the_server_of_this_environment(): void
    {
        $this->getJson('/.well-known/openid-configuration')
            ->assertOk()
            ->assertJsonPath('issuer', 'https://api.planer.test')
            ->assertJsonPath('authorization_endpoint', url('/oauth/authorize'))
            ->assertJsonPath('token_endpoint', url('/oauth/token'))
            ->assertJsonPath('userinfo_endpoint', url('/oauth/userinfo'))
            ->assertJsonPath('jwks_uri', url('/.well-known/jwks.json'))
            ->assertJsonPath('code_challenge_methods_supported', ['S256'])
            ->assertJsonPath('id_token_signing_alg_values_supported', ['RS256'])
            ->assertJsonFragment(['openid']);
    }

    public function test_the_jwks_holds_the_public_key_and_nothing_private(): void
    {
        $response = $this->getJson('/.well-known/jwks.json')->assertOk();

        $key = $response->json('keys.0');
        $this->assertSame('RSA', $key['kty']);
        $this->assertSame('RS256', $key['alg']);
        $this->assertSame(OidcKeys::keyId(), $key['kid']);
        $this->assertArrayNotHasKey('d', $key);
        $this->assertArrayNotHasKey('p', $key);
    }

    public function test_the_id_token_is_signed_and_carries_the_login(): void
    {
        $user = User::factory()->create(['name' => 'Anna', 'email' => 'anna@example.com']);
        [$client, $tokens] = $this->login($user, 'openid profile email', ['pwd', 'otp'], 'n-0S6_WzA2Mj');

        $idToken = (new Parser(new JoseEncoder))->parse($tokens->json('id_token'));
        $this->assertInstanceOf(UnencryptedToken::class, $idToken);
        $this->assertTrue((new Validator)->validate($idToken, new SignedWith(new Sha256, InMemory::plainText(OidcKeys::publicPem()))));
        $this->assertSame(OidcKeys::keyId(), $idToken->headers()->get('kid'));

        $claims = $idToken->claims();
        $this->assertSame('https://api.planer.test', $claims->get('iss'));
        $this->assertSame([$client->getKey()], $claims->get('aud'));
        $this->assertSame((string) $user->getKey(), $claims->get('sub'));
        $this->assertSame('n-0S6_WzA2Mj', $claims->get('nonce'));
        $this->assertSame(['pwd', 'otp'], $claims->get('amr'));
        $this->assertEqualsWithDelta(time(), $claims->get('auth_time'), 5);
        $this->assertSame('Anna', $claims->get('name'));
        $this->assertSame('anna@example.com', $claims->get('email'));
        $this->assertTrue($claims->get('email_verified'));
        $this->assertEqualsWithDelta(time() + 3600, $claims->get('exp')->getTimestamp(), 5);
    }

    public function test_a_passkey_login_is_reported_as_such(): void
    {
        [, $tokens] = $this->login(User::factory()->create(), 'openid', ['hwk'], null);

        $claims = (new Parser(new JoseEncoder))->parse($tokens->json('id_token'))->claims();

        $this->assertSame(['hwk'], $claims->get('amr'));
        $this->assertFalse($claims->has('nonce'));
        $this->assertFalse($claims->has('email'));
    }

    public function test_without_the_scope_openid_there_is_no_id_token(): void
    {
        [, $tokens] = $this->login(User::factory()->create(), 'mcp:use', ['pwd', 'otp'], null);

        $this->assertArrayNotHasKey('id_token', $tokens->json());
        $this->assertNotEmpty($tokens->json('access_token'));
    }

    public function test_userinfo_answers_by_scope_and_only_for_openid(): void
    {
        $user = User::factory()->create(['name' => 'Anna', 'email' => 'anna@example.com']);

        [, $tokens] = $this->login($user, 'openid profile', ['pwd', 'otp'], null);
        $this->withToken($tokens->json('access_token'))->getJson('/oauth/userinfo')
            ->assertOk()
            ->assertExactJson(['sub' => (string) $user->getKey(), 'name' => 'Anna']);

        [, $without] = $this->login($user, 'mcp:use', ['pwd', 'otp'], null);
        $this->withToken($without->json('access_token'))->getJson('/oauth/userinfo')->assertForbidden();
    }

    public function test_userinfo_needs_a_token(): void
    {
        $this->getJson('/oauth/userinfo')->assertUnauthorized();
        $this->withToken('not-a-token')->getJson('/oauth/userinfo')->assertUnauthorized();
    }

    /**
     * Whole code flow with PKCE as a person who logged in with the given methods.
     *
     * @param  list<string>  $methods
     * @return array{0: Client, 1: TestResponse}
     */
    private function login(User $user, string $scope, array $methods, ?string $nonce): array
    {
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Test App', [self::REDIRECT_URI], confidential: false);
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $session = [
            StrongAuthentication::SESSION_KEY => (string) $user->getKey(),
            StrongAuthentication::SESSION_METHODS => $methods,
            StrongAuthentication::SESSION_TIME => time(),
        ];

        $this->withSession($session)->actingAs($user)->get('/oauth/authorize?'.http_build_query(array_filter([
            'client_id' => $client->getKey(),
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => $scope,
            'state' => 'xyz',
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ])))->assertRedirect('https://planer.test/oauth/consent');

        $token = $this->withSession($session)->actingAs($user)->getJson('/oauth/consent')->json('authToken');
        $redirect = $this->withSession($session)->actingAs($user)->postJson('/oauth/consent/approve', ['auth_token' => $token])->json('redirect');
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);

        $tokens = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'redirect_uri' => self::REDIRECT_URI,
            'code' => $query['code'],
            'code_verifier' => $verifier,
        ])->assertOk();

        return [$client, $tokens];
    }
}
