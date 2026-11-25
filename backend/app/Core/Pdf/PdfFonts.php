<?php

namespace App\Core\Pdf;

/**
 * The fonts for the text of letters (Auftrag 39 F): a few well readable ones that mPDF ships, nothing to upload.
 */
final class PdfFonts
{
    /** mPDF name => name shown. */
    public const ALL = [
        'dejavusans' => 'DejaVu Sans',
        'dejavusanscondensed' => 'DejaVu Sans Condensed',
        'dejavuserif' => 'DejaVu Serif',
        'freesans' => 'FreeSans',
        'freeserif' => 'FreeSerif',
    ];

    public static function isKnown(string $font): bool
    {
        return array_key_exists($font, self::ALL);
    }
}
