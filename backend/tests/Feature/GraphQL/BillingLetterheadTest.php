<?php

namespace Tests\Feature\GraphQL;

use App\Core\Pdf\LetterheadRejected;
use App\Core\Pdf\PdfFrameSettings;
use App\Models\BillingSetting;
use App\Models\Document;
use App\Services\Billing\InvoicePdf;
use App\Services\Billing\InvoiceSheet;
use App\Services\DocumentPdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mpdf\Mpdf;

/**
 * Billing, part F (Auftrag 39): the letterhead of the PDF frame, its checks, where it lies, the layout and the option
 * for documents.
 */
class BillingLetterheadTest extends GraphQLTestCase
{
    private const UPLOAD = 'mutation ($file: Upload!) { uploadLetterhead(file: $file) { letterhead { fileName pages } } }';

    /** Fill colours only the letterhead uses (RGB 0–255), to find where its pages lie. */
    private const MARK_FIRST = [26, 51, 77];

    private const MARK_FOLLOWING = [204, 102, 51];

    private const MARK_THIRD = [10, 200, 10];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        BillingSetting::current()->fill([
            'name' => 'Mollo', 'street' => 'Weg', 'house_number' => '2', 'postal_code' => '3000', 'city' => 'Bern',
            'country' => 'CH', 'iban' => 'CH9300762011623852957',
        ])->save();
    }

    /**
     * A letterhead made with mPDF: each page a square in its own colour.
     *
     * @param  list<array{int, int, int}>  $marks  one per page
     */
    private function letterhead(array $marks = [self::MARK_FIRST], string $format = 'A4', string $name = 'briefpapier.pdf'): UploadedFile
    {
        $mpdf = new Mpdf(['tempDir' => storage_path('framework/cache/mpdf'), 'format' => $format]);

        foreach ($marks as $mark) {
            $mpdf->AddPage();
            $mpdf->SetFillColor(...$mark);
            $mpdf->Rect(10, 10, 20, 20, 'F');
        }

        return UploadedFile::fake()->createWithContent($name, $mpdf->OutputBinaryData());
    }

    /** A PDF 1.5 whose cross-reference is a stream: the free FPDI cannot read it. */
    private function compressedXref(): UploadedFile
    {
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] >>'];
        $pdf = "%PDF-1.5\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $rows = "\x00\x00\x00\xff";

        foreach ([...$offsets, $xref] as $offset) {
            $rows .= "\x01".pack('n', $offset)."\x00";
        }

        $pdf .= "4 0 obj\n<< /Type /XRef /Size 5 /Root 1 0 R /W [1 2 1] /Length ".strlen($rows)." >>\nstream\n{$rows}\nendstream\nendobj\nstartxref\n{$xref}\n%%EOF\n";

        return UploadedFile::fake()->createWithContent('neu.pdf', $pdf);
    }

    private function upload(UploadedFile $file, string $actor = 'admin', bool $confirmed = true): TestResponse
    {
        $this->as($actor);

        $this->withSession(['auth.password_confirmed_at' => $confirmed ? time() : 0]);

        return $this->multipartGraphQL(
            ['query' => self::UPLOAD, 'variables' => ['file' => null]],
            ['0' => ['variables.file']],
            ['0' => $file],
            ['Referer' => 'http://localhost'],
        );
    }

    /**
     * For each page of a PDF made by mPDF: the last fill colour of every template (imported page) it draws. A page of
     * the letterhead shows as its mark, the payment part as black, a page without a template as an empty list.
     *
     * @return list<list<string>>
     */
    private static function templatesPerPage(string $pdf): array
    {
        $stream = function (string $id) use ($pdf): string {
            preg_match('/\n'.$id.' 0 obj\s*<<(.*?)>>\s*stream\r?\n(.*?)\r?\nendstream/s', $pdf, $object);

            return str_contains($object[1], 'FlateDecode') ? (string) gzuncompress($object[2]) : $object[2];
        };

        preg_match_all('#/(TPL\d+) (\d+) 0 R#', $pdf, $templates, PREG_SET_ORDER);
        $colourOf = [];

        foreach ($templates as [, $name, $id]) {
            preg_match_all('/([\d.]+ [\d.]+ [\d.]+) rg/', $stream($id), $colours);
            $colourOf[$name] = (string) end($colours[1]);
        }

        preg_match('#/Type\s*/Pages\s*/Kids\s*\[(.*?)\]#s', $pdf, $kids);
        preg_match_all('/(\d+) 0 R/', $kids[1], $pages);

        return array_map(function (string $page) use ($pdf, $stream, $colourOf): array {
            preg_match('/\n'.$page.' 0 obj\s*<<(.*?)>>\s*endobj/s', $pdf, $dictionary);
            preg_match('#/Contents (\d+) 0 R#', $dictionary[1], $contents);
            preg_match_all('#/(TPL\d+) Do#', $stream($contents[1]), $used);

            return array_map(fn (string $name): string => $colourOf[$name], $used[1]);
        }, $pages[1]);
    }

    /** A mark as mPDF writes the fill colour. */
    private static function colour(array $mark): string
    {
        return implode(' ', array_map(fn (int $value): string => sprintf('%.3F', $value / 255), $mark));
    }

    public function test_a_valid_letterhead_is_kept_on_the_private_disk(): void
    {
        $this->upload($this->letterhead([self::MARK_FIRST, self::MARK_FOLLOWING]))
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.uploadLetterhead.letterhead', ['fileName' => 'briefpapier.pdf', 'pages' => 2]);

        $settings = PdfFrameSettings::load();
        $this->assertSame(2, $settings->letterhead->pages);
        Storage::disk('local')->assertExists($settings->letterhead->path);
        $this->assertStringStartsWith('pdf-frame/', $settings->letterhead->path);

        $this->as('member')->gql('{ letterheadAvailable }')->assertJsonPath('data.letterheadAvailable', true);
    }

    public function test_a_new_letterhead_replaces_the_old_file(): void
    {
        $this->upload($this->letterhead());
        $old = PdfFrameSettings::load()->letterhead->path;

        $this->upload($this->letterhead(name: 'neu.pdf'))->assertJsonPath('data.uploadLetterhead.letterhead.fileName', 'neu.pdf');

        Storage::disk('local')->assertMissing($old);
        $this->assertCount(1, Storage::disk('local')->allFiles('pdf-frame'));
    }

    public function test_unusable_files_are_refused_with_a_code_and_nothing_is_stored(): void
    {
        $cases = [
            LetterheadRejected::NOT_PDF => UploadedFile::fake()->createWithContent('bild.pdf', 'GIF89a'),
            LetterheadRejected::NOT_A4 => $this->letterhead(format: 'A5'),
            LetterheadRejected::PAGES => $this->letterhead([self::MARK_FIRST, self::MARK_FOLLOWING, self::MARK_THIRD]),
            LetterheadRejected::NOT_READABLE => $this->compressedXref(),
            LetterheadRejected::TOO_LARGE => UploadedFile::fake()->createWithContent('gross.pdf', '%PDF-1.4'.str_repeat(' ', 5 * 1024 * 1024)),
        ];

        foreach ($cases as $code => $file) {
            $this->upload($file)->assertJsonPath('errors.0.extensions.code', $code);
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertNull(PdfFrameSettings::load()->letterhead);
        $this->assertStringContainsString('PDF 1.4', __('pdf.letterhead.'.LetterheadRejected::NOT_READABLE));
    }

    public function test_without_billing_or_a_fresh_password_there_is_no_upload(): void
    {
        $this->upload($this->letterhead(), 'owner')->assertJsonPath('errors.0.message', __('graphql.unauthorized'));
        $this->upload($this->letterhead(), 'admin', confirmed: false)
            ->assertJsonPath('errors.0.extensions.validation.password.0', __('graphql.password_confirmation_required'));

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_the_letterhead_lies_under_page_1_and_the_following_pages_but_never_under_the_payment_part(): void
    {
        $this->upload($this->letterhead([self::MARK_FIRST, self::MARK_FOLLOWING]));

        $pages = self::templatesPerPage(app(InvoicePdf::class)->renderSheet(InvoiceSheet::sample('de', 30, 3)));
        $payment = array_pop($pages);

        $this->assertGreaterThanOrEqual(2, count($pages), 'The sample with 30 items has more than one letter page.');
        $this->assertSame([self::colour(self::MARK_FIRST)], $pages[0]);

        foreach (array_slice($pages, 1) as $page) {
            $this->assertSame([self::colour(self::MARK_FOLLOWING)], $page);
        }

        $this->assertNotContains(self::colour(self::MARK_FIRST), $payment);
        $this->assertNotContains(self::colour(self::MARK_FOLLOWING), $payment);
        $this->assertCount(1, $payment, 'The payment part is the one page of the QR bill, nothing under it.');
    }

    public function test_a_letterhead_of_one_page_lies_only_under_page_1(): void
    {
        $this->upload($this->letterhead([self::MARK_FIRST]));

        $pages = self::templatesPerPage(app(InvoicePdf::class)->renderSheet(InvoiceSheet::sample('de', 30, 3)));

        $this->assertSame([self::colour(self::MARK_FIRST)], $pages[0]);
        $this->assertSame([], $pages[1], 'Following pages stay plain.');
    }

    public function test_documents_take_the_letterhead_only_when_asked(): void
    {
        $this->upload($this->letterhead([self::MARK_FIRST]));
        $document = Document::factory()->for($this->project)->create(['content' => "# Titel\n\nText"]);

        $without = self::templatesPerPage(app(DocumentPdf::class)->render($document->fresh()));
        $with = self::templatesPerPage(app(DocumentPdf::class)->render($document->fresh(), withLetterhead: true));

        $this->assertSame([[]], $without);
        $this->assertSame([[self::colour(self::MARK_FIRST)]], $with);

        // Through the API, as a member without billing.manage.
        $this->as('member')->gql('mutation ($id: ID!) { exportDocumentPdf(id: $id, withLetterhead: true) { mimeType } }', ['id' => $document->id])
            ->assertJsonMissingPath('errors');
    }

    public function test_the_layout_is_kept_and_checked(): void
    {
        $layout = ['topFirst' => 105, 'topFollowing' => 40, 'bottom' => 30, 'left' => 22, 'right' => 18, 'addressLeft' => 115, 'addressTop' => 50, 'addressWidth' => 75, 'addressHeight' => 30];
        $mutation = 'mutation ($input: PdfFrameInput!) { updatePdfFrame(input: $input) { layout { topFirst addressLeft } font addressWindow } }';

        $this->as('admin')->withSession(['auth.password_confirmed_at' => time()])
            ->gql($mutation, ['input' => ['layout' => $layout, 'font' => 'dejavuserif', 'addressWindow' => 'right']])
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.updatePdfFrame', ['layout' => ['topFirst' => 105, 'addressLeft' => 115], 'font' => 'dejavuserif', 'addressWindow' => 'right']);
        $this->assertSame(105.0, PdfFrameSettings::load()->layout->topFirst);

        $this->as('admin')->withSession(['auth.password_confirmed_at' => time()])
            ->gql($mutation, ['input' => ['layout' => ['addressLeft' => 150] + $layout, 'font' => 'dejavuserif', 'addressWindow' => 'right']])
            ->assertJsonPath('errors.0.extensions.validation', fn (array $errors) => in_array(__('pdf.address_outside'), array_merge(...array_values($errors)), true));
        $this->as('admin')->withSession(['auth.password_confirmed_at' => time()])
            ->gql($mutation, ['input' => ['layout' => $layout, 'font' => 'comic', 'addressWindow' => 'left']])
            ->assertJsonPath('errors.0.extensions.validation', fn (array $errors) => count($errors) === 1);
        $this->as('admin')->withSession(['auth.password_confirmed_at' => 0])->gql($mutation, ['input' => ['layout' => $layout, 'font' => 'dejavusans', 'addressWindow' => 'left']])
            ->assertJsonPath('errors.0.extensions.validation.password.0', __('graphql.password_confirmation_required'));
    }

    public function test_the_sample_invoice_and_the_defaults(): void
    {
        $file = $this->as('admin')->gql('mutation { sampleInvoicePdf(locale: EN) { fileName contentBase64 } }')
            ->assertJsonMissingPath('errors')->json('data.sampleInvoicePdf');

        $this->assertSame('sample-invoice.pdf', $file['fileName']);
        $this->assertStringStartsWith('%PDF-', base64_decode($file['contentBase64']));

        $this->as('admin')->gql('{ pdfFrame { letterhead { pages } defaults { topFirst topFollowing bottom left right addressLeft addressTop addressWidth addressHeight } font fonts { key } addressWindow } }')
            ->assertJsonPath('data.pdfFrame.letterhead', null)
            ->assertJsonPath('data.pdfFrame.defaults', ['topFirst' => 100, 'topFollowing' => 35, 'bottom' => 25, 'left' => 20, 'right' => 20, 'addressLeft' => 20, 'addressTop' => 50, 'addressWidth' => 75, 'addressHeight' => 30])
            ->assertJsonPath('data.pdfFrame.font', 'dejavusans')
            ->assertJsonPath('data.pdfFrame.addressWindow', 'left');

        $this->as('owner')->gql('{ pdfFrame { font } }')->assertJsonPath('errors.0.message', __('graphql.unauthorized'));
        $this->as('member')->gql('{ letterheadAvailable }')->assertJsonPath('data.letterheadAvailable', false);
    }
}
