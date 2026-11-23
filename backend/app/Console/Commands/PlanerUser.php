<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * An interactive menu for the accounts: lists all users (admins marked) and runs the app:user-* commands on the one
 * chosen. Nothing is written here itself: every entry calls the command it stands for, with its questions and its
 * log line, so the rules stay in one place.
 */
#[Signature('planer:user')]
#[Description('Interactive: list all users (admins marked) and run the user commands on one of them')]
class PlanerUser extends Command
{
    /** The system role that makes an admin (its key in config/permissions.php). */
    private const ADMIN_ROLE = 'admin';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->components->error(__('console.planer_user.needs_interaction'));

            return self::FAILURE;
        }

        while (true) {
            $users = User::query()->orderBy('name')->orderBy('id')->get();

            $this->listUsers($users);

            $choice = (string) select(
                label: __('console.planer_user.choose'),
                options: $users->mapWithKeys(fn (User $user): array => [(string) $user->getKey() => $this->label($user)])
                    ->put('quit', __('console.planer_user.quit'))
                    ->all(),
                scroll: 15,
            );

            if ($choice === 'quit') {
                return self::SUCCESS;
            }

            $user = $users->firstWhere('id', (int) $choice);

            if ($user !== null) {
                $this->manage($user);
            }
        }
    }

    /** @param  Collection<int, User>  $users */
    private function listUsers(Collection $users): void
    {
        $this->table(
            [
                __('console.planer_user.id'),
                __('console.planer_user.name'),
                __('console.planer_user.email'),
                __('console.planer_user.admin'),
                __('console.planer_user.role'),
                __('console.planer_user.approved'),
                __('console.planer_user.agent'),
            ],
            $users->map(fn (User $user): array => [
                $user->getKey(),
                $user->name,
                $user->email,
                $this->yesNo($this->isAdmin($user)),
                $user->role?->name() ?? '-',
                $this->yesNo($user->isApproved()),
                $this->yesNo($user->isAgent()),
            ])->all(),
        );
    }

    private function manage(User $user): void
    {
        $actions = [
            'status' => __('console.planer_user.actions.status'),
            'role' => __('console.planer_user.actions.role'),
            'password' => __('console.planer_user.actions.password'),
            'email' => __('console.planer_user.actions.email'),
            'approve' => __('console.planer_user.actions.approve'),
            'two_factor' => __('console.planer_user.actions.two_factor'),
        ];

        if ($user->isAgent()) {
            $actions['token'] = __('console.planer_user.actions.token');
        }

        $actions['back'] = __('console.planer_user.back');

        $action = (string) select(
            label: __('console.planer_user.action', ['name' => $user->name]),
            options: $actions,
            scroll: count($actions),
        );

        match ($action) {
            'status' => $this->status($user),
            'role' => $this->callUserCommand('app:user-role', $user),
            'password' => $this->callUserCommand('app:user-password', $user),
            'email' => $this->changeEmail($user),
            'approve' => $this->callUserCommand('app:user-approve', $user),
            'two_factor' => $this->callUserCommand('app:user-2fa', $user),
            'token' => $this->token($user),
            default => null,
        };
    }

    private function status(User $user): void
    {
        $this->components->twoColumnDetail(__('console.planer_user.email'), (string) $user->email);
        $this->components->twoColumnDetail(__('console.planer_user.email_verified'), $this->yesNo($user->hasVerifiedEmail()));
        $this->components->twoColumnDetail(__('console.planer_user.role'), $user->role?->name() ?? '-');
        $this->components->twoColumnDetail(__('console.planer_user.admin'), $this->yesNo($this->isAdmin($user)));
        $this->components->twoColumnDetail(__('console.planer_user.approved'), $this->yesNo($user->isApproved()));
        $this->components->twoColumnDetail(__('console.planer_user.agent'), $this->yesNo($user->isAgent()));

        $this->callUserCommand('app:user-2fa', $user, ['action' => 'status']);
    }

    private function changeEmail(User $user): void
    {
        $verification = (string) select(
            label: __('console.planer_user.verification', ['email' => $user->email]),
            options: [
                'keep' => __('console.planer_user.verification_keep'),
                'verified' => __('console.planer_user.verification_verified'),
                'unverified' => __('console.planer_user.verification_unverified'),
            ],
        );

        $address = trim(text(
            label: __('console.planer_user.new_email'),
            hint: __('console.planer_user.new_email_hint'),
        ));

        $options = [];

        if ($address !== '') {
            $options['email'] = $address;
        }

        if ($verification !== 'keep') {
            $options['--'.$verification] = true;
        }

        if ($options === []) {
            $this->components->info(__('console.planer_user.nothing'));

            return;
        }

        $this->callUserCommand('app:user-email', $user, $options);
    }

    private function token(User $user): void
    {
        $mode = (string) select(
            label: __('console.planer_user.token_choose'),
            options: [
                'create' => __('console.planer_user.token_create'),
                'revoke' => __('console.planer_user.token_revoke'),
            ],
        );

        $this->callUserCommand('app:user-token', $user, $mode === 'revoke' ? ['--revoke' => true] : []);
    }

    /** Runs the command of the menu for this user; it asks for what is still missing. */
    private function callUserCommand(string $command, User $user, array $options = []): void
    {
        $this->call($command, ['user' => (string) $user->getKey()] + $options);
    }

    private function label(User $user): string
    {
        return $user->name.' <'.$user->email.'>'.($this->isAdmin($user) ? ' ('.__('console.planer_user.admin').')' : '');
    }

    private function isAdmin(User $user): bool
    {
        return $user->role?->key === self::ADMIN_ROLE;
    }

    private function yesNo(bool $value): string
    {
        return __($value ? 'console.planer_user.yes' : 'console.planer_user.no');
    }
}
