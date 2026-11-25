<?php

namespace App\GraphQL\Billing;

use App\Core\Pdf\LetterheadRejected;
use App\Core\Pdf\LetterheadUpload;
use App\Core\Pdf\PageLayout;
use App\Core\Pdf\PdfFonts;
use App\Core\Pdf\PdfFrameSettings;
use App\GraphQL\Concerns\ConfirmsPassword;
use App\Services\Billing\InvoicePdf;
use App\Services\Billing\InvoiceSheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Letterhead, layout and font of the PDF frame (Auftrag 39 F), managed under billing → settings (billing.manage,
 * checked by @can). Changes need a fresh password confirmation.
 */
class PdfFrameMutations
{
    use ConfirmsPassword;

    public function __construct(private readonly LetterheadUpload $letterheads, private readonly InvoicePdf $pdf) {}

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        return self::present(PdfFrameSettings::load());
    }

    public function available(): bool
    {
        return PdfFrameSettings::load()->usableLetterhead() !== null;
    }

    /**
     * @param  array{file: UploadedFile}  $args
     * @return array<string, mixed>
     *
     * @throws LetterheadRejected|ValidationException
     */
    public function upload(mixed $root, array $args): array
    {
        $this->requireRecentPasswordConfirmation();
        $this->letterheads->store($args['file']);

        return self::present(PdfFrameSettings::load());
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function remove(): array
    {
        $this->requireRecentPasswordConfirmation();
        $this->letterheads->remove();

        return self::present(PdfFrameSettings::load());
    }

    /**
     * @param  array{input: array{layout: array<string, float>, font: string, addressWindow: string}}  $args
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function update(mixed $root, array $args): array
    {
        $this->requireRecentPasswordConfirmation();

        $settings = PdfFrameSettings::load()->with(
            layout: PageLayout::fromArray($args['input']['layout']),
            font: $args['input']['font'],
            addressWindow: $args['input']['addressWindow'],
        );
        $settings->save();

        return self::present($settings);
    }

    /**
     * @param  array{locale?: string|null}  $args
     * @return array{fileName: string, mimeType: string, contentBase64: string}
     */
    public function sample(mixed $root, array $args): array
    {
        $locale = ($args['locale'] ?? 'de') === 'en' ? 'en' : 'de';

        return [
            'fileName' => $locale === 'en' ? 'sample-invoice.pdf' : 'musterrechnung.pdf',
            'mimeType' => 'application/pdf',
            'contentBase64' => base64_encode($this->pdf->renderSheet(InvoiceSheet::sample($locale))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function present(PdfFrameSettings $settings): array
    {
        return [
            'letterhead' => $settings->usableLetterhead()?->toArray(),
            'layout' => $settings->layout->toArray(),
            'font' => $settings->font,
            'fonts' => array_map(fn (string $key, string $name): array => ['key' => $key, 'name' => $name], array_keys(PdfFonts::ALL), PdfFonts::ALL),
            'addressWindow' => $settings->addressWindow,
            'defaults' => (new PageLayout)->toArray(),
        ];
    }
}
