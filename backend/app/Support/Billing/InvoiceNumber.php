<?php

namespace App\Support\Billing;

/**
 * The format of an invoice number (Auftrag 39 A, B): {YYYY} is the year of the issue date, {N…} the running number with
 * at least as many digits as there are N (more when it grows). Without {YYYY} the numbers run on over the years.
 */
final class InvoiceNumber
{
    /** The key of the number range: the year, or 0 when the format has no year. */
    public static function rangeOf(string $format, int $year): int
    {
        return str_contains($format, '{YYYY}') ? $year : 0;
    }

    public static function make(string $format, int $year, int $running): string
    {
        $number = str_replace('{YYYY}', (string) $year, $format);

        return (string) preg_replace_callback('/\{(N+)\}/', fn (array $match): string => str_pad((string) $running, strlen($match[1]), '0', STR_PAD_LEFT), $number);
    }
}
