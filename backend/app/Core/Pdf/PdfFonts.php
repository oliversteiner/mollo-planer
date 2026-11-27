<?php

namespace App\Core\Pdf;

/**
 * The fonts for the text of letters (Auftrag 39 F, 81 B): the three that dompdf ships, nothing to upload. A stored font
 * that is no longer here (DejaVu Sans Condensed, FreeSans, FreeSerif of the time with mPDF) falls back to the default
 * when the settings are read (PdfFrameSettings::load).
 */
final class PdfFonts
{
    /** Key => name shown. The name is also the CSS family dompdf knows. */
    public const ALL = [
        'dejavusans' => 'DejaVu Sans',
        'dejavuserif' => 'DejaVu Serif',
        'dejavusansmono' => 'DejaVu Sans Mono',
    ];

    public static function isKnown(string $font): bool
    {
        return array_key_exists($font, self::ALL);
    }

    /** The CSS font-family of a key, with a generic family behind it; unknown keys give the default. */
    public static function css(string $font): string
    {
        $key = self::isKnown($font) ? $font : PdfFrame::DEFAULT_FONT;
        $generic = match ($key) {
            'dejavuserif' => 'serif',
            'dejavusansmono' => 'monospace',
            default => 'sans-serif',
        };

        return '"'.self::ALL[$key].'", '.$generic;
    }
}
