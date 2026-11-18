<?php

namespace Tests\Feature\GraphQL;

use App\Notifications\SecurityChange;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * The reset of the second factor by an admin tells the person (docs: Auftrag 22 O7): by mail, in their own
 * language, and the log names who did it. Both ways (mutation, artisan command) do the same.
 */
class ResetUserTwoFactorMailTest extends GraphQLTestCase
{
    private const MUTATION = 'mutation ($id: ID!) { resetUserTwoFactor(id: $id) { id } }';

    public function test_the_mutation_sends_the_mail_to_the_person_in_their_language_and_logs_the_admin_without_an_address(): void
    {
        Notification::fake();
        Log::spy();
        $member = $this->users['member'];
        $admin = $this->users['admin'];

        // The admin asks in German, the person has no stored language: the application's (never the admin's request).
        $this->as('admin')->withHeader('Accept-Language', 'de')->gql(self::MUTATION, ['id' => $member->id])->assertJsonMissingPath('errors');

        Notification::assertSentToTimes($member, SecurityChange::class, 1);
        Notification::assertSentTo($member, SecurityChange::class, fn (SecurityChange $notification): bool => $notification->kind === SecurityChange::ADMIN_RESET && $notification->locale === $member->mailLocale());
        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) use ($member, $admin): bool {
            $text = json_encode($context);

            return $context['user_id'] === $member->id
                && $context['by'] === (string) $admin->id
                && ! str_contains($text, $member->email)
                && ! str_contains($text, $admin->email);
        });
    }

    public function test_the_mail_says_what_the_task_asks_in_both_languages(): void
    {
        $member = $this->users['member'];

        app()->setLocale('de');
        $de = (new SecurityChange(SecurityChange::ADMIN_RESET))->locale('de')->toMail($member);
        app()->setLocale('en');
        $en = (new SecurityChange(SecurityChange::ADMIN_RESET))->locale('en')->toMail($member);

        $this->assertContains('Dein zweiter Faktor wurde von einem Admin zurückgesetzt. Du richtest bei der nächsten Anmeldung einen neuen ein.', $de->introLines);
        $this->assertContains('Warst du das nicht, melde dich sofort.', $de->introLines);
        $this->assertContains('Your second factor was reset by an admin. You set up a new one the next time you sign in.', $en->introLines);
        $this->assertContains('If this was not you, get in touch right away.', $en->introLines);
    }

    public function test_the_artisan_command_sends_the_same_mail_and_logs_the_console(): void
    {
        Notification::fake();
        Log::spy();
        $member = $this->users['member'];

        $this->artisan('app:user-2fa', ['user' => $member->email, 'action' => 'reset'])
            ->expectsConfirmation(__('console.user_2fa.confirm_reset', ['name' => $member->name]), 'yes')
            ->assertSuccessful();

        Notification::assertSentToTimes($member, SecurityChange::class, 1);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $context['by'] === 'console' && $context['user_id'] === $member->id);
    }
}
