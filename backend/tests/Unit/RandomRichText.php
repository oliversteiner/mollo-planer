<?php

namespace Tests\Unit;

/**
 * Random valid TipTap documents (blocks of the whitelist, every mark, text
 * full of characters that mean something in Markdown) and the form a
 * document takes after writing it as Markdown and reading it again.
 *
 * Marked text starts and ends with a letter: Markdown cannot read bold or
 * italic around text that starts or ends with a punctuation mark when a word
 * touches it (`a**.**b`), whichever way it is written. The same holds for bold or
 * italic around inline code (its backtick is punctuation).
 */
final class RandomRichText
{
    private const PIECES = ['a', 'b', 'Wort', 'x y', 'é', 'ü', '1', '12.', '*', '_', '[', ']', '(', ')', '\\', '`', '<', '>', '&', '&amp;', '#', '-', '+', '=', '~', '|', '!', ':', '"', "'", '.', ',', 'https://x.ch', '  ', ' '];

    private const HREFS = ['https://a.ch/x', 'https://a.ch/Foo_(Bar)', 'mailto:a@b.ch', 'https://a.ch/x)y'];

    private const MARK_ORDER = ['bold', 'italic', 'code', 'link'];

    public function __construct(int $seed)
    {
        mt_srand($seed);
    }

