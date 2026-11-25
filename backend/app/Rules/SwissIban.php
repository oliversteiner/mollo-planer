<?php

namespace App\Rules;

use App\Support\Billing\Iban;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A normal IBAN of Switzerland or Liechtenstein with valid check digits, not a QR-IBAN (Auftrag 39 A).
 */
class SwissIban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $message = match (true) {
            ! Iban::isSwiss($value) => 'billing.iban_country',
            ! Iban::hasValidChecksum($value) => 'billing.iban_checksum',
            Iban::isQrIban($value) => 'billing.iban_qr',
            default => null,
        };

        if ($message !== null) {
            $fail(__($message));
        }
    }
}
