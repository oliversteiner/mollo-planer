<?php

namespace App\GraphQL\Validators;

use App\Rules\PostalCode;
use Nuwave\Lighthouse\Validation\Validator;

/**
 * Address of a customer (Auftrag 39 A): the lengths the QR bill allows, a postal code that fits the country.
 */
class CustomerInputValidator extends Validator
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'company' => ['nullable', 'string', 'max:70'],
            'name' => ['required', 'string', 'min:2', 'max:70'],
            'street' => ['required', 'string', 'max:70'],
            'houseNumber' => ['nullable', 'string', 'max:16'],
            'postalCode' => ['required', 'string', new PostalCode('country')],
            'city' => ['required', 'string', 'max:35'],
            'country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
