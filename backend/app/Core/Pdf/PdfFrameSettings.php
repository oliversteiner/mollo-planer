<?php

namespace App\Core\Pdf;

use Illuminate\Support\Facades\DB;

/**
 * Settings of the PDF frame (Auftrag 39 F): the letterhead, the layout in mm, the font of the text and on which side the
 * envelope has its window. One row of app_settings (key pdf_frame), so the frame of the starter kit needs no table of
 * a module. Who may change them is decided by the module that offers the page (billing.manage).
 */
final readonly class PdfFrameSettings
{
    public const KEY = 'pdf_frame';

    public function __construct(
        public ?Letterhead $letterhead = null,
        public PageLayout $layout = new PageLayout,
        public string $font = PdfFrame::DEFAULT_FONT,
        public string $addressWindow = 'left',
    ) {}

    public static function load(): self
    {
        $raw = DB::table('app_settings')->where('key', self::KEY)->value('value');
        $values = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($values)) {
            return new self;
        }

        $font = is_string($values['font'] ?? null) && PdfFonts::isKnown($values['font']) ? $values['font'] : PdfFrame::DEFAULT_FONT;

        return new self(
            Letterhead::fromArray(is_array($values['letterhead'] ?? null) ? $values['letterhead'] : null),
            PageLayout::fromArray(is_array($values['layout'] ?? null) ? $values['layout'] : null),
            $font,
            ($values['addressWindow'] ?? 'left') === 'right' ? 'right' : 'left',
        );
    }

    public function save(): void
    {
        $value = json_encode([
            'letterhead' => $this->letterhead?->toArray(),
            'layout' => $this->layout->toArray(),
            'font' => $this->font,
            'addressWindow' => $this->addressWindow,
        ], JSON_THROW_ON_ERROR);

        if (DB::table('app_settings')->where('key', self::KEY)->exists()) {
            DB::table('app_settings')->where('key', self::KEY)->update(['value' => $value, 'updated_at' => now()]);

            return;
        }

        DB::table('app_settings')->insert(['key' => self::KEY, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function with(?Letterhead $letterhead = null, ?PageLayout $layout = null, ?string $font = null, ?string $addressWindow = null, bool $removeLetterhead = false): self
    {
        return new self(
            $removeLetterhead ? null : ($letterhead ?? $this->letterhead),
            $layout ?? $this->layout,
            $font ?? $this->font,
            $addressWindow ?? $this->addressWindow,
        );
    }

    /** The letterhead, if one is uploaded and its file is there. */
    public function usableLetterhead(): ?Letterhead
    {
        return $this->letterhead?->exists() ? $this->letterhead : null;
    }
}
