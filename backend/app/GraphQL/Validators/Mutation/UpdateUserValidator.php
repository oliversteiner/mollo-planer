<?php

namespace App\GraphQL\Validators\Mutation;

use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

/**
 * updateUser: a changed e-mail address must not belong to someone else.
 */
class UpdateUserValidator extends Validator
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'input.email' => [Rule::unique('users', 'email')->ignore($this->arg('id'))],
        ];
    }
}
