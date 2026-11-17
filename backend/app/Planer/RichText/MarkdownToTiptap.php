<?php

namespace App\Planer\RichText;

use App\Support\RichTextDocument;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem;
use League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Extension\Strikethrough\Strikethrough;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\Extension\Table\TableSection;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;

/**
 * Markdown to a TipTap document of the app (spec/format-v2.md 6.1, the
 * other direction is TiptapToMarkdown).
 *
 * Only the syntax tree of league/commonmark is used, never its HTML. The
 * extension for tables and the processor for `~~` are on only to recognise
 * those constructs; footnotes, task lists, bare URLs and single tildes stay
 * plain text.
 *
 * Strict: a construct outside the whitelist is an error (InvalidMarkdown).
 * Lenient: it becomes plain text as in docs/planer-abgleich.md 9.6, with a
 * warning. Lists and quotes may hold any block (also headings and deeper
 * levels), which the editor can store as well.
 */
final class MarkdownToTiptap
{
    /**
     * Stands for an escaped asterisk while parsing, so a bare one can be told from it. A
     * punctuation mark, so that bold and italic next to it are read as next to the backslash.
     */
    private const PLACEHOLDERS = ["\u{2042}", "\u{2E3A}", "\u{2E3B}", "\u{2E3C}"];

    private const SCHEME = '~^(https?:|mailto:)~i';

    private static ?Environment $environment = null;

    /** @var array<string, Warning> */
    private array $found = [];

    /** @var list<string> */
    private array $lines;

    private string $escapedStar;

