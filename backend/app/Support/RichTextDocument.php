<?php

namespace App\Support;

/**
 * TipTap documents (JSON) for descriptions and comments.
 *
 * The whitelist here must match the editor configuration in the frontend:
 * paragraph, heading (levels 2 and 3), bullet list, ordered list, list item,
 * hard break, blockquote, text; marks bold, italic, inline code and link (http, https,
 * mailto). HTML is never stored or produced.
 */
final class RichTextDocument
{
    public const MAX_TEXT_LENGTH = 20000;

    public const MAX_DEPTH = 10;

    /** Guards against huge documents made of empty nodes. */
    public const MAX_NODES = 5000;

    private const BLOCKS = ['paragraph', 'heading', 'bulletList', 'orderedList', 'blockquote'];

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

    private const NODE_KEYS = ['type', 'attrs', 'content', 'text', 'marks'];

    private const MARKS = ['bold', 'italic', 'code', 'link'];

    /** Link attributes the editor sends. Only href is kept (see normalize). */
    private const LINK_ATTRS = ['href', 'target', 'rel', 'class'];

    private const LINK_SCHEMES = ['http', 'https', 'mailto'];

    private const ORDERED_LIST_TYPES = ['1', 'a', 'A', 'i', 'I'];

    private int $nodeCount = 0;

    private int $textLength = 0;

    private function __construct() {}

    /**
     * Check a document against the whitelist.
     *
     * Returns null if valid, otherwise [message key, parameters]. The keys
     * refer to lang/{de,en}/richtext.php.
     *
     * @return array{0: string, 1: array<string, int|string>}|null
     */
    public static function validate(mixed $document): ?array
    {
        if (! is_array($document) || ($document['type'] ?? null) !== 'doc') {
            return ['not_a_document', []];
        }

        $checker = new self;
        $error = $checker->checkNode($document, 1);

        if ($error !== null) {
            return $error;
        }

        if ($checker->textLength > self::MAX_TEXT_LENGTH) {
            return ['too_long', ['max' => self::MAX_TEXT_LENGTH]];
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
    private function checkNode(mixed $node, int $depth): ?array
    {
        if (! is_array($node) || ! is_string($node['type'] ?? null)) {
            return ['invalid_structure', []];
        }

        $type = $node['type'];

        if (! array_key_exists($type, self::CHILDREN)) {
            return ['node_not_allowed', ['type' => $type]];
        }

        if ($depth > self::MAX_DEPTH) {
            return ['too_deep', ['max' => self::MAX_DEPTH]];
        }

        if (++$this->nodeCount > self::MAX_NODES) {
            return ['too_large', []];
        }

        if (array_diff(array_keys($node), self::NODE_KEYS) !== []) {
            return ['invalid_structure', []];
        }

        $error = $this->checkAttrs($type, $node['attrs'] ?? null)
            ?? $this->checkText($type, $node)
            ?? $this->checkMarks($type, $node['marks'] ?? null);

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

        $allowed = self::CHILDREN[$type];

        if ($allowed === null || ! is_array($content) || ! array_is_list($content)) {
            return ['invalid_structure', []];
        }

        foreach ($content as $child) {
            $childType = is_array($child) ? ($child['type'] ?? null) : null;

            if (is_string($childType) && array_key_exists($childType, self::CHILDREN) && ! in_array($childType, $allowed, true)) {
                return ['invalid_structure', []];
            }

            $error = $this->checkNode($child, $depth + 1);

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

            return in_array($level, [2, 3], true) && array_keys($attrs) === ['level']
                ? null
                : ['heading_level', []];
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
    private function checkMarks(string $type, mixed $marks): ?array
    {
        if ($marks === null) {
            return null;
        }

        if ($type !== 'text' || ! is_array($marks) || ! array_is_list($marks)) {
            return ['invalid_structure', []];
        }

        foreach ($marks as $mark) {
            $markType = is_array($mark) ? ($mark['type'] ?? null) : null;

            if (! is_string($markType) || array_diff(array_keys($mark), ['type', 'attrs']) !== []) {
                return ['invalid_structure', []];
            }

            if (! in_array($markType, self::MARKS, true)) {
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
