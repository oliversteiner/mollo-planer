<?php

namespace App\GraphQL\Validators\Mutation;

use App\Models\User;
use App\Rules\KeepsProjectHolders;
use App\Rules\NoAgentIdentifier;
use App\Rules\NoBillingForAgents;
use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

/**
 * updateUser: a changed e-mail address must not belong to someone else, and
 * a new system role must not leave a project without a holder (§7.3), and an agent account never gets a role with
 * billing.manage (Auftrag 39).
 */
class UpdateUserValidator extends Validator
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'input.email' => [Rule::unique('users', 'email')->ignore($this->arg('id')), new NoAgentIdentifier(User::find($this->arg('id')))],
            'input.role' => [new KeepsProjectHolders(User::find($this->arg('id'))), new NoBillingForAgents(User::find($this->arg('id')))],
        ];
    }
}