    private function __construct(private readonly string $markdown, private readonly Mode $mode)
    {
        $this->lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $markdown));
        $this->escapedStar = collect(self::PLACEHOLDERS)->first(static fn (string $mark): bool => ! str_contains($markdown, $mark)) ?? self::PLACEHOLDERS[0];
    }

    /**
     * @throws InvalidMarkdown In strict mode, for the first construct outside the whitelist (all are listed)
     */
    public static function convert(string $markdown, Mode $mode): Result
    {
        return (new self($markdown, $mode))->run();
    }

    private function run(): Result
    {
        $parsed = (new MarkdownParser(self::environment()))->parse($this->protectEscapedStars($this->markdown));
        $doc = ['type' => 'doc', 'content' => $this->blocks($parsed)];

        if (($error = RichTextDocument::validate($doc)) !== null) {
            $this->found['document_limit'] = new Warning('document_limit', 0, $error[0]);
            throw new InvalidMarkdown(array_values($this->found));
        }

        if ($this->mode === Mode::Strict && ($errors = $this->errors()) !== []) {
            throw new InvalidMarkdown($errors);
        }

        return new Result($doc, $this->mode === Mode::Strict ? [] : array_values($this->found));
    }

    /**
     * @return list<Warning>
     */
    private function errors(): array
    {
        return array_values(array_filter($this->found, fn (Warning $warning): bool => ! in_array($warning->kind, ['ordered_start', 'link_title'], true)));
    }

    private static function environment(): Environment
    {
        if (self::$environment === null) {
            self::$environment = new Environment;
            self::$environment->addExtension(new CommonMarkCoreExtension);
            self::$environment->addDelimiterProcessor(new DoubleTildeDelimiterProcessor);
            self::$environment->addExtension(new TableExtension);
        }

        return self::$environment;
    }

    /**
     * `\*` becomes a marker, `\\` stays: after parsing, every asterisk left in a text is a bare one.
     * A footnote definition (`[^1]: …`) would be read as a link reference and vanish: its bracket
     * is escaped, so it stays text.
     */
    private function protectEscapedStars(string $markdown): string
    {
        $markdown = (string) preg_replace('/^( {0,3})\[(\^[^\]\n]*\]:)/m', '$1\\[$2', $markdown);

        return (string) preg_replace_callback('/\\\\([\\\\*])/', fn (array $match): string => $match[1] === '*' ? $this->escapedStar : $match[0], $markdown);
    }

    private function restoreStars(string $text, bool $literal = false): string
    {
        return str_replace($this->escapedStar, $literal ? '\\*' : '*', $text);
    }

    private function warn(string $kind, ?int $line): void
    {
        $line ??= 1;
        $this->found[$kind.':'.$line] ??= new Warning($kind, $line, $this->excerpt($line));
    }

    private function excerpt(int $line): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $this->lines[$line - 1] ?? ''));

        return mb_strlen($text) <= 60 ? $text : rtrim(mb_substr($text, 0, 59)).'…';
    }

    // ─── Blocks ──────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     */
    private function blocks(Node $parent): array
    {
        $blocks = [];

        foreach ($parent->children() as $child) {
            array_push($blocks, ...$this->block($child));
        }

        return $blocks;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function block(Node $node): array
    {
        $line = $this->lineOf($node);

        return match (true) {
            $node instanceof Paragraph => $this->paragraph($this->inlines($node, [], $line)),
            $node instanceof Heading => $this->heading($node, $line),
            $node instanceof BlockQuote => $this->container('blockquote', $this->blocks($node)),
            $node instanceof ListBlock => $this->list($node, $line),
            $node instanceof FencedCode, $node instanceof IndentedCode => $this->code($node, $line),
            $node instanceof HtmlBlock => $this->htmlBlock($node, $line),
            $node instanceof ThematicBreak => $this->thematicBreak($line),
            $node instanceof Table => $this->table($node, $line),
            default => [],
        };
    }

    private function lineOf(Node $node): int
    {
        for ($current = $node; $current !== null; $current = $current->parent()) {
            if (method_exists($current, 'getStartLine') && $current->getStartLine() !== null) {
                return $current->getStartLine();
            }
        }

        return 1;
    }

    /**
     * @param  list<array<string, mixed>>  $inline
     * @return list<array<string, mixed>>
     */
    private function paragraph(array $inline): array
    {
        return $inline === [] ? [] : [['type' => 'paragraph', 'content' => $inline]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function heading(Heading $heading, int $line): array
    {
        $level = $heading->getLevel();

        if ($level !== 2 && $level !== 3) {
            $this->warn('heading_level', $line);
            $level = $level === 1 ? 2 : 3;
        }

        $inline = $this->inlines($heading, [], $line);

        return $inline === [] ? [] : [['type' => 'heading', 'attrs' => ['level' => $level], 'content' => $inline]];
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    private function container(string $type, array $blocks): array
    {
        return $blocks === [] ? [] : [['type' => $type, 'content' => $blocks]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function list(ListBlock $list, int $line): array
    {
        $ordered = $list->getListData()->type === ListBlock::TYPE_ORDERED;

        if ($ordered && ($list->getListData()->start ?? 1) !== 1) {
            $this->warn('ordered_start', $line);
        }

        $items = [];
        foreach ($list->children() as $item) {
            if (! $item instanceof ListItem) {
                continue;
            }
            $content = $this->blocks($item);

            if ($content !== []) {
                $items[] = ['type' => 'listItem', 'content' => $content];
            }
        }

        return $items === [] ? [] : [['type' => $ordered ? 'orderedList' : 'bulletList', 'content' => $items]];
    }

    /**
     * A code block becomes one paragraph, every line as inline code, joined by line breaks.
     *
     * @return list<array<string, mixed>>
     */
    private function code(FencedCode|IndentedCode $node, int $line): array
    {
        $this->warn('code_block', $line);

        $inline = [];
        foreach (explode("\n", rtrim($this->restoreStars($node->getLiteral(), true), "\n")) as $text) {
            if (trim($text) === '') {
                continue;
            }
            if ($inline !== []) {
                $inline[] = ['type' => 'hardBreak'];
            }
            $inline[] = $this->text($text, ['code' => true]);
        }

        return $this->paragraph($inline);
    }

    /**
     * Tags and comments are removed, the text between stays.
     *
     * @return list<array<string, mixed>>
     */
    private function htmlBlock(HtmlBlock $node, int $line): array
    {
        $this->warn('html', $line);

        $text = preg_replace(['/<!--.*?-->/s', '/<[^>]*>/s'], '', $this->restoreStars($node->getLiteral(), true));
        $blocks = [];
        foreach (preg_split('/\n\s*\n/', html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5)) ?: [] as $part) {
            $part = trim((string) preg_replace('/\s+/', ' ', $part));
            if ($part !== '') {
                $blocks[] = ['type' => 'paragraph', 'content' => [$this->text($part, [])]];
            }
        }

        return $blocks;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function thematicBreak(int $line): array
    {
        $this->warn('thematic_break', $line);

        return [];
    }

    /**
     * Every row becomes a paragraph, its cells joined by " · ".
     *
     * @return list<array<string, mixed>>
     */
    private function table(Table $table, int $line): array
    {
        $this->warn('table', $line);

        $paragraphs = [];
        foreach ($table->children() as $section) {
            if (! $section instanceof TableSection) {
                continue;
            }
            foreach ($section->children() as $row) {
                if (! $row instanceof TableRow) {
                    continue;
                }
                $inline = [];
                foreach ($row->children() as $cell) {
                    if (! $cell instanceof TableCell) {
                        continue;
                    }
                    if ($inline !== []) {
                        $inline[] = $this->text(' · ', []);
                    }
                    array_push($inline, ...$this->inlines($cell, [], $line));
                }
                array_push($paragraphs, ...$this->paragraph($this->merge($inline)));
            }
        }

        return $paragraphs;
    }

    // ─── Inline ──────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $marks  bold, italic, code (bool), link (string)
     * @return list<array<string, mixed>>
     */
    private function inlines(Node $parent, array $marks, int $line): array
    {
        $out = [];

        foreach ($parent->children() as $node) {
            array_push($out, ...$this->inline($node, $marks, $line));
        }

        return $this->merge($out);
    }

    /**
     * @param  array<string, mixed>  $marks
     * @return list<array<string, mixed>>
     */
    private function inline(Node $node, array $marks, int $line): array
    {
        switch (true) {
            case $node instanceof Text:
                $this->checkBareStars($node, $line);

                return [$this->text($this->restoreStars($node->getLiteral()), $marks)];

            case $node instanceof Code:
                return [$this->text($this->restoreStars($node->getLiteral(), true), [...$marks, 'code' => true])];

            case $node instanceof Strong:
                return $this->inlines($node, [...$marks, 'bold' => true], $line);

            case $node instanceof Emphasis:
                return $this->inlines($node, [...$marks, 'italic' => true], $line);

            case $node instanceof Strikethrough:
                $this->warn('strikethrough', $line);

                return $this->inlines($node, $marks, $line);

            case $node instanceof Newline:
                return [$node->getType() === Newline::HARDBREAK ? ['type' => 'hardBreak'] : $this->text(' ', $marks)];

            case $node instanceof Link:
                return $this->link($node, $marks, $line);

            case $node instanceof Image:
                return $this->image($node, $marks, $line);

            case $node instanceof HtmlInline:
                $this->warn('html', $line);

                return [];

            default:
                return $this->inlines($node, $marks, $line);
        }
    }

    /**
     * @param  array<string, mixed>  $marks
     * @return list<array<string, mixed>>
     */
    private function link(Link $link, array $marks, int $line): array
    {
        $url = $this->restoreStars($link->getUrl());

        if (preg_match(self::SCHEME, $url) !== 1) {
            $this->warn('link_scheme', $line);

            return $this->inlines($link, $marks, $line);
        }

        if (($link->getTitle() ?? '') !== '') {
            $this->warn('link_title', $line);
        }

        return $this->inlines($link, [...$marks, 'link' => $url], $line);
    }

    /**
     * An image becomes its alt text, as a link to the image when that is http(s).
     *
     * @param  array<string, mixed>  $marks
     * @return list<array<string, mixed>>
     */
    private function image(Image $image, array $marks, int $line): array
    {
        $this->warn('image', $line);

        $url = $this->restoreStars($image->getUrl());
        $alt = trim($this->plainText($image));
        $isWeb = preg_match('~^https?:~i', $url) === 1;

        if ($alt === '' && $isWeb) {
            $alt = $url;
        }

        return $alt === '' ? [] : [$this->text($alt, $isWeb ? [...$marks, 'link' => $url] : $marks)];
    }

    private function plainText(Node $node): string
    {
        $text = '';

        foreach ($node->children() as $child) {
            $text .= match (true) {
                $child instanceof Text, $child instanceof Code => $this->restoreStars($child->getLiteral()),
                $child instanceof Newline => ' ',
                default => $this->plainText($child),
            };
        }

        return $text;
    }

    /**
     * An asterisk left in a text that has a neighbour other than white space was meant as
     * bold or italic and never closed.
     */
    private function checkBareStars(Text $node, int $line): void
    {
        $literal = $node->getLiteral();

        if (! str_contains($literal, '*')) {
            return;
        }

        preg_match_all('/\*+/', $literal, $runs, PREG_OFFSET_CAPTURE);

        foreach ($runs[0] as [$run, $offset]) {
            $before = $offset > 0 ? $literal[$offset - 1] : ($node->previous() === null ? ' ' : 'x');
            $end = $offset + strlen($run);
            $after = $end < strlen($literal) ? $literal[$end] : ($node->next() === null ? ' ' : 'x');

            if (ctype_space($before) && ctype_space($after)) {
                continue;
            }

            $this->warn('unclosed_emphasis', $line);

            return;
        }
    }

    /**
     * @param  array<string, mixed>  $marks
     * @return array<string, mixed>
     */
    private function text(string $text, array $marks): array
    {
        $list = [];

        foreach (['bold', 'italic', 'code'] as $mark) {
            if (($marks[$mark] ?? false) === true) {
                $list[] = ['type' => $mark];
            }
        }

        if (isset($marks['link'])) {
            $list[] = ['type' => 'link', 'attrs' => ['href' => $marks['link']]];
        }

        return $list === [] ? ['type' => 'text', 'text' => $text] : ['type' => 'text', 'text' => $text, 'marks' => $list];
    }

    /**
     * Joins neighbouring texts with the same marks, drops empty ones.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function merge(array $nodes): array
    {
        $out = [];

        foreach ($nodes as $node) {
            if (($node['type'] ?? '') === 'text' && $node['text'] === '') {
                continue;
            }

            $last = $out === [] ? null : $out[array_key_last($out)];

            if ($last !== null && $node['type'] === 'text' && $last['type'] === 'text' && ($last['marks'] ?? []) === ($node['marks'] ?? [])) {
                $out[array_key_last($out)]['text'] .= $node['text'];

                continue;
            }

            $out[] = $node;
        }

        return $out;
    }
}
