<?php

namespace Tests\Feature\GraphQL;

use App\Enums\DocumentAccess;
use App\Enums\DocumentKind;
use App\Models\Document;
use App\Services\DocumentPdf;

/** D5 of docs/dokumentenverwaltung.md: the PDF of a document. */
class DocumentPdfTest extends GraphQLTestCase
{
    private const EXPORT = 'mutation ($id: ID!) { exportDocumentPdf(id: $id) { fileName mimeType contentBase64 } }';

    private function make(DocumentAccess $access = DocumentAccess::READ, array $attributes = []): Document
    {
        return Document::factory()->for($this->project)->create(['owner_id' => $this->users['owner']->id, 'project_access' => $access, 'title' => 'Sitzung Größe 12', 'kind' => DocumentKind::MINUTES, ...$attributes]);
    }

    /** @return array{fileName: string, mimeType: string, bytes: string} */
    private function export(string $as, Document $document): array
    {
        $file = $this->as($as)->gql(self::EXPORT, ['id' => $document->id])->assertJsonMissingPath('errors')->json('data.exportDocumentPdf');

        return ['fileName' => $file['fileName'], 'mimeType' => $file['mimeType'], 'bytes' => (string) base64_decode($file['contentBase64'], true)];
    }

    public function test_a_reader_gets_a_pdf_with_the_name_of_the_title(): void
    {
        $document = $this->make(attributes: ['content' => "# Protokoll\n\n| A | B |\n| --- | --- |\n| 1 | 2 |\n\n- [x] fertig\n- [ ] offen\n\n```php\necho 1;\n```\n"]);

        $file = $this->export('member', $document);

        $this->assertSame(['sitzung-grosse-12.pdf', 'application/pdf'], [$file['fileName'], $file['mimeType']]);
        $this->assertStringStartsWith('%PDF-', $file['bytes']);
        $this->assertStringContainsString('%%EOF', $file['bytes']);
        $this->assertGreaterThan(2000, strlen($file['bytes']));
    }

    public function test_without_the_level_read_there_is_no_pdf(): void
    {
        $private = $this->make(DocumentAccess::NONE);

        $this->assertUnauthorized($this->as('stranger')->gql(self::EXPORT, ['id' => $this->make()->id]));
        $this->assertUnauthorized($this->as('member')->gql(self::EXPORT, ['id' => $private->id]));
        $this->as('manager')->gql(self::EXPORT, ['id' => $private->id])->assertJsonMissingPath('errors');
    }

    public function test_an_archived_project_still_exports(): void
    {
        $document = $this->make();
        $this->project->forceFill(['archived_at' => now()])->save();

        $this->assertStringStartsWith('%PDF-', $this->export('member', $document)['bytes']);
    }

    public function test_html_in_the_content_is_text_in_the_html_and_unsafe_links_are_off(): void
    {
        $html = new \ReflectionMethod(DocumentPdf::class, 'html');
        $result = $html->invoke(app(DocumentPdf::class), "<script>alert(1)</script> <b>fett</b>\n\n[x](javascript:alert(1)) und [ok](https://example.ch)\n\n<img src=\"http://example.com/a.png\">");

        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('<b>', $result);
        $this->assertStringNotContainsString('<img', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringContainsString('href="https://example.ch"', $result);
    }

    public function test_the_file_name_is_safe_whatever_the_title(): void
    {
        $name = fn (string $title): string => app(DocumentPdf::class)->fileName(new Document(['title' => $title]));

        $this->assertSame('document.pdf', $name('???'));
        $this->assertSame('abc.pdf', $name('A/B\\C'));
        $this->assertSame('uber-uns.pdf', $name('Über uns'));
    }

    public function test_a_large_document_is_made_within_the_limits_of_the_server(): void
    {
        $content = str_repeat("Ein Absatz mit etwas Text, der sich wiederholt und Platz braucht.\n\n", 1500)."| A | B |\n| --- | --- |\n".str_repeat("| 1 | 2 |\n", 200);
        $document = $this->make(attributes: ['content' => $content]);

        $file = $this->export('owner', $document);

        $this->assertStringStartsWith('%PDF-', $file['bytes']);
        $this->assertLessThan(256 * 1024 * 1024, memory_get_peak_usage(true), 'memory_limit of the server: 256M');
    }
}
