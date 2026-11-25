<?php

namespace App\GraphQL\Billing;

use App\GraphQL\Concerns\ConfirmsPassword;
use App\Models\BillingSetting;
use App\Support\Billing\Iban;
use Illuminate\Validation\ValidationException;

/**
 * Sender and terms of the billing (Auftrag 39 A). Reading and changing need billing.manage (@can); changing also a
 * fresh password confirmation (the whole API already needs the strong sign-in).
 */
class BillingSettingsMutations
{
    use ConfirmsPassword;

    public function current(): BillingSetting
    {
        return BillingSetting::current();
    }

    /**
     * @param  array{input: array<string, mixed>}  $args
     *
     * @throws ValidationException
     */
    public function update(mixed $root, array $args): BillingSetting
    {
        $this->requireRecentPasswordConfirmation();

        $input = array_map(fn (mixed $value): mixed => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value, $args['input']);

        if (isset($input['iban'])) {
            $input['iban'] = Iban::normalize((string) $input['iban']);
        }

        $settings = BillingSetting::current();
        $settings->fill($input)->save();

        return $settings;
    }
}
