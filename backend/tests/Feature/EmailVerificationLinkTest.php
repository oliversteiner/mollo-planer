<?php

namespace Tests\Feature;

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Models\User;
use App\Notifications\UserAwaitingApproval;
use App\Notifications\VerifyRegistrationEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The link of the confirmation mail (docs: Auftrag 21): works without a session,
 * confirms only on the POST of the page, and every failure ends the same way.
 */
class EmailVerificationLinkTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct-horse-battery-staple';

    private function applicant(): User
    {
        return User::factory()->unapproved()->unverified()->create(['email' => 'eva@example.com']);
    }

    /** The link as the mail carries it. */
    private function link(User $user, ?\DateTimeInterface $until = null, ?string $hash = null, ?int $id = null): string
    {
        return URL::temporarySignedRoute('verification.verify', $until ?? now()->addHour(), [
            'id' => $id ?? $user->id,
            'hash' => $hash ?? sha1($user->getEmailForVerification()),
        ]);
    }

    public function test_opening_the_link_without_a_session_confirms_nothing_and_hands_over_to_the_page(): void
    {
        Event::fake([Verified::class]);
        $user = $this->applicant();
        $link = $this->link($user);

        $response = $this->get($link)->assertRedirect();

        $target = $response->headers->get('Location');
        $this->assertStringStartsWith(config('app.frontend_url').'/verify-email?', $target);
        parse_str((string) parse_url($target, PHP_URL_QUERY), $query);
        $this->assertSame(['id', 'hash', 'expires', 'signature'], array_keys($query));
        $this->assertSame((string) $user->id, $query['id']);
        $this->assertNull($user->fresh()->email_verified_at, 'a mail scanner opens links in advance');
        Event::assertNotDispatched(Verified::class);
        $this->assertGuest();
    }

    public function test_the_click_on_the_page_confirms_without_a_session_and_logs_nobody_in(): void
    {
        Event::fake([Verified::class]);
        $user = $this->applicant();

        $this->postJson($this->link($user))->assertOk()->assertExactJson(['status' => 'ok']);

        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatchedTimes(Verified::class, 1);
        $this->assertGuest();

        $confirmedAt = $user->fresh()->email_verified_at;
        $this->travel(5)->minutes();
        $this->postJson($this->link($user))->assertOk()->assertExactJson(['status' => 'already']);
        $this->assertTrue($confirmedAt->equalTo($user->fresh()->email_verified_at));
        Event::assertDispatchedTimes(Verified::class, 1);
    }

    public function test_every_failure_looks_the_same_and_confirms_nothing(): void
    {
        $user = $this->applicant();

        // Signature wrong or expired: the GET already says so.
        $cases = [
            'expired' => $this->link($user, now()->subMinute()),
            'signature changed' => preg_replace('/signature=(.)/', 'signature=x$1', $this->link($user)),
        ];

        foreach ($cases as $name => $link) {
            $this->get($link)->assertRedirect(config('app.frontend_url').'/verify-email?status=invalid');
            $this->postJson($link)->assertOk()->assertExactJson(['status' => 'invalid']);
            $this->assertNull($user->fresh()->email_verified_at, $name);
        }

        // Signed correctly but for an account or hash that does not match: the GET reads nothing,
        // so it hands over; the POST of the page answers "invalid", the same as above.
        $cases = [
            'wrong hash' => $this->link($user, null, sha1('someone-else@example.com')),
            'unknown id' => $this->link($user, null, null, $user->id + 1000),
            'id of another account' => $this->link($user, null, null, User::factory()->create()->id),
        ];

        foreach ($cases as $name => $link) {
            $this->get($link)->assertRedirectContains('/verify-email?id=');
            $this->postJson($link)->assertOk()->assertExactJson(['status' => 'invalid']);
            $this->assertNull($user->fresh()->email_verified_at, $name);
        }
    }

    public function test_the_route_of_fortify_does_not_hide_ours(): void
    {
        $this->assertSame(EmailVerificationController::class.'@show', Route::getRoutes()->getByName('verification.verify')->getActionName());
        $this->assertCount(1, collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => $route->uri() === 'email/verify/{id}/{hash}' && in_array('GET', $route->methods(), true)));

        $user = $this->applicant();
        $origin = config('app.frontend_url');

        $this->get($this->link($user))->assertRedirect()->assertDontSee('login');
        $this->withHeaders(['Origin' => $origin])->postJson($this->link($user))
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', $origin);
    }

    public function test_the_signature_of_the_get_link_passes_the_post_check_too(): void
    {
        // Laravel's signature covers path and query, not the method.
        $user = $this->applicant();
        $request = Request::create($this->link($user), 'POST');

        $this->assertTrue(URL::hasValidSignature($request));
    }

    public function test_the_mail_after_an_email_change_in_the_profile_confirms_in_the_same_way(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com']);

        app(UpdateUserProfileInformation::class)->update($user, ['name' => $user->name, 'email' => 'new@example.com']);

        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user, VerifyRegistrationEmail::class, function (VerifyRegistrationEmail $notification) use ($user): bool {
            $this->postJson($notification->toMail($user->fresh())->actionUrl)->assertOk()->assertJsonPath('status', 'ok');

            return true;
        });
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_the_confirmation_informs_the_admins(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $user = $this->applicant();

        $this->postJson($this->link($user))->assertJsonPath('status', 'ok');

        Notification::assertSentTo($admin, UserAwaitingApproval::class);
    }

    public function test_the_mail_is_queued_and_the_registration_does_not_wait_for_it(): void
    {
        Queue::fake();

        $this->postJson('/register', [
            'name' => 'Eva Neu', 'email' => 'eva.neu@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
        ])->assertCreated();

        Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof VerifyRegistrationEmail);
    }

    public function test_the_link_starts_from_the_configured_url_and_the_signature_holds_there(): void
    {
        config(['app.url' => 'https://api.example.test']);
        $user = $this->applicant();

        $url = (new VerifyRegistrationEmail)->toMail($user)->actionUrl;

        $this->assertStringStartsWith('https://api.example.test/email/verify/'.$user->id.'/', $url);
        $this->get($url)->assertRedirect()->assertRedirectContains('/verify-email?id=');
    }

    public function test_the_link_is_throttled_per_ip(): void
    {
        $user = $this->applicant();
        $link = $this->link($user);

        foreach (range(1, 20) as $i) {
            $this->get($link)->assertRedirect();
        }

        $this->get($link)->assertStatus(429);
        $this->postJson($link)->assertStatus(429);
    }

    public function test_the_mail_names_the_person_the_expiry_and_the_warning(): void
    {
        config(['auth.verification.expire' => 45]);
        app()->setLocale('de');
        $user = $this->applicant();
        $user->forceFill(['name' => 'Eva Neu'])->save();

        $mail = (new VerifyRegistrationEmail)->locale('de')->toMail($user);

        $this->assertSame('Hallo Eva Neu', $mail->greeting);
        $this->assertContains('Der Link ist 45 Minuten gültig.', $mail->outroLines);
        $this->assertContains('Wenn du dich nicht registriert hast, klick nicht auf den Link und tu nichts.', $mail->outroLines);
    }

    public function test_sending_the_mail_again_when_logged_in_and_the_throttle(): void
    {
        Notification::fake();
        $user = $this->applicant();

        $this->actingAs($user)->postJson('/email/verification-notification')->assertStatus(202);
        Notification::assertSentToTimes($user, VerifyRegistrationEmail::class, 1);

        $this->actingAs($user)->postJson('/email/verification-notification')->assertStatus(429);
        Notification::assertSentToTimes($user, VerifyRegistrationEmail::class, 1);
    }

    public function test_sending_again_says_204_when_the_address_is_confirmed_meanwhile(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/email/verification-notification')->assertNoContent();

        Notification::assertNothingSent();
    }

    public function test_sending_again_needs_a_session(): void
    {
        $this->postJson('/email/verification-notification')->assertUnauthorized();
    }

    public function test_sending_again_without_a_session_answers_every_address_alike(): void
    {
        Notification::fake();
        $waiting = $this->applicant();
        $confirmed = User::factory()->create(['email' => 'done@example.com']);
        $answers = [];

        foreach (['eva@example.com', 'DONE@example.com', 'nobody@example.com'] as $email) {
            $response = $this->postJson('/email/verification-notification/guest', ['email' => $email])->assertStatus(202);
            $answers[] = $response->getContent();
        }

        $this->assertCount(1, array_unique($answers));
        Notification::assertSentToTimes($waiting, VerifyRegistrationEmail::class, 1);
        Notification::assertNotSentTo($confirmed, VerifyRegistrationEmail::class);
        Notification::assertCount(1);
    }

    public function test_sending_again_without_a_session_is_throttled_per_address_and_per_ip(): void
    {
        Notification::fake();
        $this->applicant();

        foreach (range(1, 3) as $i) {
            $this->postJson('/email/verification-notification/guest', ['email' => 'eva@example.com'])->assertStatus(202);
        }
        $this->postJson('/email/verification-notification/guest', ['email' => 'eva@example.com'])->assertStatus(429);
        // The address is not the only key: the same IP asking for others runs into the limit as well.
        $this->postJson('/email/verification-notification/guest', ['email' => 'other@example.com'])->assertStatus(429);
        Notification::assertCount(3);
    }

    public function test_an_invalid_address_is_refused_with_a_field_error(): void
    {
        $this->postJson('/email/verification-notification/guest', ['email' => 'not-an-address'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }
}
