<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanerUserCommandTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create(['name' => 'Anna Keller', 'email' => 'anna@example.com', ...$attributes]);
    }

    /** @return array<string, string> */
    private function actions(): array
    {
        return [
            'status' => __('console.planer_user.actions.status'),
            'role' => __('console.planer_user.actions.role'),
            'password' => __('console.planer_user.actions.password'),
            'email' => __('console.planer_user.actions.email'),
            'approve' => __('console.planer_user.actions.approve'),
            'two_factor' => __('console.planer_user.actions.two_factor'),
            'back' => __('console.planer_user.back'),
        ];
    }

    public function test_the_menu_refuses_without_a_terminal_and_changes_nothing(): void
    {
        $user = $this->user(['role' => 'mitglied']);

        $this->artisan('planer:user', ['--no-interaction' => true])->assertFailed();

        $this->assertSame('mitglied', $user->fresh()->role->key);
    }

    public function test_the_menu_lists_the_users_and_marks_the_admins(): void
    {
        $admin = $this->user(['role' => 'admin', 'approved_at' => now()]);
        $bruno = $this->user(['email' => 'bruno@example.com', 'name' => 'Bruno Weber', 'role' => 'mitglied', 'approved_at' => now()]);

        $this->artisan('planer:user')
            ->expectsOutputToContain('anna@example.com')
            ->expectsOutputToContain('bruno@example.com')
            ->expectsChoice(__('console.planer_user.choose'), 'quit', [
                (string) $admin->id => 'Anna Keller <anna@example.com> ('.__('console.planer_user.admin').')',
                (string) $bruno->id => 'Bruno Weber <bruno@example.com>',
                'quit' => __('console.planer_user.quit'),
            ])
            ->assertSuccessful();
    }

    public function test_the_status_of_a_user_is_shown_and_nothing_is_written(): void
    {
        $user = $this->user(['role' => 'admin', 'approved_at' => now()]);
        $label = 'Anna Keller <anna@example.com> ('.__('console.planer_user.admin').')';

        $this->artisan('planer:user')
            ->expectsChoice(__('console.planer_user.choose'), (string) $user->id, [
                (string) $user->id => $label,
                'quit' => __('console.planer_user.quit'),
            ])
            ->expectsChoice(__('console.planer_user.action', ['name' => 'Anna Keller']), 'status', $this->actions())
            ->expectsOutputToContain('anna@example.com')
            ->expectsChoice(__('console.planer_user.choose'), 'quit', [
                (string) $user->id => $label,
                'quit' => __('console.planer_user.quit'),
            ])
            ->assertSuccessful();

        $this->assertSame('admin', $user->fresh()->role->key);
    }

    public function test_the_email_change_runs_the_user_email_command(): void
    {
        $user = $this->user(['approved_at' => now()]);

        $this->artisan('planer:user')
            ->expectsChoice(__('console.planer_user.choose'), (string) $user->id, [
                (string) $user->id => 'Anna Keller <anna@example.com>',
                'quit' => __('console.planer_user.quit'),
            ])
            ->expectsChoice(__('console.planer_user.action', ['name' => 'Anna Keller']), 'email', $this->actions())
            ->expectsChoice(__('console.planer_user.verification', ['email' => 'anna@example.com']), 'keep', [
                'keep' => __('console.planer_user.verification_keep'),
                'verified' => __('console.planer_user.verification_verified'),
                'unverified' => __('console.planer_user.verification_unverified'),
            ])
            ->expectsQuestion(__('console.planer_user.new_email'), 'neu@example.com')
            ->expectsConfirmation(__('console.user_email.confirm', ['name' => 'Anna Keller']), 'yes')
            ->expectsChoice(__('console.planer_user.choose'), 'quit', [
                (string) $user->id => 'Anna Keller <neu@example.com>',
                'quit' => __('console.planer_user.quit'),
            ])
            ->assertSuccessful();

        $this->assertSame('neu@example.com', $user->fresh()->email);
    }
}
