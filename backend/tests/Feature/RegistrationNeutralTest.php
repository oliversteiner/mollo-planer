<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use App\Notifications\RegistrationForExistingAddress;
use App\Notifications\UserAwaitingApproval;
use App\Notifications\VerifyRegistrationEmail;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Registration is neutral (docs: Auftrag 22 O3): a taken address looks like a new one. This replaces the
 * old test that expected 422 for a taken address.
 */
class RegistrationNeutralTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-9';

    private function register(string $email, string $name = 'Eva Neu'): TestResponse
    {
        return $this->postJson('/register', ['name' => $name, 'email' => $email, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD]);
    }

    public function test_a_new_address_creates_the_account_logs_in_and_sends_the_confirmation_mail(): void
    {
        Notification::fake();

        $response = $this->register('eva@example.com')->assertCreated();

        $user = User::where('email', 'eva@example.com')->firstOrFail();
        $this->assertSame('""', $response->getContent(), 'no data of the account in the answer');
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyRegistrationEmail::class);
        Notification::assertNotSentTo($user, RegistrationForExistingAddress::class);
    }

    public function test_the_registration_counts_as_password_confirmation_for_the_first_factor_but_a_taken_address_does_not(): void
    {
        Notification::fake();
        $this->register('new@example.com')->assertCreated();
        $this->getJson('/user/confirmed-password-status')->assertOk()->assertJsonPath('confirmed', true);

        $this->post('/logout');
        User::factory()->create(['email' => 'anna@example.com']);
        $this->register('anna@example.com')->assertCreated();
        $this->assertNull(session('auth.password_confirmed_at'));
        $this->getJson('/user/confirmed-password-status')->assertUnauthorized();
    }

    public function test_a_taken_address_gets_the_same_status_and_body_creates_nothing_and_logs_nobody_in(): void
    {
        Notification::fake();
        $fresh = $this->register('new@example.com');
        $this->post('/logout');
        $owner = User::factory()->create(['email' => 'anna@example.com', 'name' => 'Anna Keller']);
        $hash = $owner->password;

        $taken = $this->register('anna@example.com', 'Somebody Else');

        $taken->assertCreated();
        $this->assertSame($fresh->getContent(), $taken->getContent());
        $this->assertGuest();
        $this->assertSame(2, User::count());
        $owner->refresh();
        $this->assertSame('Anna Keller', $owner->name);
        $this->assertSame($hash, $owner->password);
        Notification::assertSentTo($owner, RegistrationForExistingAddress::class, fn (RegistrationForExistingAddress $notification): bool => $notification->confirmed);
        Notification::assertNotSentTo($owner, VerifyRegistrationEmail::class);
        Notification::assertNotSentTo($owner, UserAwaitingApproval::class);
    }

    public function test_the_address_is_compared_without_regard_to_case_and_spaces_at_the_edge(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['email' => 'anna@example.com']);

        $this->register('  ANNA@Example.COM ')->assertCreated();

        $this->assertSame(1, User::count());
        Notification::assertSentTo($owner, RegistrationForExistingAddress::class);
    }

    public function test_an_account_that_was_never_confirmed_gets_its_own_mail_and_stays_as_it_is(): void
    {
        Notification::fake();
        $waiting = User::factory()->unverified()->unapproved()->create(['email' => 'eva@example.com', 'name' => 'Eva Neu']);
        $hash = $waiting->password;

        $this->register('eva@example.com', 'Stranger')->assertCreated();

        $waiting->refresh();
        $this->assertSame('Eva Neu', $waiting->name, 'a stranger cannot take over a registration that is still waiting');
        $this->assertSame($hash, $waiting->password);
        Notification::assertSentTo($waiting, RegistrationForExistingAddress::class, fn (RegistrationForExistingAddress $notification): bool => ! $notification->confirmed);
    }

    public function test_a_german_mail_ends_with_a_german_salutation_and_link_hint(): void
    {
        app()->setLocale('de');
        $user = User::factory()->create(['name' => 'Anna Keller']);

        $html = (string) (new RegistrationForExistingAddress(true))->locale('de')->toMail($user)->render();

        $this->assertStringContainsString('Viele Grüsse', $html);
        $this->assertStringNotContainsString('Regards', $html);
        $this->assertStringContainsString('kopiere diese Adresse in die Adresszeile deines Browsers', $html);
    }

    public function test_the_two_mails_name_the_right_links_in_both_languages(): void
    {
        $user = User::factory()->create(['name' => 'Anna Keller']);
        $frontend = config('app.frontend_url');

        foreach (['de', 'en'] as $locale) {
            app()->setLocale($locale);
            $confirmed = (new RegistrationForExistingAddress(true))->locale($locale)->toMail($user);
            $pending = (new RegistrationForExistingAddress(false))->locale($locale)->toMail($user);

            $this->assertSame("$frontend/login", $confirmed->actionUrl);
            $this->assertSame("$frontend/verify-email?status=invalid", $pending->actionUrl);
            $this->assertStringContainsString("$frontend/forgot-password", implode(' ', $confirmed->outroLines));
            $this->assertStringContainsString("$frontend/forgot-password", implode(' ', $pending->outroLines));
            $this->assertNotSame($confirmed->subject, $pending->subject);
        }
    }

    public function test_the_extra_mail_goes_out_once_an_hour_per_address_and_the_answer_stays_the_same(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['email' => 'anna@example.com']);

        $first = $this->register('anna@example.com');
        $second = $this->register('anna@example.com');

        $this->assertSame($first->getContent(), $second->getContent());
        Notification::assertSentToTimes($owner, RegistrationForExistingAddress::class, 1);

        $this->travel(61)->minutes();
        $this->register('anna@example.com')->assertCreated();
        Notification::assertSentToTimes($owner, RegistrationForExistingAddress::class, 2);
    }

    public function test_invalid_input_is_refused_with_field_errors_and_never_as_taken(): void
    {
        User::factory()->create(['email' => 'anna@example.com']);

        $this->postJson('/register', ['name' => 'A', 'email' => 'anna@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/register', ['name' => 'Eva Neu', 'email' => 'not-an-address', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/register', ['name' => 'Eva Neu', 'email' => 'anna@example.com', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/register', ['name' => 'Eva Neu', 'email' => 'anna@example.com', 'password' => self::PASSWORD, 'password_confirmation' => 'different'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_the_password_is_hashed_in_both_cases(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'anna@example.com']);
        Hash::spy();

        $this->register('anna@example.com')->assertCreated();

        Hash::shouldHaveReceived('make')->once();
    }

    public function test_two_registrations_at_once_do_not_end_in_an_error(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['email' => 'anna@example.com']);
        // The check passes (the address looked free), the insert hits the unique index: the same case as "taken".
        User::creating(function (): void {
            throw new UniqueConstraintViolationException('mariadb', 'insert into users', [], new \Exception('duplicate'));
        });
        // The owner exists, so the first lookup already finds it; drop the owner for the lookup and bring it back after.
        $this->mock(CreateNewUser::class, function ($mock) use ($owner): void {
            $mock->shouldReceive('validate')->andReturn(['name' => 'Eva', 'email' => 'racing@example.com', 'password' => self::PASSWORD]);
            $mock->shouldReceive('create')->andReturnUsing(function () use ($owner): never {
                $owner->forceFill(['email' => 'racing@example.com'])->save();

                throw new UniqueConstraintViolationException('mariadb', 'insert into users', [], new \Exception('duplicate'));
            });
        });

        $this->postJson('/register', ['name' => 'Eva', 'email' => 'racing@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertCreated();

        $this->assertGuest();
        Notification::assertSentTo($owner->fresh(), RegistrationForExistingAddress::class);
    }

    public function test_registration_stays_throttled_per_ip(): void
    {
        Notification::fake();

        foreach (range(1, 5) as $i) {
            $this->register("person$i@example.com")->assertCreated();
            $this->post('/logout');
        }

        $this->register('person6@example.com')->assertStatus(429);
    }
}
