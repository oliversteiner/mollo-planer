<?php

namespace App\Support;

use App\Planer\RichText\Profile;

/**
 * TipTap documents (JSON) for descriptions and comments.
 *
 * The whitelist here must match the editor configuration in the frontend:
 * paragraph, heading (levels 2 and 3), bullet list, ordered list, list item,
 * hard break, blockquote, text; marks bold, italic, inline code and link (http, https,
 * mailto). HTML is never stored or produced.
 *
 * The profile "Document" (documents, docs/dokumentenverwaltung.md §5.2, spec/format-v2.md §6.2) allows more:
 * headings 1 to 4, code block (text only, language of the characters A-Z a-z 0-9 _ + # . -), horizontal rule,
 * table (rows of header and normal cells, every cell exactly one paragraph of text, no merged cells, no
 * alignment), task list, mark strike. A list item and a task begin with a paragraph. The editor's default
 * attributes (colspan 1, rowspan 1, colwidth null, align null, checked, language) are accepted. Limits: 200 000
 * characters of text, 50 000 nodes; the Markdown after the conversion is limited as well (DocumentContent).
 */
final class RichTextDocument
{
    public const MAX_TEXT_LENGTH = 20000;

    /** Lists nest up to 8 levels (a list and an item per level): the planer file allows deep lists (spec/format-v2.md 6.1). */
    public const MAX_DEPTH = 20;

    /** Guards against huge documents made of empty nodes. */
    public const MAX_NODES = 5000;

    /** The profile "Document" is bigger: 200 000 characters of text (§5.3) and more nodes (tables, tasks). */
    public const DOCUMENT_MAX_TEXT_LENGTH = 200000;

    public const DOCUMENT_MAX_NODES = 50000;

    private const BLOCKS = ['paragraph', 'heading', 'bulletList', 'orderedList', 'blockquote'];

    private const DOCUMENT_BLOCKS = ['paragraph', 'heading', 'bulletList', 'orderedList', 'taskList', 'blockquote', 'codeBlock', 'horizontalRule', 'table'];

    private const INLINE = ['text', 'hardBreak'];

    /**
     * Allowed child node types per node type. Null means: no content.
     *
     * @var array<string, list<string>|null>
     */
    private const CHILDREN = [
        'doc' => self::BLOCKS,
        'paragraph' => self::INLINE,
        'heading' => self::INLINE,
        'blockquote' => self::BLOCKS,
        'bulletList' => ['listItem'],
        'orderedList' => ['listItem'],
        'listItem' => self::BLOCKS,
        'text' => null,
        'hardBreak' => null,
    ];

    /**
     * The same for the profile "Document".
     *
     * @var array<string, list<string>|null>
     */
    private const DOCUMENT_CHILDREN = [
        'doc' => self::DOCUMENT_BLOCKS,
        'paragraph' => self::INLINE,
        'heading' => self::INLINE,
        'blockquote' => self::DOCUMENT_BLOCKS,
        'bulletList' => ['listItem'],
        'orderedList' => ['listItem'],
        'taskList' => ['taskItem'],
        'listItem' => self::DOCUMENT_BLOCKS,
        'taskItem' => self::DOCUMENT_BLOCKS,
        'codeBlock' => ['text'],
        'horizontalRule' => null,
        'table' => ['tableRow'],
        'tableRow' => ['tableHeader', 'tableCell'],
        'tableHeader' => ['paragraph'],
        'tableCell' => ['paragraph'],
        'text' => null,
        'hardBreak' => null,
    ];

    private const NODE_KEYS = ['type', 'attrs', 'content', 'text', 'marks'];

    private const MARKS = ['bold', 'italic', 'code', 'link'];

    private const DOCUMENT_MARKS = ['bold', 'italic', 'strike', 'code', 'link'];

    private const CODE_LANGUAGE = '/^[A-Za-z0-9_+#.-]{1,32}$/';

    /** Link attributes the editor sends. Only href is kept (see normalize). */
    private const LINK_ATTRS = ['href', 'target', 'rel', 'class'];

