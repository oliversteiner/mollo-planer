<?php

namespace App\Support\Billing;

/**
 * IBAN of the sender (Auftrag 39 A). A normal IBAN of Switzerland or Liechtenstein (the QR bill pays only to those),
 * checked with its check digits (ISO 13616, mod 97). A QR-IBAN (institution id 30000–31999) is refused: it needs a QR
 * reference, the invoices use a creditor reference (SCOR).
 */
final class Iban
{
    /** Spaces out, upper case. */
    public static function normalize(string $iban): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $iban));
    }

    /** Groups of four, the way it is printed. */
    public static function format(string $iban): string
    {
        return trim(chunk_split(self::normalize($iban), 4, ' '));
    }

    public static function hasValidChecksum(string $iban): bool
    {
        $iban = self::normalize($iban);

        if (preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{1,30}$/', $iban) !== 1) {
            return false;
        }

        return self::mod97(substr($iban, 4).substr($iban, 0, 4)) === 1;
    }

    /** Swiss or Liechtenstein IBAN: 21 characters, the account part digits and letters. */
    public static function isSwiss(string $iban): bool
    {
        return preg_match('/^(CH|LI)\d{2}\d{5}[A-Z0-9]{12}$/', self::normalize($iban)) === 1;
    }

    /** The institution id 30000–31999 marks a QR-IBAN. */
    public static function isQrIban(string $iban): bool
    {
        $institution = (int) substr(self::normalize($iban), 4, 5);

        return $institution >= 30000 && $institution <= 31999;
    }

    /**
     * Remainder of a number given as letters and digits (A = 10 … Z = 35) divided by 97, piece by piece so no integer
     * overflows. Shared with the creditor reference (ISO 11649).
     */
    public static function mod97(string $value): int
    {
        $digits = '';

        foreach (str_split($value) as $char) {
            $digits .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        $remainder = 0;

        foreach (str_split($digits, 7) as $chunk) {
            $remainder = ((int) ($remainder.$chunk)) % 97;
        }

        return $remainder;
    }
}
