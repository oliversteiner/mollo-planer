<?php

namespace Tests\Unit;

use App\Support\RichTextDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RichTextDocumentTest extends TestCase
{
    public function test_every_allowed_element_passes(): void
    {
        $this->assertNull(RichTextDocument::validate($this->fullDocument()));
    }

    public function test_inline_code_mark_passes(): void
    {
        $this->assertNull(RichTextDocument::validate(self::paragraph(['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'code']]])));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidDocuments(): array
    {
        $text = ['type' => 'text', 'text' => 'Hi'];

        return [
            'not an array' => ['<p>Hi</p>', 'not_a_document'],
            'wrong root' => [['type' => 'paragraph'], 'not_a_document'],
            'image node' => [self::doc(['type' => 'image', 'attrs' => ['src' => 'https://x.ch/a.png']]), 'node_not_allowed'],
            'code block' => [self::doc(['type' => 'codeBlock', 'content' => [$text]]), 'node_not_allowed'],
            'heading level 1' => [self::doc(['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [$text]]), 'heading_level'],
            'heading level 4' => [self::doc(['type' => 'heading', 'attrs' => ['level' => 4], 'content' => [$text]]), 'heading_level'],
            'heading without level' => [self::doc(['type' => 'heading', 'content' => [$text]]), 'heading_level'],
            'strike mark' => [self::paragraph(['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'strike']]]), 'mark_not_allowed'],
            'javascript link' => [self::link('javascript:alert(1)'), 'link_not_allowed'],
            'data link' => [self::link('data:text/html,<script>alert(1)</script>'), 'link_not_allowed'],
            'link with spaces' => [self::link('https://example.com/a b'), 'link_not_allowed'],
            'http link without host' => [self::link('http:///path'), 'link_not_allowed'],
            'relative link' => [self::link('/admin'), 'link_not_allowed'],
            'link with extra attribute' => [self::paragraph(['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://x.ch', 'onclick' => 'x']]]]), 'invalid_structure'],
            'unknown node key' => [self::doc(['type' => 'paragraph', 'html' => '<b>x</b>']), 'invalid_structure'],
            'paragraph in paragraph' => [self::paragraph(['type' => 'paragraph']), 'invalid_structure'],
            'list item directly in doc' => [self::doc(['type' => 'listItem', 'content' => [['type' => 'paragraph']]]), 'invalid_structure'],
            'text directly in doc' => [self::doc($text), 'invalid_structure'],
            'marks on paragraph' => [self::doc(['type' => 'paragraph', 'marks' => [['type' => 'bold']]]), 'invalid_structure'],
            'empty text node' => [self::paragraph(['type' => 'text', 'text' => '']), 'invalid_structure'],
            'text with content' => [self::paragraph(['type' => 'text', 'text' => 'x', 'content' => []]), 'invalid_structure'],
            'content as object' => [['type' => 'doc', 'content' => ['a' => ['type' => 'paragraph']]], 'invalid_structure'],
            'attrs on paragraph' => [self::doc(['type' => 'paragraph', 'attrs' => ['style' => 'color:red']]), 'invalid_structure'],
            'nested doc' => [self::doc(['type' => 'doc']), 'invalid_structure'],
        ];
    }

    #[DataProvider('invalidDocuments')]
    public function test_documents_outside_the_whitelist_are_rejected(mixed $document, string $expectedKey): void
    {
        $this->assertSame($expectedKey, RichTextDocument::validate($document)[0] ?? null);
    }

    public function test_text_length_limit(): void
    {
        $max = RichTextDocument::MAX_TEXT_LENGTH;

        $this->assertNull(RichTextDocument::validate(self::paragraph(['type' => 'text', 'text' => str_repeat('ä', $max)])));
        $this->assertSame(
            ['too_long', ['max' => $max]],
            RichTextDocument::validate(self::doc(
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => str_repeat('a', $max)]]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'b']]],
            )),
        );
    }

    public function test_depth_limit(): void
    {
        // doc (1) + 7 blockquotes + paragraph + text = depth 10.
        $this->assertNull(RichTextDocument::validate(self::nestedQuotes(7)));
        $this->assertSame('too_deep', RichTextDocument::validate(self::nestedQuotes(8))[0]);
    }

    public function test_node_count_limit(): void
    {
        $paragraphs = array_fill(0, RichTextDocument::MAX_NODES, ['type' => 'paragraph']);

        $this->assertSame('too_large', RichTextDocument::validate(self::doc(...$paragraphs))[0]);
    }

    public function test_empty_documents(): void
    {
        $this->assertTrue(RichTextDocument::isEmpty(null));
        $this->assertTrue(RichTextDocument::isEmpty(['type' => 'doc']));
        $this->assertTrue(RichTextDocument::isEmpty(self::doc(['type' => 'paragraph'])));
        $this->assertTrue(RichTextDocument::isEmpty(self::paragraph(['type' => 'hardBreak'], ['type' => 'text', 'text' => "  \n "])));
        $this->assertFalse(RichTextDocument::isEmpty(self::paragraph(['type' => 'text', 'text' => 'x'])));
    }

    public function test_normalize_turns_empty_into_null_and_keeps_only_href(): void
    {
        $this->assertNull(RichTextDocument::normalize(self::doc(['type' => 'paragraph'])));

        $editorLink = self::paragraph(['type' => 'text', 'text' => 'FCA', 'marks' => [
            ['type' => 'bold', 'attrs' => []],
            ['type' => 'link', 'attrs' => ['href' => 'https://mollo.ch', 'target' => '_blank', 'rel' => 'noopener', 'class' => null]],
        ]]);

        $this->assertSame(
            self::paragraph(['type' => 'text', 'text' => 'FCA', 'marks' => [
                ['type' => 'bold'],
                ['type' => 'link', 'attrs' => ['href' => 'https://mollo.ch']],
            ]]),
            RichTextDocument::normalize($editorLink),
        );
    }

    /**
     * A document as the TipTap editor produces it, using every allowed element.
     *
     * @return array<string, mixed>
     */
    private function fullDocument(): array
    {
        $text = fn (string $t, array $marks = []) => array_filter(['type' => 'text', 'text' => $t, 'marks' => $marks ?: null]);
        $item = fn (string $t) => ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [$text($t)]]]];

        return self::doc(
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [$text('Ziel')]],
            ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [$text('Details')]],
            ['type' => 'paragraph', 'content' => [
                $text('fett', [['type' => 'bold']]),
                ['type' => 'hardBreak'],
                $text('kursiv', [['type' => 'italic']]),
                $text('Web', [['type' => 'link', 'attrs' => ['href' => 'https://mollo.ch', 'target' => '_blank', 'rel' => 'noopener noreferrer nofollow', 'class' => null]]]),
                $text('Mail', [['type' => 'link', 'attrs' => ['href' => 'mailto:info@example.com']]]),
            ]],
            ['type' => 'bulletList', 'content' => [$item('eins'), $item('zwei')]],
            ['type' => 'orderedList', 'attrs' => ['start' => 1, 'type' => null], 'content' => [$item('erstens')]],
            ['type' => 'blockquote', 'content' => [['type' => 'paragraph', 'content' => [$text('Zitat')]]]],
            ['type' => 'paragraph'],
        );
    }

    /**
     * @param  array<string, mixed>  ...$blocks
     * @return array<string, mixed>
     */
    private static function doc(array ...$blocks): array
    {
        return ['type' => 'doc', 'content' => $blocks];
    }

    /**
     * @param  array<string, mixed>  ...$inline
     * @return array<string, mixed>
     */
    private static function paragraph(array ...$inline): array
    {
        return self::doc(['type' => 'paragraph', 'content' => $inline]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function link(string $href): array
    {
        return self::paragraph(['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'link', 'attrs' => ['href' => $href]]]]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function nestedQuotes(int $levels): array
    {
        $node = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'tief']]];

        for ($i = 0; $i < $levels; $i++) {
            $node = ['type' => 'blockquote', 'content' => [$node]];
        }

        return self::doc($node);
    }
}
