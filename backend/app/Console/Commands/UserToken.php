<?php

namespace App\Console\Commands;

use App\Console\Concerns\ManagesUserAccounts;
use App\Support\McpTokens;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;

/**
 * Makes or revokes the access token of an agent account for the MCP connector. An agent cannot log in to make its own
 * token; a person makes theirs in the profile, so this command refuses a person's account. The token is shown once, here
 * in the terminal, and is never written to a log or a file.
 */
#[Signature('app:user-token
    {user : E-mail address or id of the agent account}
    {name=Claude : Name of the token, shown in the list of accesses}
    {--revoke : Revoke all tokens of the account instead of making one}')]
#[Description('Make (shown once) or revoke the connector token of an agent account')]
class UserToken extends Command implements PromptsForMissingInput
{
    use ManagesUserAccounts;

    public function handle(): int
    {
        $user = $this->userOrFail();

        if ($user === null) {
            return self::FAILURE;
        }

        if (! $user->isAgent()) {
            $this->components->error(__('console.user_token.not_an_agent', ['name' => $user->name]));

            return self::FAILURE;
        }

        if (! $this->requireTerminal()) {
            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            if (! $this->confirmWrite(__('console.user_token.confirm_revoke', ['name' => $user->name]))) {
                return self::FAILURE;
            }

            $count = McpTokens::revokeAll($user);
            $this->logIntervention($user, 'token-revoke', ['tokens' => $count]);
            $this->components->info(__('console.user_token.revoked', ['count' => $count]));

            return self::SUCCESS;
        }

        if (! $this->confirmWrite(__('console.user_token.confirm', ['name' => $user->name]))) {
            return self::FAILURE;
        }

        $token = McpTokens::createForAgent($user, (string) $this->argument('name'));

        // The log gets no part of the token, only the fact.
        $this->logIntervention($user, 'token-create');
        $this->components->info(__('console.user_token.created', ['days' => McpTokens::VALID_DAYS]));
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