    private const LINK_SCHEMES = ['http', 'https', 'mailto'];

    private const ORDERED_LIST_TYPES = ['1', 'a', 'A', 'i', 'I'];

    private int $nodeCount = 0;

    private int $textLength = 0;

    private function __construct(private readonly Profile $profile) {}

    private function isDocument(): bool
    {
        return $this->profile === Profile::Document;
    }

    /**
     * @return array<string, list<string>|null>
     */
    private function children(): array
    {
        return $this->isDocument() ? self::DOCUMENT_CHILDREN : self::CHILDREN;
    }

    /**
     * Check a document against the whitelist.
     *
     * Returns null if valid, otherwise [message key, parameters]. The keys
     * refer to lang/{de,en}/richtext.php.
     *
     * @return array{0: string, 1: array<string, int|string>}|null
     */
    public static function validate(mixed $document, Profile $profile = Profile::Description): ?array
    {
        if (! is_array($document) || ($document['type'] ?? null) !== 'doc') {
            return ['not_a_document', []];
        }

        $checker = new self($profile);
        $error = $checker->checkNode($document, 1);

        if ($error !== null) {
            return $error;
        }

        $max = $checker->isDocument() ? self::DOCUMENT_MAX_TEXT_LENGTH : self::MAX_TEXT_LENGTH;

        if ($checker->textLength > $max) {
            return ['too_long', ['max' => $max]];
        }

        return null;
    }

    /**
     * True if the document contains no visible text.
     *
     * @param  array<string, mixed>|null  $document
     */
    public static function isEmpty(?array $document): bool
    {
        return $document === null || trim(self::plainText($document)) === '';
    }

