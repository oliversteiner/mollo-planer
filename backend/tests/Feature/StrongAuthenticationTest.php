<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureStrongAuthentication;
use App\Models\User;
use App\Support\StrongAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Nuwave\Lighthouse\Http\Middleware\AttemptAuthentication;
use Tests\TestCase;

class StrongAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // A plain route behind the same lock, independent of the schema.
        Route::middleware(['web', EnsureStrongAuthentication::class])
            ->get('/_test/strong', fn () => 'ok');
    }

    public function test_graphql_route_uses_sanctum_and_the_lock_after_authentication(): void
    {
        $middleware = config('lighthouse.route.middleware');

        $this->assertSame(['sanctum'], config('lighthouse.guards'));
        $this->assertContains(EnsureFrontendRequestsAreStateful::class, $middleware);
        $this->assertGreaterThan(
            array_search(AttemptAuthentication::class, $middleware, true),
            array_search(EnsureStrongAuthentication::class, $middleware, true),
        );
    }

    public function test_query_limits_are_set(): void
    {
        $this->assertGreaterThan(0, config('lighthouse.security.max_query_depth'));
        $this->assertGreaterThan(0, config('lighthouse.security.max_query_complexity'));
    }

    public function test_user_without_any_factor_is_sent_to_setup(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/graphql', ['query' => '{ me { id } }'])
            ->assertForbidden()
            ->assertJsonPath('errors.0.extensions.code', EnsureStrongAuthentication::CODE_SETUP_REQUIRED);
    }

    public function test_user_with_authenticator_app_must_use_it(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', ['query' => '{ me { id } }'])
            ->assertForbidden()
            ->assertJsonPath('errors.0.extensions.code', EnsureStrongAuthentication::CODE_STRONG_AUTH_REQUIRED);
    }

    public function test_user_with_passkey_must_use_it(): void
    {
        $user = User::factory()->create();
        DB::table('passkeys')->insert([
            'user_id' => $user->id,
            'name' => 'Laptop',
            'credential_id' => 'test-credential',
            'credential' => json_encode([]),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', ['query' => '{ me { id } }'])
            ->assertForbidden()
            ->assertJsonPath('errors.0.extensions.code', EnsureStrongAuthentication::CODE_STRONG_AUTH_REQUIRED);
    }

    public function test_strongly_authenticated_session_passes(): void
    {
        $this->actingAs(User::factory()->create(['two_factor_confirmed_at' => now()]))
            ->withSession([StrongAuthentication::SESSION_KEY => true])
            ->get('/_test/strong')
            ->assertOk();
    }

    public function test_session_without_mark_is_blocked(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/_test/strong')
            ->assertForbidden();
    }

    public function test_guests_pass_the_lock(): void
    {
        $this->get('/_test/strong')->assertOk();
    }

    public function test_error_message_is_translated(): void
    {
        foreach (['de', 'en'] as $locale) {
            app()->setLocale($locale);

            foreach ([EnsureStrongAuthentication::CODE_SETUP_REQUIRED, EnsureStrongAuthentication::CODE_STRONG_AUTH_REQUIRED] as $code) {
                $key = 'graphql.'.strtolower($code);
                $this->assertNotSame($key, __($key), "{$locale}: {$key}");
            }
        }
    }
}
