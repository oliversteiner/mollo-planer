<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Search without case and accents, the counterpart of the frontend utils/text-search.ts.
 */
final class TextSearch
{
    /** Lower case and without accents, so "Müller" finds "muller". */
    public static function normalize(string $text): string
    {
        return mb_strtolower(Str::ascii($text));
    }
}
