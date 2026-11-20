<?php

namespace App\Planer\RichText;

/**
 * A TipTap document of the app as canonical Markdown (spec/format-v2.md
 * 6.1; the same output as tools/planer/core/richtext.mjs). The result has
 * no line break at the end. Empty paragraphs, quotes and lists are left
 * out (an item without content as well), nodes outside the whitelist too.
 *
 * Beyond the plain form, these keep what is written readable again:
 * - spaces at the edge of a marked text are written outside the marks
 *   (`**a** b`, not `**a **b`), spaces at the edge of a line are left out, and so is
 *   a line break at the end of a paragraph (in a heading it is a space);
 * - a text that would read as Markdown at the start of a line gets a backslash
 *   (`\# x`, `1\. x`, `\- x`, `\> x`, a table row `-|`), and `&` where it would read as an entity;
 * - a continuation of an item is indented by the width of its marker
 *   (3 spaces for `1. `, 4 for `10. `);
 * - blocks of an item are separated by a blank line, except a list after a
 *   paragraph or heading;
 * - bold and italic are opened and closed only where they change. Marks that
 *   overlap without nesting and touch (bold over A and B, italic over B and C,
 *   no space between) cannot be read back: CommonMark cannot tell `****` apart.
 *   In practice a space or other text stands between;
 * - `~~` is written `\~\~` (a single tilde stays as it is);
 * - an escaped backtick directly before inline code is written as `&#96;`;
 * - inline code uses a longer fence when it holds a backtick, a link
 *   address with spaces or unbalanced brackets is written in <…>.
 *
 * The profile "Document" (spec/format-v2.md §6.2) adds headings 1 to 4, a code block (the fence is one backtick
 * longer than the longest run in the code, the language follows it), a rule (`---`), a table (the first row is
 * the header row, a `|` in a cell is written `\|`), a task list (`- [ ]`, `- [x]`; a list of the other kind right
 * after it starts with `*`, or both would read as one list) and strikethrough (`~~`).
 */
final class TiptapToMarkdown
{
    private const MARK_ORDER = ['bold', 'italic', 'code', 'link'];

    /** The marks of the profile "Document", in the order they are written. */
    private const DOCUMENT_MARK_ORDER = ['bold', 'italic', 'strike', 'code', 'link'];

    private const LIST_TYPES = ['bulletList', 'orderedList', 'taskList'];

    /** The profile of the conversion running now (the methods are static; convert() sets and restores it). */
    private static Profile $profile = Profile::Description;

    /**
     * @param  array<string, mixed>  $doc
     */
    public static function convert(array $doc, Profile $profile = Profile::Description): string
    {
        $before = self::$profile;
        self::$profile = $profile;

        try {
            return rtrim(self::blocks(self::content($doc)), "\n");
        } finally {
            self::$profile = $before;
        }
    }

    private static function isDocument(): bool
    {
        return self::$profile === Profile::Document;
    }

