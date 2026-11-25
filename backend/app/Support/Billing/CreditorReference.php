<?php

namespace App\Support\Billing;

use InvalidArgumentException;

/**
 * Creditor Reference after ISO 11649 (RF, two check digits, up to 21 letters or digits), the reference type SCOR of the
 * Swiss QR bill (Auftrag 39). Made from the invoice number: everything but letters and digits is dropped.
 */
final class CreditorReference
{
    public static function fromNumber(string $number): string
    {
        $body = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $number));

        if ($body === '' || strlen($body) > 21) {
            throw new InvalidArgumentException('A creditor reference needs 1 to 21 letters or digits, got "'.$number.'".');
        }

        $check = 98 - Iban::mod97($body.'RF00');

        return 'RF'.str_pad((string) $check, 2, '0', STR_PAD_LEFT).$body;
    }

    public static function isValid(string $reference): bool
    {
        $reference = strtoupper((string) preg_replace('/\s+/', '', $reference));

        if (preg_match('/^RF\d{2}[A-Z0-9]{1,21}$/', $reference) !== 1) {
            return false;
        }

        return Iban::mod97(substr($reference, 4).substr($reference, 0, 4)) === 1;
    }

    /** Groups of four, the way it is printed. */
    public static function format(string $reference): string
    {
        return trim(chunk_split($reference, 4, ' '));
    }
}
