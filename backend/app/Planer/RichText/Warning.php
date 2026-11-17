<?php

namespace App\Planer\RichText;

/**
 * One construct of the Markdown that is not part of the whitelist. In
 * strict mode it is an error (InvalidMarkdown), in lenient mode a warning
 * of the result.
 *
 * Kinds: heading_level, code_block, table, image, link_scheme, strikethrough,
 * html, thematic_break, unclosed_emphasis, and in lenient mode also
 * ordered_start and link_title (valid in a file, but the detail is lost).
 * document_limit: the document is longer or deeper than the app allows.
 */
final readonly class Warning
{
    public function __construct(
        public string $kind,
        public int $line,
        public string $excerpt,
    ) {}

    /**
     * @return array{kind: string, line: int, excerpt: string}
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind, 'line' => $this->line, 'excerpt' => $this->excerpt];
    }
}
