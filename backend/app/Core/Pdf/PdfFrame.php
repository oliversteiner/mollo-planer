<?php

namespace App\Core\Pdf;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * The PDF frame of the starter kit (Auftrag 39 C, F; 81 B; docs/starter-kit.md): HTML in, PDF out. dompdf lays out the
 * letter pages (A4, UTF-8, nothing loaded from outside, no PHP or JavaScript in the HTML) and draws header, footer and
 * page numbers; FPDI then lays every page on the letterhead, appends other PDFs (the payment part of an invoice) and
 * puts the watermark over all of them. Callers know only PdfOptions, never a PDF library. Runs within the limits of the
 * PHP of the server (memory_limit, max_execution_time); nothing here raises them.
 */
final class PdfFrame
{
    /** The font used when no other is chosen (PdfFonts). */
    public const DEFAULT_FONT = 'dejavusans';

    /** Margins in mm when the caller gives none. */
    public const DEFAULT_MARGINS = ['top' => 25, 'bottom' => 22, 'left' => 20, 'right' => 20];

    /** Distance of header and footer from the edge of the page, in mm. */
    private const EDGE = 10;

    /** Size of header, footer and page numbers, in pt. */
    private const SMALL = 8;

    /** Grey of header, footer and rule (RGB 0–1). */
    private const GREY = [0.47, 0.47, 0.47];

    private const PT_PER_MM = 72 / 25.4;

    /**
     * The PDF as bytes. $html is the body of the letter pages, or a whole HTML document (its own <style> is kept and
     * comes after the one of the frame).
     */
    public function render(string $html, PdfOptions $options): string
    {
        return $this->compose($this->letterPages($html, $options), $options);
    }

    /**
     * Margins of a letter: page 1 starts at topFirst (PdfOptions::firstPageTop), the following pages at topFollowing.
     *
     * @return array{top: float, bottom: float, left: float, right: float}
     */
    public static function marginsOf(PageLayout $layout): array
    {
        return ['top' => $layout->topFollowing, 'bottom' => $layout->bottom, 'left' => $layout->left, 'right' => $layout->right];
    }

    private function letterPages(string $html, PdfOptions $options): string
    {
        $margins = array_merge(self::DEFAULT_MARGINS, $options->margins);
        $css = sprintf('@page { margin: %smm %smm %smm %smm; }', $margins['top'], $margins['right'], $margins['bottom'], $margins['left']);

        if ($options->firstPageTop !== null) {
            $css .= sprintf(' @page :first { margin-top: %smm; }', $options->firstPageTop);
        }

        $css .= ' body { margin: 0; font-family: '.PdfFonts::css($options->font).'; }';
        $head = '<meta charset="utf-8"><style>'.$css.'</style>';
        $document = preg_match('/<head[^>]*>/i', $html) === 1
            ? (string) preg_replace('/<head[^>]*>/i', '$0'.$head, $html, 1)
            : '<!doctype html><html><head>'.$head.'</head><body>'.$html.'</body></html>';

        $dompdf = $this->dompdf();
        $dompdf->loadHtml($document, 'UTF-8');
        $dompdf->render();
        $this->decorate($dompdf->getCanvas(), $dompdf->getFontMetrics(), $options, $margins);

        return (string) $dompdf->output();
    }

    /**
     * Header (with a rule), footer and page numbers on every letter page.
     *
     * @param  array{top: float|int, bottom: float|int, left: float|int, right: float|int}  $margins
     */
    private function decorate(Canvas $canvas, FontMetrics $metrics, PdfOptions $options, array $margins): void
    {
        $font = (string) $metrics->getFont('DejaVu Sans');
        $left = $margins['left'] * self::PT_PER_MM;
        $right = (210 - $margins['right']) * self::PT_PER_MM;
        $bottom = (297 - self::EDGE) * self::PT_PER_MM - self::SMALL * 1.2;

        if ($options->header !== null) {
            $top = self::EDGE * self::PT_PER_MM;
            $canvas->page_text($left, $top, $this->fit($options->header, $metrics, $font, $right - $left), $font, self::SMALL, self::GREY);
            $canvas->page_line($left, $top + self::SMALL * 1.6, $right, $top + self::SMALL * 1.6, [0.8, 0.8, 0.8], 0.5);
        }

        $labelWidth = 0.0;

        if ($options->pageLabel !== null) {
            $label = str_replace(['{current}', '{total}'], ['{PAGE_NUM}', '{PAGE_COUNT}'], $options->pageLabel);
            $labelWidth = $metrics->getTextWidth(str_replace(['{current}', '{total}'], '99', $options->pageLabel), $font, self::SMALL);
            $canvas->page_text($right - $labelWidth, $bottom, $label, $font, self::SMALL, self::GREY);
        }

        if ($options->footer !== null) {
            $room = $right - $left - ($labelWidth > 0 ? $labelWidth + 6 * self::PT_PER_MM : 0);
            $canvas->page_text($left, $bottom, $this->fit($options->footer, $metrics, $font, $room), $font, self::SMALL, self::GREY);
        }
    }

