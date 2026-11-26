<?php

namespace App\Rules;

use App\Core\Accounts\AgentAccounts;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A person never has an address at agent.invalid (Auftrag 58 E): that domain marks the identifier of an agent account,
 * which receives no mail. Given the account that is changed, an agent account may keep or get one.
 */
class NoAgentIdentifier implements ValidationRule
{
    public function __construct(private readonly ?User $person = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->person?->isAgent() === true || ! is_string($value)) {
            return;
        }

        if (AgentAccounts::isIdentifier($value)) {
            $fail(__('graphql.agent_identifier_reserved'));
        }
    }
}