    /**
     * @return list<string>
     */
    private static function markOrder(): array
    {
        return self::isDocument() ? self::DOCUMENT_MARK_ORDER : self::MARK_ORDER;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<array<string, mixed>>
     */
    private static function content(array $node): array
    {
        $content = $node['content'] ?? [];

        return is_array($content) ? array_values(array_filter($content, 'is_array')) : [];
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private static function blocks(array $blocks, bool $inItem = false): string
    {
        $out = '';
        $previous = null;

        foreach ($blocks as $block) {
            $text = self::block($block, $previous);

            if ($text === '') {
                continue;
            }

            if ($previous !== null) {
                $out .= $inItem && self::joinsTight($previous, $block) ? "\n" : "\n\n";
            }

            $out .= $text;
            $previous = $block;
        }

        return $out;
    }

    /**
     * In an item, a list directly after a paragraph or heading needs no blank line.
     *
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $next
     */
    private static function joinsTight(array $previous, array $next): bool
    {
        return in_array($next['type'] ?? '', self::LIST_TYPES, true) && in_array($previous['type'] ?? '', ['paragraph', 'heading'], true);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private static function block(array $block, ?array $previous = null): string
    {
        $type = $block['type'] ?? '';

        if (self::isDocument() && in_array($type, ['horizontalRule', 'codeBlock', 'table', 'taskList'], true)) {
            return self::documentBlock($block, $previous);
        }

        switch ($type) {
            case 'heading':
                $level = (int) ($block['attrs']['level'] ?? 2);
                $text = self::inline(self::content($block), true);
                [$lowest, $highest] = self::isDocument() ? [1, 4] : [2, 3];

                return $text === '' ? '' : str_repeat('#', max($lowest, min($highest, $level))).' '.$text;

            case 'paragraph':
                return self::inline(self::content($block));

            case 'blockquote':
                $inner = self::blocks(self::content($block));

                return $inner === '' ? '' : implode("\n", array_map(
                    static fn (string $line): string => $line === '' ? '>' : '> '.$line,
                    explode("\n", $inner),
                ));

            case 'bulletList':
                // Right after a task list another marker starts a new list (else both read as one).
                $mark = self::isDocument() && ($previous['type'] ?? '') === 'taskList' ? '* ' : '- ';

                return self::items($block, static fn (int $index): string => $mark);

            case 'orderedList':
                return self::items($block, static fn (int $index): string => ($index + 1).'. ');

            default:
                return '';
        }
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>|null  $previous
     */
    private static function documentBlock(array $block, ?array $previous): string
    {
        return match ($block['type']) {
            'horizontalRule' => '---',
            'codeBlock' => self::codeBlock($block),
            'table' => self::table($block),
            default => self::taskItems($block, ($previous['type'] ?? '') === 'bulletList' ? '*' : '-'),
        };
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private static function codeBlock(array $block): string
    {
        $text = '';

        foreach (self::content($block) as $node) {
            $text .= (string) ($node['text'] ?? '');
        }

        $longest = 2;

        if (preg_match_all('/`+/', $text, $runs) > 0) {
            $longest = max($longest, ...array_map('strlen', $runs[0]));
        }

        $fence = str_repeat('`', $longest + 1);
        $language = (string) preg_replace('/[^A-Za-z0-9_+#.-]/', '', (string) ($block['attrs']['language'] ?? ''));

        return $fence.$language."\n".$text.($text === '' ? '' : "\n").$fence;
    }

    /**
     * @param  array<string, mixed>  $table
     */
    private static function table(array $table): string
    {
        $rows = [];

        foreach (self::content($table) as $row) {
            $cells = [];

            foreach (self::content($row) as $cell) {
                $paragraphs = array_map(static fn (array $paragraph): string => self::inline(self::content($paragraph), true), self::content($cell));
                $cells[] = str_replace('|', '\\|', implode(' ', $paragraphs));
            }

            $rows[] = $cells;
        }

        if ($rows === []) {
            return '';
        }

        $width = max(array_map('count', $rows));
        $line = static fn (array $cells): string => '| '.implode(' | ', array_pad($cells, $width, '')).' |';
        $out = [$line($rows[0]), '| '.implode(' | ', array_fill(0, $width, '---')).' |'];

        foreach (array_slice($rows, 1) as $row) {
            $out[] = $line($row);
        }

        return implode("\n", $out);
    }

    /**
     * @param  array<string, mixed>  $list
     */
    private static function taskItems(array $list, string $bullet): string
    {
        $lines = [];

        foreach (self::content($list) as $item) {
            $text = self::blocks(self::content($item), true);

            if ($text === '') {
                continue;
            }

            $first = $bullet.(($item['attrs']['checked'] ?? false) === true ? ' [x] ' : ' [ ] ');

            foreach (explode("\n", $text) as $number => $row) {
                $lines[] = $number === 0 ? $first.$row : ($row === '' ? '' : '  '.$row);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $list
     * @param  callable(int): string  $marker
     */
    private static function items(array $list, callable $marker): string
    {
        $lines = [];
        $written = 0;

        foreach (self::content($list) as $item) {
            $text = self::blocks(self::content($item), true);

            if ($text === '') {
                continue;
            }

            $first = $marker($written++);
            $rest = str_repeat(' ', strlen($first));

            foreach (explode("\n", $text) as $number => $line) {
                $lines[] = $number === 0 ? $first.$line : ($line === '' ? '' : $rest.$line);
            }
        }

        return implode("\n", $lines);
    }

    // ─── Inline ──────────────────────────────────────────────────

    /**
     * Bold and italic are opened and closed only where they change between two texts, so
     * `**a*b***` stays one run and no two runs of asterisks touch more than needed; a link
     * wraps the texts that share its address.
     *
     * @param  list<array<string, mixed>>  $nodes
     */
    private static function inline(array $nodes, bool $heading = false): string
    {
        $nodes = self::prepare($nodes);
        $out = '';
        $open = [];
        $href = null;
        $atLineStart = true;
        $last = count($nodes) - 1;

        foreach ($nodes as $index => $node) {
            if (($node['type'] ?? '') === 'hardBreak') {
                $out .= self::closeAll($open, $href).($heading ? ' ' : "\\\n");
                $atLineStart = true;

                continue;
            }

            $marks = array_column($node['marks'] ?? [], null, 'type');
            $wantedHref = isset($marks['link']) ? $marks['link']['attrs']['href'] : null;

            if ($wantedHref !== $href) {
                $out .= self::closeAll($open, $href);
                $out .= $wantedHref === null ? '' : '[';
                $href = $wantedHref;
            }

            $out .= self::change($open, array_values(array_filter(['bold', 'italic', 'strike'], static fn (string $mark): bool => isset($marks[$mark]) && ($mark !== 'strike' || self::isDocument()))));

            $nextTypes = array_column($nodes[$index + 1]['marks'] ?? [], 'type');
            $out .= self::text(
                $node['text'],
                isset($marks['code']),
                $atLineStart && $marks === [],
                $heading && $index === $last && $marks === [],
                $marks === [] && in_array('code', $nextTypes, true),
                $marks === [] && in_array('link', $nextTypes, true),
            );
            $atLineStart = false;
        }

        return $out.self::closeAll($open, $href);
    }

    /**
     * Closes what is open, bold and italic first, then the link.
     *
     * @param  list<string>  $open
     */
    private static function closeAll(array &$open, ?string &$href): string
    {
        $out = '';

        foreach (array_reverse($open) as $mark) {
            $out .= self::delimiter($mark);
        }
        $open = [];

        if ($href !== null) {
            $out .= ']('.self::destination($href).')';
            $href = null;
        }

        return $out;
    }

    /**
     * Moves from the open marks to the wanted ones: closes what is no longer wanted (and what
     * was opened after it, which is opened again), then opens what is new.
     *
     * @param  list<string>  $open
     * @param  list<string>  $wanted
     */
    private static function change(array &$open, array $wanted): string
    {
        $out = '';
        $cut = null;

        foreach ($open as $position => $mark) {
            if (! in_array($mark, $wanted, true)) {
                $cut = $position;

                break;
            }
        }

        if ($cut !== null) {
            $closed = array_splice($open, $cut);

            foreach (array_reverse($closed) as $mark) {
                $out .= self::delimiter($mark);
            }
            foreach ($closed as $mark) {
                if (in_array($mark, $wanted, true)) {
                    $out .= self::delimiter($mark);
                    $open[] = $mark;
                }
            }
        }

        foreach ($wanted as $mark) {
            if (! in_array($mark, $open, true)) {
                $out .= self::delimiter($mark);
                $open[] = $mark;
            }
        }

        return $out;
    }

    private static function delimiter(string $mark): string
    {
        return match ($mark) {
            'bold' => '**',
            'strike' => '~~',
            default => '*',
        };
    }

    /**
     * Text nodes only, marks in a fixed order; spaces at the edge of a marked text become
     * plain, spaces at the edge of a line are dropped; equal neighbours are joined.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private static function prepare(array $nodes): array
    {
        $split = [];

        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'hardBreak') {
                $split[] = ['type' => 'hardBreak'];

                continue;
            }
            if (($node['type'] ?? '') !== 'text' || ! is_string($node['text'] ?? null) || $node['text'] === '') {
                continue;
            }

            $text = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $node['text']);
            $marks = self::sortedMarks($node['marks'] ?? []);

            if ($marks === []) {
                $split[] = ['type' => 'text', 'text' => $text];

                continue;
            }

            preg_match('/^([ \p{Zs}]*)(.*?)([ \p{Zs}]*)$/su', $text, $parts);
            [, $before, $core, $after] = $parts;

            if ($core === '') {
                $split[] = ['type' => 'text', 'text' => $text];

                continue;
            }

            foreach ([[$before, []], [$core, $marks], [$after, []]] as [$piece, $pieceMarks]) {
                if ($piece !== '') {
                    $split[] = $pieceMarks === [] ? ['type' => 'text', 'text' => $piece] : ['type' => 'text', 'text' => $piece, 'marks' => $pieceMarks];
                }
            }
        }

        $joined = [];
        foreach ($split as $node) {
            $last = $joined === [] ? null : $joined[array_key_last($joined)];

            if ($last !== null && $node['type'] === 'text' && $last['type'] === 'text' && ($last['marks'] ?? []) === ($node['marks'] ?? [])) {
                $joined[array_key_last($joined)]['text'] .= $node['text'];
            } else {
                $joined[] = $node;
            }
        }

        return self::trimLines($joined);
    }

    /**
     * Drops plain spaces at the start and end of the paragraph and next to a line break.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private static function trimLines(array $nodes): array
    {
        $lineStart = true;
        $trimmed = [];

        foreach ($nodes as $node) {
            if ($node['type'] === 'hardBreak') {
                $trimmed[] = $node;
                $lineStart = true;

                continue;
            }

            if ($lineStart && ! isset($node['marks'])) {
                $node['text'] = ltrim($node['text'], ' ');
            }

            if ($node['text'] !== '') {
                $trimmed[] = $node;
                $lineStart = false;
            }
        }

        while ($trimmed !== [] && $trimmed[array_key_last($trimmed)]['type'] === 'hardBreak') {
            array_pop($trimmed);
        }

        for ($i = count($trimmed) - 1; $i >= 0; $i--) {
            $isEdge = $i === count($trimmed) - 1 || $trimmed[$i + 1]['type'] === 'hardBreak';

            if ($trimmed[$i]['type'] === 'text' && ! isset($trimmed[$i]['marks']) && $isEdge) {
                $trimmed[$i]['text'] = rtrim($trimmed[$i]['text'], ' ');

                if ($trimmed[$i]['text'] === '') {
                    array_splice($trimmed, $i, 1);
                }
            }
        }

        return $trimmed;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function sortedMarks(mixed $marks): array
    {
        $byType = [];

        foreach (is_array($marks) ? $marks : [] as $mark) {
            if (is_array($mark) && in_array($mark['type'] ?? '', self::markOrder(), true)) {
                $byType[$mark['type']] = $mark['type'] === 'link' ? ['type' => 'link', 'attrs' => ['href' => (string) ($mark['attrs']['href'] ?? '')]] : ['type' => $mark['type']];
            }
        }

        return array_values(array_filter(array_map(static fn (string $type): ?array => $byType[$type] ?? null, self::markOrder())));
    }

    private static function text(string $text, bool $isCode, bool $atLineStart, bool $endsHeading, bool $beforeCode, bool $beforeLink): string
    {
        if ($isCode) {
            return self::code($text);
        }

        $text = self::escape($text, $atLineStart, $endsHeading);

        // An escaped backtick right before the fence of a code would run into it: `\`` + `` ` ``.
        if ($beforeCode) {
            $text = (string) preg_replace('/\\\\`$/', '&#96;', $text);
        }

        // A "!" right before a link would make an image of it.
        if ($beforeLink && str_ends_with($text, '!')) {
            $text = substr($text, 0, -1).'\\!';
        }

        return $text;
    }

    private static function escape(string $text, bool $atLineStart, bool $endsHeading): string
    {
        $text = (string) preg_replace('/[\\\\`*_\[\]<]|&(?=#?[A-Za-z0-9]+;)|~(?=~)|(?<=~)~/', '\\\\$0', $text);

        if ($atLineStart) {
            $text = (string) preg_replace(
                [
                    '/^(#{1,6})(?=[ \t]|$)/',
                    '/^>/',
                    '/^([-+])(?=[ \t]|$)/',
                    '/^(\d{1,9})([.)])(?=[ \t]|$)/',
                    '/^([-=]+)(?=[ \t]*$)/',
                    '/^(:?-+:?)(?=[ \t]*\|)/',
                    '/^\|/',
                ],
                ['\\\\$1', '\\\\>', '\\\\$1', '$1\\\\$2', '\\\\$1', '\\\\$1', '\\\\|'],
                $text,
            );
        }

        if ($endsHeading) {
            $text = (string) preg_replace('/(?<![^ \t])#+$/', '\\\\$0', $text);
        }

        return $text;
    }

    private static function code(string $text): string
    {
        $longest = 0;
        if (preg_match_all('/`+/', $text, $runs) > 0) {
            $longest = max(array_map('strlen', $runs[0]));
        }

        $fence = str_repeat('`', $longest + 1);
        $needsPad = str_starts_with($text, '`') || str_ends_with($text, '`')
            || (str_starts_with($text, ' ') && str_ends_with($text, ' ') && trim($text) !== '');
        $pad = $needsPad ? ' ' : '';

        return $fence.$pad.$text.$pad.$fence;
    }

    /**
     * An address with spaces, angle brackets or unbalanced parentheses goes into <…>.
     */
    private static function destination(string $href): string
    {
        $href = str_replace(["\r", "\n"], '', $href);
        $depth = 0;
        $balanced = true;

        foreach (str_split($href) as $char) {
            $depth += $char === '(' ? 1 : ($char === ')' ? -1 : 0);
            $balanced = $balanced && $depth >= 0;
        }

        if ($href !== '' && $balanced && $depth === 0 && preg_match('/[\s<>\\\\]/', $href) !== 1) {
            return $href;
        }

        $escaped = str_replace(['\\', '<', '>'], ['\\\\', '%3C', '%3E'], $href);

        return '<'.$escaped.'>';
    }
}
