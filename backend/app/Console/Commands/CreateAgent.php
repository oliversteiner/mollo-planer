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
    {email : Address of the account (a mailbox is not needed, no mail is sent to it)}')]
#[Description('Create an agent account: no login in the browser, access only by a token for the connector')]
class CreateAgent extends Command implements PromptsForMissingInput
{
    use AsksBeforeWriting;

    public function handle(AgentAccounts $accounts): int
    {
        $input = ['name' => trim((string) $this->argument('name')), 'email' => trim((string) $this->argument('email'))];
        $validator = Validator::make($input, AgentAccounts::rules());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        if (! $this->requireTerminal() || ! $this->confirmWrite(__('console.create_agent.confirm', $input))) {
            return self::FAILURE;
        }

        // The same action as the mutation createAgent; without an admin it neither logs nor mails (logged below).
        $user = $accounts->create($input['name'], $input['email']);

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
            'email' => fn (): string => text(label: __('console.create_agent.email'), required: true),
        ];
    }
}
