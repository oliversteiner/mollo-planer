<?php

namespace App\Console\Commands;

use App\Enums\Locale;
use App\Enums\UserRole;
use App\Services\UserInvitation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates an admin, for example the very first one on a new installation.
 * By default the admin gets an invitation mail to set their password;
 * with --password the password is entered here instead (no mail needed).
 * No password ever comes from the command line or the code.
 */
#[Signature('app:create-admin
    {name : Full name of the admin}
    {email : Email address, used to log in}
    {--password : Enter the password now instead of sending an invitation mail}
    {--locale= : Language of the invitation mail (de or en), default APP_LOCALE}')]
#[Description('Create an admin user and send an invitation mail (or set the password interactively)')]
class CreateAdmin extends Command implements PromptsForMissingInput
{
    public function handle(UserInvitation $invitation): int
    {
        $locale = Locale::tryFrom((string) ($this->option('locale') ?? config('app.locale')));

        if ($locale === null) {
            $this->components->error(__('console.create_admin.invalid_locale'));

            return self::FAILURE;
        }

        $input = ['name' => trim((string) $this->argument('name')), 'email' => trim((string) $this->argument('email'))];
        $validator = Validator::make($input, $this->rules());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        if (! $this->option('password')) {
            $invitation->invite($input['name'], $input['email'], UserRole::ADMIN, $locale);
            $this->components->info(__('console.create_admin.invited', ['email' => $input['email']]));

            return self::SUCCESS;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error(__('console.create_admin.password_needs_interaction'));

            return self::FAILURE;
        }

        $invitation->create($input['name'], $input['email'], UserRole::ADMIN, $this->askForPassword());
        $this->components->info(__('console.create_admin.created', ['email' => $input['email']]));

        return self::SUCCESS;
    }

    /**
     * Same rules as CreateUserInput in the GraphQL schema.
     *
     * @return array<string, list<string>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
        ];
    }

    private function askForPassword(): string
    {
        $password = password(
            label: __('console.create_admin.password'),
            required: true,
            validate: ['password' => Password::default()],
        );

        password(
            label: __('console.create_admin.password_confirmation'),
            required: true,
            validate: fn (string $value): ?string => $value === $password ? null : __('console.create_admin.password_mismatch'),
        );

        return $password;
    }

    /**
     * @return array<string, \Closure(): string>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => fn (): string => text(label: __('console.create_admin.name'), required: true),
            'email' => fn (): string => text(label: __('console.create_admin.email'), required: true),
        ];
    }
}
