<?php

namespace App\Core\Pdf;

/**
 * What a PDF of the frame needs besides its HTML (Auftrag 81 B): the frame turns it into pages, the callers never touch
 * the PDF library. Texts are plain text; the frame escapes and places them.
 */
final readonly class PdfOptions
{
    /**
     * @param  array{top?: float|int, bottom?: float|int, left?: float|int, right?: float|int}  $margins  in mm
     * @param  float|null  $firstPageTop  top margin of page 1 in mm (letters: page 1 starts lower), null: as the others
     * @param  string|null  $header  a line at the top of every letter page, with a rule below
     * @param  string|null  $footer  a line at the bottom left of every letter page
     * @param  string|null  $pageLabel  bottom right of every letter page, with {current} and {total}
     * @param  string|null  $watermark  across every page, also the appended ones (a draft must not be paid)
     * @param  list<string>  $appendPages  PDFs (bytes) whose pages follow unchanged: no letterhead, header or footer
     */
    public function __construct(
        public string $title,
        public array $margins = [],
        public ?float $firstPageTop = null,
        public string $font = PdfFrame::DEFAULT_FONT,
        public ?Letterhead $letterhead = null,
        public ?string $header = null,
        public ?string $footer = null,
        public ?string $pageLabel = null,
        public ?string $watermark = null,
        public array $appendPages = [],
    ) {}
}
