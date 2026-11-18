<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * "Forgot password" is neutral (docs: Auftrag 22 O1): the answer does not tell which addresses exist.
 */
class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_and_unknown_addresses_get_the_same_answer_and_only_the_known_one_gets_a_mail(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'anna@example.com']);

        $known = $this->postJson('/forgot-password', ['email' => 'anna@example.com']);
        $unknown = $this->postJson('/forgot-password', ['email' => 'nobody@example.com']);

        $known->assertOk();
        $unknown->assertOk();
        $this->assertSame($known->getContent(), $unknown->getContent());
        $this->assertSame(__('passwords.sent'), $unknown->json('message'));
        Notification::assertSentTo($user, ResetPasswordNotification::class);
        Notification::assertCount(1);
    }

    public function test_the_brokers_throttle_of_a_known_address_gives_the_same_answer_too(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'anna@example.com']);

        $first = $this->postJson('/forgot-password', ['email' => 'anna@example.com']);
        $second = $this->postJson('/forgot-password', ['email' => 'anna@example.com']);

        $second->assertOk();
        $this->assertSame($first->getContent(), $second->getContent());
        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
    }

    public function test_the_address_is_found_in_any_case(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'anna@example.com']);

        $this->postJson('/forgot-password', ['email' => 'ANNA@Example.com'])->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_a_formally_invalid_address_is_refused(): void
    {
        $this->postJson('/forgot-password', ['email' => 'not-an-address'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
        $this->postJson('/forgot-password', [])->assertUnprocessable();
    }

    public function test_the_text_is_neutral_in_both_languages(): void
    {
        foreach (['de', 'en'] as $locale) {
            $text = __('passwords.sent', [], $locale);

            $this->assertStringContainsString($locale === 'de' ? 'Wenn die Adresse' : 'If the address', $text);
        }
    }

    public function test_forgot_password_is_throttled_per_ip(): void
    {
        Notification::fake();

        foreach (range(1, 5) as $i) {
            $this->postJson('/forgot-password', ['email' => "person$i@example.com"])->assertOk();
        }

        $this->postJson('/forgot-password', ['email' => 'person6@example.com'])->assertStatus(429);
    }

    public function test_reset_password_is_throttled_per_ip(): void
    {
        foreach (range(1, 5) as $i) {
            $this->postJson('/reset-password', ['token' => 'x', 'email' => "person$i@example.com", 'password' => 'a', 'password_confirmation' => 'a'])->assertStatus(422);
        }

        $this->postJson('/reset-password', ['token' => 'x', 'email' => 'person6@example.com', 'password' => 'a', 'password_confirmation' => 'a'])->assertStatus(429);
    }
}
