<?php

namespace App\GraphQL\Validators;

use App\Rules\PostalCode;
use App\Rules\SwissIban;
use Nuwave\Lighthouse\Validation\Validator;

/**
 * Sender of the invoices (Auftrag 39 A). The fields may stay empty while the settings are filled in; an invoice is
 * refused until the required ones are there (BillingSetting::missingFields). The number format needs exactly one
 * running number {N…} and may hold the year {YYYY}.
 */
class BillingSettingsInputValidator extends Validator
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:70'],
            'street' => ['nullable', 'string', 'max:70'],
            'houseNumber' => ['nullable', 'string', 'max:16'],
            'postalCode' => ['nullable', 'string', new PostalCode('country')],
            'city' => ['nullable', 'string', 'max:35'],
            'country' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', new SwissIban],
            'uid' => ['nullable', 'string', 'max:20'],
            'paymentTermsDays' => ['required', 'integer', 'min:0', 'max:365'],
            'numberFormat' => ['required', 'string', 'max:40', 'regex:/^[^{}]*(\{YYYY\}[^{}]*)?\{N{1,8}\}[^{}]*$|^[^{}]*\{N{1,8}\}[^{}]*\{YYYY\}[^{}]*$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['numberFormat.regex' => __('billing.number_format')];
    }
}
