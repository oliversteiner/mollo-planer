<?php

namespace App\Core\Pdf;

use Mpdf\Mpdf;

/**
 * The PDF frame of the starter kit (Auftrag 39 C, F; docs/starter-kit.md): one place for the settings of mPDF (A4, UTF-8,
 * the temporary folder, the font, nothing loaded from outside) and the margins. Documents and invoices both start
 * from here. mPDF runs within the limits of the PHP of the server (memory_limit, max_execution_time); nothing here
 * raises them.
 */
final class PdfFrame
{
    /** A font mPDF ships with, used when no other is chosen. */
    public const DEFAULT_FONT = 'dejavusans';

    /**
     * With a letterhead its page 1 lies under page 1; a second page of it lies under every following page, without one
     * the following pages stay plain.
     *
     * @param  array{top?: float|int, bottom?: float|int, left?: float|int, right?: float|int, header?: float|int, footer?: float|int}  $margins  in mm
     */
    public function make(array $margins = [], string $font = self::DEFAULT_FONT, ?Letterhead $letterhead = null): Mpdf
    {
        $temp = storage_path('framework/cache/mpdf');

        if (! is_dir($temp)) {
            mkdir($temp, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'tempDir' => $temp,
            'default_font' => $font,
            'margin_top' => $margins['top'] ?? 25,
            'margin_bottom' => $margins['bottom'] ?? 22,
            'margin_left' => $margins['left'] ?? 20,
            'margin_right' => $margins['right'] ?? 20,
            'margin_header' => $margins['header'] ?? 10,
            'margin_footer' => $margins['footer'] ?? 10,
        ]);
        $mpdf->SetCreator((string) config('app.name'));

        if ($letterhead !== null) {
            $mpdf->SetDocTemplate($letterhead->absolutePath(), $letterhead->pages >= 2);
        }

        return $mpdf;
    }

    /** No letterhead under the pages added from now on (for example the payment part of an invoice). */
    public static function withoutLetterhead(Mpdf $mpdf): void
    {
        $mpdf->SetDocTemplate('');
    }

    /**
     * Margins of a letter: page 1 starts lower (CSS @page :first), the following pages at topFollowing.
     *
     * @return array{top: float, bottom: float, left: float, right: float, header: int, footer: int}
     */
    public static function marginsOf(PageLayout $layout): array
    {
        return ['top' => $layout->topFollowing, 'bottom' => $layout->bottom, 'left' => $layout->left, 'right' => $layout->right, 'header' => 10, 'footer' => 10];
    }

    /** The CSS that moves the start of page 1 down to topFirst. */
    public static function firstPageCss(PageLayout $layout): string
    {
        return '<style>@page { margin-top: '.$layout->topFollowing.'mm; } @page :first { margin-top: '.$layout->topFirst.'mm; }</style>';
    }
}
