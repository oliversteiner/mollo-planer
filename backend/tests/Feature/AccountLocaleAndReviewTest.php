<?php

namespace Tests\Feature;

use App\Actions\ReviewRegistration;
use App\Enums\Locale;
use App\Models\User;
use App\Notifications\AccountApproved;
use App\Notifications\AccountRejected;
use App\Services\UserInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * users.locale and the mail about the release or rejection of a registration (docs: Auftrag 22 O8).
 */
class AccountLocaleAndReviewTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-9';

    public function test_the_column_is_there_nullable_and_not_fillable(): void
    {
        $column = collect(Schema::getColumns('users'))->firstWhere('name', 'locale');

        $this->assertNotNull($column);
        $this->assertTrue($column['nullable']);
        $this->assertFalse((new User)->isFillable('locale'), 'only registration and invitation set it');
    }

    public function test_registration_stores_the_language_of_the_request(): void
    {
        $this->withHeader('Accept-Language', 'en')->postJson('/register', ['name' => 'Eva Neu', 'email' => 'eva@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertCreated();
        $this->withHeader('Accept-Language', 'de')->postJson('/register', ['name' => 'Ben Neu', 'email' => 'ben@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertCreated();

        $this->assertSame('en', User::where('email', 'eva@example.com')->value('locale'));
        $this->assertSame('de', User::where('email', 'ben@example.com')->value('locale'));
    }

    public function test_the_invitation_stores_the_language_it_is_sent_in_and_accounts_on_the_command_line_have_none(): void
    {
        Notification::fake();

        $invited = app(UserInvitation::class)->invite('Fritz', 'fritz@example.com', null, Locale::EN);
        $this->assertSame('en', $invited->fresh()->locale);

        $this->artisan('app:create-admin', ['name' => 'Ada Admin', 'email' => 'ada@example.com', '--password' => true])
            ->expectsQuestion(__('console.create_admin.password'), 'correct-horse-battery-staple')
            ->expectsQuestion(__('console.create_admin.password_confirmation'), 'correct-horse-battery-staple')
            ->assertSuccessful();
        $this->assertNull(User::where('email', 'ada@example.com')->value('locale'));
    }

    public function test_the_release_mail_comes_in_the_language_of_the_person_not_of_the_admin(): void
    {
        Notification::fake();
        config(['app.default_locale' => 'de']);
        $english = User::factory()->unapproved()->create();
        $english->forceFill(['locale' => 'en'])->save();
        $unknown = User::factory()->unapproved()->create();
        app()->setLocale('en');

        app(ReviewRegistration::class)->approve($english);
        app(ReviewRegistration::class)->approve($unknown);

        Notification::assertSentTo($english, AccountApproved::class, fn (AccountApproved $notification): bool => $notification->locale === 'en');
        Notification::assertSentTo($unknown, AccountApproved::class, fn (AccountApproved $notification): bool => $notification->locale === 'de');
        $this->assertTrue($english->fresh()->isApproved());
    }

    public function test_the_release_mail_says_what_is_asked_and_links_to_the_sign_in(): void
    {
        $user = User::factory()->unapproved()->create(['name' => 'Eva Neu']);
        $frontend = config('app.frontend_url');

        app()->setLocale('de');
        $mail = (new AccountApproved)->locale('de')->toMail($user);
        $this->assertSame('Hallo Eva Neu', $mail->greeting);
        $this->assertContains('Dein Konto ist freigegeben. Du kannst dich jetzt anmelden.', $mail->introLines);
        $this->assertSame('Anmelden', $mail->actionText);
        $this->assertSame("$frontend/login", $mail->actionUrl);
        $this->assertInstanceOf(ShouldQueue::class, new AccountApproved);
    }

    public function test_a_second_release_changes_nothing_and_sends_no_second_mail(): void
    {
        Notification::fake();
        $user = User::factory()->unapproved()->create();

        app(ReviewRegistration::class)->approve($user);
        app(ReviewRegistration::class)->approve($user->fresh());

        Notification::assertSentToTimes($user, AccountApproved::class, 1);
    }

    public function test_the_rejection_mail_goes_out_although_the_account_is_gone_afterwards(): void
    {
        $user = User::factory()->unapproved()->create(['name' => 'Eva Neu', 'email' => 'eva@example.com']);
        $user->forceFill(['locale' => 'en'])->save();

        // The queue runs at once in tests: the mail is built and sent from the data in the notification.
        app(ReviewRegistration::class)->reject($user);

        $this->assertDatabaseMissing('users', ['email' => 'eva@example.com']);
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $sent = $messages->first()->getOriginalMessage();
        $this->assertSame('eva@example.com', $sent->getTo()[0]->getAddress());
        $this->assertStringContainsString('Hello Eva Neu', $sent->getTextBody());
        $this->assertStringContainsString('If you have questions, contact the person who knows you.', $sent->getTextBody());
    }

    public function test_the_rejection_mail_has_no_reason_no_link_and_no_admin(): void
    {
        $user = User::factory()->unapproved()->create(['name' => 'Eva Neu']);

        app()->setLocale('de');
        $mail = (new AccountRejected('Eva Neu'))->locale('de')->toMail($user);

        $this->assertSame('Hallo Eva Neu', $mail->greeting);
        $this->assertNull($mail->actionUrl);
        $this->assertContains('Bei Fragen melde dich bei deiner Kontaktperson.', $mail->introLines);
        $this->assertInstanceOf(ShouldQueue::class, new AccountRejected('x'));
    }
}
