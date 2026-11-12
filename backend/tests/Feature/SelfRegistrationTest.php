<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\UserAwaitingApproval;
use App\Notifications\VerifyRegistrationEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Registration with password through Fortify: the account waits for the mail
 * address and an admin's approval.
 */
class SelfRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-9';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', 'http://localhost:3000');
    }

    public function test_registration_creates_a_member_that_waits_for_mail_and_approval(): void
    {
        Notification::fake();

        $this->postJson('/register', [
            'name' => 'Eva Neu',
            'email' => 'eva@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertCreated();

        $user = User::where('email', 'eva@example.com')->firstOrFail();

        $this->assertSame(UserRole::MITGLIED, $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->isApproved());
        Notification::assertSentTo($user, VerifyRegistrationEmail::class);
    }

    public function test_registration_rejects_a_taken_mail_address(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/register', [
            'name' => 'Eva Neu',
            'email' => 'taken@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_confirming_the_mail_informs_the_admins_but_not_the_applicant(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->unapproved()->unverified()->create();

        $this->actingAs($applicant)
            ->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), [
                'id' => $applicant->id,
                'hash' => sha1($applicant->getEmailForVerification()),
            ]))
            ->assertRedirect();

        $this->assertNotNull($applicant->fresh()->email_verified_at);
        $this->assertFalse($applicant->fresh()->isApproved());
        Notification::assertSentTo($admin, UserAwaitingApproval::class);
        Notification::assertNotSentTo($applicant, UserAwaitingApproval::class);
    }

    public function test_the_confirmation_mail_is_german_for_a_german_request(): void
    {
        app()->setLocale('de');
        $user = User::factory()->unverified()->unapproved()->create(['name' => 'Eva Neu']);

        $mail = (new VerifyRegistrationEmail)->locale('de')->toMail($user);

        $this->assertSame('Bitte bestätige deine E-Mail-Adresse', $mail->subject);
        $this->assertSame('Hallo Eva Neu', $mail->greeting);
        $this->assertSame('E-Mail-Adresse bestätigen', $mail->actionText);
    }

    public function test_the_frontend_may_call_the_registration_route(): void
    {
        $this->postJson('/register', [
            'name' => 'Eva Neu',
            'email' => 'eva@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertCreated()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }

    public function test_the_rest_user_route_stays_gone(): void
    {
        $this->getJson('/api/user')->assertNotFound();
    }
}
