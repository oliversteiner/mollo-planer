<?php

namespace App\GraphQL\Validators\Mutation;

use App\Models\User;
use App\Rules\KeepsProjectHolders;
use Nuwave\Lighthouse\Validation\Validator;

/**
 * deleteUser: the person must not be the only one who may delete a project
 * (§7.3, docs/fix-m1-abnahme.md F3.4).
 */
class DeleteUserValidator extends Validator
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'id' => [new KeepsProjectHolders(User::find($this->arg('id')), deleting: true)],
        ];
    }
}
