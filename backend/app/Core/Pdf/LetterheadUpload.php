<?php

namespace App\Core\Pdf;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Throwable;

/**
 * Checks and keeps an uploaded letterhead (Auftrag 39 F): a PDF of at most 5 MB, one or two pages, A4 portrait, and
 * readable by FPDI, the way mPDF will read it. Nothing is converted on the server. The file goes to the private disk;
 * the one it replaces is removed (PDFs made before keep their own copy of the look).
 */
final class LetterheadUpload
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** Allowed difference to 210 × 297 mm. */
    private const TOLERANCE_MM = 1.0;

    /**
     * @throws LetterheadRejected
     */
    public function store(UploadedFile $file): Letterhead
    {
        $path = $file->getRealPath();

        if ($path === false || ! str_starts_with((string) file_get_contents($path, length: 5), '%PDF-')) {
            throw new LetterheadRejected(LetterheadRejected::NOT_PDF);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new LetterheadRejected(LetterheadRejected::TOO_LARGE);
        }

        $pages = self::check($path);

        $stored = 'pdf-frame/letterhead-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6)).'.pdf';
        Storage::disk('local')->putFileAs(dirname($stored), $file, basename($stored));

        $settings = PdfFrameSettings::load();
        $old = $settings->letterhead;
        $letterhead = new Letterhead($stored, $pages, mb_substr($file->getClientOriginalName(), 0, 120), now()->toDateTimeString());
        $settings->with(letterhead: $letterhead)->save();

        if ($old !== null && $old->path !== $stored) {
            Storage::disk('local')->delete($old->path);
        }

        return $letterhead;
    }

    public function remove(): void
    {
        $settings = PdfFrameSettings::load();

        if ($settings->letterhead !== null) {
            Storage::disk('local')->delete($settings->letterhead->path);
        }

        $settings->with(removeLetterhead: true)->save();
    }

    /**
     * Number of pages, after the checks of format and readability.
     *
     * @throws LetterheadRejected
     */
    public static function check(string $path): int
    {
        try {
            $mpdf = new Mpdf(['tempDir' => storage_path('framework/cache/mpdf')]);
            $pages = $mpdf->setSourceFile($path);
            $sizes = [];

            for ($page = 1; $page <= min($pages, 2); $page++) {
                $sizes[] = $mpdf->getTemplateSize($mpdf->importPage($page));
            }
        } catch (Throwable) {
            throw new LetterheadRejected(LetterheadRejected::NOT_READABLE);
        }

        if ($pages < 1 || $pages > 2) {
            throw new LetterheadRejected(LetterheadRejected::PAGES);
        }

        foreach ($sizes as $size) {
            if (abs((float) $size['width'] - 210) > self::TOLERANCE_MM || abs((float) $size['height'] - 297) > self::TOLERANCE_MM) {
                throw new LetterheadRejected(LetterheadRejected::NOT_A4);
            }
        }

        return $pages;
    }
}