    /**
     * @return array<string, mixed>
     */
    public function document(): array
    {
        return ['type' => 'doc', 'content' => $this->blocks(0, mt_rand(1, 4))];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blocks(int $depth, int $count): array
    {
        return array_map(fn (): array => $this->block($depth), range(1, $count));
    }

    /**
     * @return array<string, mixed>
     */
    private function block(int $depth): array
    {
        $kinds = ['paragraph', 'paragraph', 'paragraph', 'paragraph', 'heading'];

        if ($depth < 3) {
            array_push($kinds, 'bulletList', 'bulletList', 'orderedList', 'orderedList');
        }
        if ($depth < 2) {
            $kinds[] = 'blockquote';
        }

        $kind = $kinds[mt_rand(0, count($kinds) - 1)];

        return match ($kind) {
            'heading' => ['type' => 'heading', 'attrs' => ['level' => mt_rand(2, 3)], 'content' => $this->inline(false)],
            'blockquote' => ['type' => 'blockquote', 'content' => $this->blocks($depth + 1, mt_rand(1, 3))],
            'bulletList', 'orderedList' => ['type' => $kind, 'content' => $this->items($depth)],
            default => ['type' => 'paragraph', 'content' => $this->inline(true)],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(int $depth): array
    {
        $items = [];

        for ($i = mt_rand(1, 4); $i > 0; $i--) {
            $items[] = ['type' => 'listItem', 'content' => $this->blocks($depth + 1, mt_rand(1, 2))];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inline(bool $breaks): array
    {
        $nodes = [];

        for ($i = mt_rand(0, 5); $i > 0; $i--) {
            $node = $breaks && mt_rand(0, 9) === 0 ? ['type' => 'hardBreak'] : $this->text();

            if (self::boldOrItalicCode($node)) {
                $nodes[] = ['type' => 'text', 'text' => ' '];
                array_push($nodes, $node, ['type' => 'text', 'text' => ' ']);
            } else {
                $nodes[] = $node;
            }
        }

        return self::separateOverlaps($nodes);
    }

    /**
     * Bold and italic that overlap without nesting and touch cannot be read back (`**a*b****c*`).
     * Where the writer would have to open a mark again, a space is put in between.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private static function separateOverlaps(array $nodes): array
    {
        $out = [];
        $open = [];
        $href = null;
        $touching = 0;
        $previous = null;

        foreach ($nodes as $node) {
            $types = array_column($node['marks'] ?? [], 'type');
            $emphasis = array_values(array_intersect(['bold', 'italic'], $types));

            // Three touching runs of different bold/italic (`**a***b**c***`) are ambiguous as well.
            if ($emphasis === []) {
                $touching = 0;
            } elseif ($previous !== null && $previous !== $emphasis) {
                $touching++;
            } else {
                $touching = max($touching, 1);
            }
            $previous = $emphasis === [] ? null : $emphasis;

            if ($touching >= 2) {
                $out[] = ['type' => 'text', 'text' => ' '];
                $open = [];
                $touching = 1;
            }

            $link = ($node['marks'] ?? []) === [] ? null : (array_values(array_filter($node['marks'], static fn (array $mark): bool => $mark['type'] === 'link'))[0]['attrs']['href'] ?? null);

            if ($node['type'] === 'hardBreak' || $link !== $href) {
                $open = [];
                $href = $link;
            }

            $wanted = array_values(array_intersect(['bold', 'italic'], $types));
            $cut = null;
            foreach ($open as $position => $mark) {
                if (! in_array($mark, $wanted, true)) {
                    $cut = $position;

                    break;
                }
            }

            if ($cut !== null && array_intersect(array_slice($open, $cut), $wanted) !== []) {
                $out[] = ['type' => 'text', 'text' => ' '];
                $open = [];
                $href = null;
                $href = $link;
            } elseif ($cut !== null) {
                $open = array_slice($open, 0, $cut);
            }

            foreach ($wanted as $mark) {
                if (! in_array($mark, $open, true)) {
                    $open[] = $mark;
                }
            }

            $out[] = $node;
        }

        return $out;
    }

    /**
     * Bold or italic around code that touches a word cannot be read (`Wort**`x`**`): the
     * backtick counts as punctuation. In practice it stands between spaces.
     *
     * @param  array<string, mixed>  $node
     */
    private static function boldOrItalicCode(array $node): bool
    {
        $types = array_column($node['marks'] ?? [], 'type');

        return in_array('code', $types, true) && array_intersect($types, ['bold', 'italic']) !== [];
    }

    /**
     * @return array<string, mixed>
     */
    private function text(): array
    {
        if (mt_rand(0, 2) > 0) {
            return ['type' => 'text', 'text' => $this->pieces(mt_rand(1, 4))];
        }

        $marks = [];
        foreach (['bold', 'italic', 'code'] as $name) {
            if (mt_rand(0, 2) === 0) {
                $marks[] = ['type' => $name];
            }
        }
        if (mt_rand(0, 2) === 0) {
            $marks[] = ['type' => 'link', 'attrs' => ['href' => self::HREFS[mt_rand(0, count(self::HREFS) - 1)]]];
        }
        if ($marks === []) {
            $marks[] = ['type' => ['bold', 'italic', 'code'][mt_rand(0, 2)]];
        }
        shuffle($marks);

        $edge = ['a', 'Wort', 'x1', 'é'][mt_rand(0, 3)];
        $core = $edge.$this->pieces(mt_rand(0, 3)).$edge;
        // A paragraph that starts with a link whose code holds "]:" reads as a link reference
        // definition (`[a`b]: c`), which CommonMark takes out of the text.
        $core = in_array('code', array_column($marks, 'type'), true) ? str_replace(']', ')', $core) : $core;
        $text = (mt_rand(0, 3) === 0 ? ' ' : '').$core.(mt_rand(0, 3) === 0 ? ' ' : '');

        return ['type' => 'text', 'text' => $text, 'marks' => $marks];
    }

    private function pieces(int $count): string
    {
        $text = '';

        for ($i = 0; $i < $count; $i++) {
            $text .= self::PIECES[mt_rand(0, count(self::PIECES) - 1)];
        }

        return $text;
    }

    // ─── What reading the written Markdown gives back ────────────

    /**
     * @param  array<string, mixed>  $doc
     * @return array<string, mixed>
     */
    public static function normalize(array $doc): array
    {
        return ['type' => 'doc', 'content' => self::normalizeBlocks($doc['content'] ?? [])];
    }

    /**
     * Empty blocks vanish, lists of the same kind side by side become one.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    private static function normalizeBlocks(array $blocks): array
    {
        $out = [];

        foreach ($blocks as $block) {
            $normalized = self::normalizeBlock($block);

            if ($normalized === null) {
                continue;
            }

            $last = $out === [] ? null : $out[array_key_last($out)];

            if ($last !== null && $last['type'] === $normalized['type'] && in_array($last['type'], ['bulletList', 'orderedList'], true)) {
                $out[array_key_last($out)]['content'] = [...$last['content'], ...$normalized['content']];
            } else {
                $out[] = $normalized;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>|null
     */
    private static function normalizeBlock(array $block): ?array
    {
        switch ($block['type']) {
            case 'paragraph':
                $inline = self::normalizeInline($block['content'] ?? []);

                return $inline === [] ? null : ['type' => 'paragraph', 'content' => $inline];

            case 'heading':
                $inline = self::normalizeInline($block['content'] ?? []);

                return $inline === [] ? null : ['type' => 'heading', 'attrs' => ['level' => $block['attrs']['level']], 'content' => $inline];

            case 'blockquote':
                $content = self::normalizeBlocks($block['content'] ?? []);

                return $content === [] ? null : ['type' => 'blockquote', 'content' => $content];

            default:
                $items = [];
                foreach ($block['content'] ?? [] as $item) {
                    $content = self::normalizeBlocks($item['content'] ?? []);

                    if ($content !== []) {
                        $items[] = ['type' => 'listItem', 'content' => $content];
                    }
                }

                return $items === [] ? null : ['type' => $block['type'], 'content' => $items];
        }
    }

    /**
     * Spaces at the edge of marked text lose their marks, spaces at the edge of a line vanish.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private static function normalizeInline(array $nodes): array
    {
        $split = [];

        foreach ($nodes as $node) {
            if ($node['type'] === 'hardBreak') {
                $split[] = ['type' => 'hardBreak'];

                continue;
            }

            $marks = self::sortedMarks($node['marks'] ?? []);
            preg_match('/^( *)(.*?)( *)$/su', $node['text'], $parts);
            [, $before, $core, $after] = $parts;

            foreach ($marks === [] || $core === '' ? [[$node['text'], []]] : [[$before, []], [$core, $marks], [$after, []]] as [$text, $textMarks]) {
                if ($text !== '') {
                    $split[] = $textMarks === [] ? ['type' => 'text', 'text' => $text] : ['type' => 'text', 'text' => $text, 'marks' => $textMarks];
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

        $lines = [];
        $lineStart = true;
        foreach ($joined as $node) {
            if ($node['type'] === 'hardBreak') {
                $lines[] = $node;
                $lineStart = true;

                continue;
            }
            if ($lineStart && ! isset($node['marks'])) {
                $node['text'] = ltrim($node['text'], ' ');
            }
            if ($node['text'] !== '') {
                $lines[] = $node;
                $lineStart = false;
            }
        }
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if ($lines[$i]['type'] === 'text' && ! isset($lines[$i]['marks']) && ($i === count($lines) - 1 || $lines[$i + 1]['type'] === 'hardBreak')) {
                $lines[$i]['text'] = rtrim($lines[$i]['text'], ' ');
                if ($lines[$i]['text'] === '') {
                    array_splice($lines, $i, 1);
                }
            }
        }

        // A line break at the end of the paragraph is not written.
        while ($lines !== [] && $lines[array_key_last($lines)]['type'] === 'hardBreak') {
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $marks
     * @return list<array<string, mixed>>
     */
    private static function sortedMarks(array $marks): array
    {
        $byType = [];
        foreach ($marks as $mark) {
            $byType[$mark['type']] = $mark;
        }

        $sorted = [];
        foreach (self::MARK_ORDER as $type) {
            if (isset($byType[$type])) {
                $sorted[] = $type === 'link' ? ['type' => 'link', 'attrs' => ['href' => $byType[$type]['attrs']['href']]] : ['type' => $type];
            }
        }

        return $sorted;
    }
}