    /**
     * The document as one line of plain text, at most $length characters
     * (with "…" when cut), for example a comment in the trash.
     *
     * @param  array<string, mixed>|null  $document
     */
    public static function excerpt(?array $document, int $length = 80): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $document === null ? '' : self::lineText($document)));

        return mb_strlen($text) <= $length ? $text : rtrim(mb_substr($text, 0, $length - 1)).'…';
    }

    /**
     * Prepare a valid document for storage: empty documents become null,
     * links keep only their href (rel and target are set by the frontend).
     *
     * @param  array<string, mixed>|null  $document
     * @return array<string, mixed>|null
     */
    public static function normalize(?array $document): ?array
    {
        if (self::isEmpty($document)) {
            return null;
        }

        return self::normalizeNode($document);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function plainText(array $node): string
    {
        $text = is_string($node['text'] ?? null) ? $node['text'] : '';

        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            if (is_array($child)) {
                $text .= self::plainText($child);
            }
        }

        return $text;
    }

    /**
     * Plain text with a space between blocks and at line breaks; the text
     * inside a block stays together (marks split it into nodes).
     *
     * @param  array<string, mixed>  $node
     */
    private static function lineText(array $node): string
    {
        if (($node['type'] ?? null) === 'hardBreak') {
            return ' ';
        }

        $inline = in_array($node['type'] ?? null, ['paragraph', 'heading', 'codeBlock'], true);
        $parts = [];

        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            if (is_array($child)) {
                $parts[] = self::lineText($child);
            }
        }

        return (is_string($node['text'] ?? null) ? $node['text'] : '').implode($inline ? '' : ' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function normalizeNode(array $node): array
    {
        if (isset($node['content'])) {
            $node['content'] = array_map(self::normalizeNode(...), $node['content']);
        }

        if (isset($node['marks'])) {
            $node['marks'] = array_map(
                fn (array $mark) => $mark['type'] === 'link'
                    ? ['type' => 'link', 'attrs' => ['href' => $mark['attrs']['href']]]
                    : ['type' => $mark['type']],
                $node['marks'],
            );
        }

        return $node;
    }

    /**
     * @return array{0: string, 1: array<string, int|string>}|null
     */
    private function checkNode(mixed $node, int $depth, string $parent = ''): ?array
    {
        if (! is_array($node) || ! is_string($node['type'] ?? null)) {
            return ['invalid_structure', []];
        }

        $type = $node['type'];

        if (! array_key_exists($type, $this->children())) {
            return ['node_not_allowed', ['type' => $type]];
        }

        if ($depth > self::MAX_DEPTH) {
            return ['too_deep', ['max' => self::MAX_DEPTH]];
        }

        if (++$this->nodeCount > ($this->isDocument() ? self::DOCUMENT_MAX_NODES : self::MAX_NODES)) {
            return ['too_large', []];
        }

        if (array_diff(array_keys($node), self::NODE_KEYS) !== []) {
            return ['invalid_structure', []];
        }

        $error = $this->checkAttrs($type, $node['attrs'] ?? null)
            ?? $this->checkText($type, $node)
            ?? $this->checkMarks($type, $node['marks'] ?? null, $parent);

        if ($error !== null) {
            return $error;
        }

        return $this->checkContent($type, $node['content'] ?? null, $depth);
    }

    /**
     * @return array{0: string, 1: array<string, int|string>}|null
     */
    private function checkContent(string $type, mixed $content, int $depth): ?array
    {
        if ($content === null) {
            return null;
        }

        $allowed = $this->children()[$type];

        if ($allowed === null || ! is_array($content) || ! array_is_list($content)) {
            return ['invalid_structure', []];
        }

        if ($this->isDocument() && ($error = $this->checkDocumentContent($type, $content)) !== null) {
            return $error;
        }

        foreach ($content as $child) {
            $childType = is_array($child) ? ($child['type'] ?? null) : null;

            if (is_string($childType) && array_key_exists($childType, $this->children()) && ! in_array($childType, $allowed, true)) {
                return ['invalid_structure', []];
            }

            $error = $this->checkNode($child, $depth + 1, $type);

            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: array<string, int|string>}|null
     */
    private function checkAttrs(string $type, mixed $attrs): ?array
    {
        if ($type === 'heading') {
            $level = is_array($attrs) ? ($attrs['level'] ?? null) : null;

            return in_array($level, $this->isDocument() ? [1, 2, 3, 4] : [2, 3], true) && array_keys($attrs) === ['level']
                ? null
                : [$this->isDocument() ? 'heading_level_document' : 'heading_level', []];
        }

        if ($this->isDocument() && ($error = $this->checkDocumentAttrs($type, $attrs)) !== false) {
            return $error;
        }

        if ($attrs === null || $attrs === []) {
            return null;
        }

        if ($type === 'orderedList' && is_array($attrs) && array_diff(array_keys($attrs), ['start', 'type']) === []) {
            $start = $attrs['start'] ?? 1;
            $listType = $attrs['type'] ?? null;

            if (is_int($start) && $start >= 0 && ($listType === null || in_array($listType, self::ORDERED_LIST_TYPES, true))) {
                return null;
            }
        }

        return ['invalid_structure', []];
    }

    /**
     * What only the profile "Document" asks of the children: a list item begins with a paragraph; a table cell holds
     * exactly one paragraph, and that holds text only (no line break).
     *
     * @param  array<int, mixed>  $content
     * @return array{0: string, 1: array<string, int|string>}|null
     */
    private function checkDocumentContent(string $type, array $content): ?array
    {
        if (in_array($type, ['listItem', 'taskItem'], true) && $content !== [] && (is_array($content[0]) ? ($content[0]['type'] ?? null) : null) !== 'paragraph') {
            return ['invalid_structure', []];
        }

        if (in_array($type, ['tableHeader', 'tableCell'], true)) {
            $paragraph = $content[0] ?? null;
            $inline = is_array($paragraph) && is_array($paragraph['content'] ?? null) ? $paragraph['content'] : [];

            if (count($content) !== 1 || array_filter($inline, static fn (mixed $node): bool => ! is_array($node) || ($node['type'] ?? null) !== 'text') !== []) {
                return ['table_cell', []];
            }
        }

        return null;
    }

    /**
     * Attributes of the nodes only the profile "Document" has. False: not one of those, the common rules go on.
     *
     * @return array{0: string, 1: array<string, int|string>}|null|false
     */
    private function checkDocumentAttrs(string $type, mixed $attrs): array|null|false
    {
        if ($type === 'codeBlock') {
            $language = is_array($attrs) ? ($attrs['language'] ?? null) : null;

            return ($attrs === null || (is_array($attrs) && array_diff(array_keys($attrs), ['language']) === []))
                && ($language === null || (is_string($language) && preg_match(self::CODE_LANGUAGE, $language) === 1))
                ? null
                : ['invalid_structure', []];
        }

        if ($type === 'taskItem') {
            return $attrs === null || (is_array($attrs) && array_diff(array_keys($attrs), ['checked']) === [] && is_bool($attrs['checked'] ?? false))
                ? null
                : ['invalid_structure', []];
        }

        if ($type === 'tableCell' || $type === 'tableHeader') {
            $attrs ??= [];

            // The editor sends its defaults; merged cells, widths and alignment are not part of the profile.
            return is_array($attrs) && array_diff(array_keys($attrs), ['colspan', 'rowspan', 'colwidth', 'align']) === []
                && ($attrs['colspan'] ?? 1) === 1 && ($attrs['rowspan'] ?? 1) === 1
                && ($attrs['colwidth'] ?? null) === null && ($attrs['align'] ?? null) === null
                ? null
                : ['table_cell', []];
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{0: string, 1: array<string, int|string>}|null
     */
    private function checkText(string $type, array $node): ?array
    {
        if ($type !== 'text') {
            return array_key_exists('text', $node) ? ['invalid_structure', []] : null;
        }

        if (! is_string($node['text'] ?? null) || $node['text'] === '') {
            return ['invalid_structure', []];
        }

        $this->textLength += mb_strlen($node['text']);

        return null;
    }

    /**
     * @return array{0: string, 1: array<string, int|string>}|null
     */
    private function checkMarks(string $type, mixed $marks, string $parent = ''): ?array
    {
        if ($marks === null) {
            return null;
        }

        // The text of a code block is plain.
        if ($parent === 'codeBlock') {
            return ['invalid_structure', []];
        }

        if ($type !== 'text' || ! is_array($marks) || ! array_is_list($marks)) {
            return ['invalid_structure', []];
        }

        foreach ($marks as $mark) {
            $markType = is_array($mark) ? ($mark['type'] ?? null) : null;

            if (! is_string($markType) || array_diff(array_keys($mark), ['type', 'attrs']) !== []) {
                return ['invalid_structure', []];
            }

            if (! in_array($markType, $this->isDocument() ? self::DOCUMENT_MARKS : self::MARKS, true)) {
                return ['mark_not_allowed', ['type' => $markType]];
            }

            $error = $markType === 'link'
                ? $this->checkLink($mark['attrs'] ?? null)
                : (empty($mark['attrs']) ? null : ['invalid_structure', []]);

            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: array<string, int|string>}|null
     */
    private function checkLink(mixed $attrs): ?array
    {
        if (! is_array($attrs) || array_diff(array_keys($attrs), self::LINK_ATTRS) !== []) {
            return ['invalid_structure', []];
        }

        foreach (['target', 'rel', 'class'] as $optional) {
            if (isset($attrs[$optional]) && ! is_string($attrs[$optional])) {
                return ['invalid_structure', []];
            }
        }

        return self::isAllowedHref($attrs['href'] ?? null) ? null : ['link_not_allowed', []];
    }

    private static function isAllowedHref(mixed $href): bool
    {
        if (! is_string($href) || $href === '' || strlen($href) > 2048 || preg_match('/[\s\x00-\x1F\x7F]/', $href)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        if (! in_array($scheme, self::LINK_SCHEMES, true)) {
            return false;
        }

        return $scheme === 'mailto'
            ? strlen($href) > strlen('mailto:')
            : is_string(parse_url($href, PHP_URL_HOST)) && parse_url($href, PHP_URL_HOST) !== '';
    }
}
