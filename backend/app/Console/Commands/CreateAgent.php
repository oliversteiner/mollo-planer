<?php

namespace App\Console\Commands;

use App\Console\Concerns\AsksBeforeWriting;
use App\Core\Accounts\AgentAccounts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\text;

/**
 * Creates an agent account (Claude, a person's assistant) through AgentAccounts, like the mutation createAgent: system role Mitglied, never an admin, a random password
 * nobody knows, verified and approved at once. It has no web access (RefuseAgentLogin, EnsureNotAgent); the way in is
 * a token for the connector, made with app:user-token. Add it to a project in the app, with the project role Agent.
 */
#[Signature('app:create-agent
    {name : Name shown in the app, for example Claude Club DB}
    {email? : Identifier of the account (optional; without it: <name>@agent.invalid, no mail is sent to it)}')]
#[Description('Create an agent account: no login in the browser, access only by a token for the connector')]
class CreateAgent extends Command implements PromptsForMissingInput
{
    use AsksBeforeWriting;

    public function handle(AgentAccounts $accounts): int
    {
        $name = trim((string) $this->argument('name'));
        $email = trim((string) $this->argument('email'));
        $validator = Validator::make(['name' => $name, 'email' => $email === '' ? null : $email], AgentAccounts::rules());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        // Shown before writing; the identifier made from the name is checked again when the account is made.
        $input = ['name' => $name, 'email' => $email === '' ? AgentAccounts::identifierFor($name) : $email];

        if (! $this->requireTerminal() || ! $this->confirmWrite(__('console.create_agent.confirm', $input))) {
            return self::FAILURE;
        }

        // The same action as the mutation createAgent; without an admin it neither logs nor mails (logged below).
        $user = $accounts->create($name, $email === '' ? null : $email);

        $this->logIntervention($user, 'create-agent');
        $this->components->info(__('console.create_agent.created', ['name' => $user->name]));

        return self::SUCCESS;
    }

    /**
     * @return array<string, \Closure(): string>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => fn (): string => text(label: __('console.create_agent.name'), required: true),
        ];
    }
}