    /** The text, shortened with an ellipsis until it fits the width (pt). */
    private function fit(string $text, FontMetrics $metrics, string $font, float $width): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        while ($text !== '' && $metrics->getTextWidth($text, $font, self::SMALL) > $width) {
            $text = rtrim(mb_substr($text, 0, -2)).'…';
        }

        return $text;
    }

    /** One empty A4 page with the watermark across it, light grey, to be laid over every page. */
    private function watermarkPage(string $text): string
    {
        $dompdf = $this->dompdf();
        $dompdf->loadHtml('<!doctype html><html><head><meta charset="utf-8"><style>@page { margin: 0; }</style></head><body></body></html>', 'UTF-8');
        $dompdf->render();
        $metrics = $dompdf->getFontMetrics();
        $canvas = $dompdf->getCanvas();
        $font = (string) $metrics->getFont('DejaVu Sans', 'bold');

        // About 180 mm along the diagonal, at most 110 pt.
        $size = min(110.0, 180 * self::PT_PER_MM / max(1.0, $metrics->getTextWidth($text, $font, 1)));
        $half = $metrics->getTextWidth($text, $font, $size) / 2 * M_SQRT1_2;
        $canvas->set_opacity(0.08);
        $canvas->text(105 * self::PT_PER_MM - $half, 148.5 * self::PT_PER_MM + $half, $text, $font, $size, [0, 0, 0], 0.0, 0.0, -45.0);

        return (string) $dompdf->output();
    }

    /** Letterhead under, watermark over each page; the appended PDFs follow without letterhead. */
    private function compose(string $letterPages, PdfOptions $options): string
    {
        $pdf = new Fpdi('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetTitle($options->title, true);
        $pdf->SetCreator((string) config('app.name'), true);

        $under = [1 => null, 2 => null];

        if ($options->letterhead !== null) {
            $pages = $pdf->setSourceFile($options->letterhead->absolutePath());
            $under[1] = $pdf->importPage(1);
            $under[2] = $pages >= 2 && $options->letterhead->pages >= 2 ? $pdf->importPage(2) : null;
        }

        $over = null;

        if ($options->watermark !== null && $options->watermark !== '') {
            $pdf->setSourceFile(StreamReader::createByString($this->watermarkPage($options->watermark)));
            $over = $pdf->importPage(1);
        }

        $count = $pdf->setSourceFile(StreamReader::createByString($letterPages));

        for ($page = 1; $page <= $count; $page++) {
            $this->place($pdf, $pdf->importPage($page), $page === 1 ? $under[1] : $under[2], $over);
        }

        foreach ($options->appendPages as $bytes) {
            $count = $pdf->setSourceFile(StreamReader::createByString($bytes));

            for ($page = 1; $page <= $count; $page++) {
                $this->place($pdf, $pdf->importPage($page), null, $over);
            }
        }

        return $pdf->Output('S');
    }

    private function place(Fpdi $pdf, string $page, ?string $under, ?string $over): void
    {
        $pdf->AddPage();

        foreach ([$under, $page, $over] as $template) {
            if ($template !== null) {
                $pdf->useTemplate($template, 0, 0, 210, 297);
            }
        }
    }

    private function dompdf(): Dompdf
    {
        $dir = storage_path('framework/cache/dompdf');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setDefaultPaperSize('a4');
        $options->setDefaultMediaType('print');
        $options->setTempDir($dir);
        $options->setFontCache($dir);
        $options->setChroot([$dir]);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');

        return $dompdf;
    }
}
