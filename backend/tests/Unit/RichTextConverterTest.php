<?php

namespace Tests\Unit;

use App\Planer\RichText\InvalidMarkdown;
use App\Planer\RichText\MarkdownToTiptap;
use App\Planer\RichText\Mode;
use App\Planer\RichText\TiptapToMarkdown;
use App\Support\RichTextDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * Markdown and TipTap in both directions (docs/auftrag-m3.md section 2).
 *
 * The cases in spec/fixtures/richtext/ are shared with tools/planer (which
 * reads the .in.md/.out.md pairs and the .error.md files); the .tiptap.json
 * files were written from its parser, so the two implementations check each
 * other.
 */
class RichTextConverterTest extends TestCase
{
    private static function dir(): string
    {
        // Data providers run before the app exists, so no base_path().
        return dirname(__DIR__, 3).'/spec/fixtures/richtext/';
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pairs(): array
    {
        $names = [];
        foreach (glob(self::dir().'*.in.md') ?: [] as $file) {
            $name = basename($file, '.in.md');
            $names[$name] = [$name];
        }

        return $names;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function errorCases(): array
    {
        $names = [];
        foreach (glob(self::dir().'*.error.md') ?: [] as $file) {
            $name = basename($file, '.error.md');
            $names[$name] = [$name];
        }

        return $names;
    }

    private static function read(string $file): string
    {
        return (string) file_get_contents(self::dir().$file);
    }

    /**
     * @return array<string, mixed>
     */
    private static function fixtureJson(string $file): array
    {
        return json_decode(self::read($file), true, 512, JSON_THROW_ON_ERROR);
    }

    #[DataProvider('pairs')]
    public function test_markdown_becomes_the_expected_document_and_writes_back_canonically(string $name): void
    {
        $doc = MarkdownToTiptap::convert(self::read("{$name}.in.md"), Mode::Strict)->doc;

        $this->assertEquals(self::fixtureJson("{$name}.tiptap.json"), $doc, 'in.md to document');
        $this->assertNull(RichTextDocument::validate($doc));
        $this->assertSame(self::read("{$name}.out.md"), TiptapToMarkdown::convert($doc)."\n", 'document to out.md');
    }

    #[DataProvider('pairs')]
    public function test_the_document_writes_the_canonical_form_and_it_reads_back_unchanged(string $name): void
    {
        $doc = self::fixtureJson("{$name}.tiptap.json");
        $markdown = TiptapToMarkdown::convert($doc);

        $this->assertSame(self::read("{$name}.out.md"), $markdown."\n");
        $this->assertEquals($doc, MarkdownToTiptap::convert(self::read("{$name}.out.md"), Mode::Strict)->doc, 'out.md to document');
        $this->assertSame($markdown, TiptapToMarkdown::convert(MarkdownToTiptap::convert($markdown, Mode::Strict)->doc));
    }

    #[DataProvider('errorCases')]
    public function test_strict_refuses_what_lenient_turns_into_text(string $name): void
    {
        $markdown = self::read("{$name}.error.md");
        $expected = self::fixtureJson("{$name}.lenient.json");

        try {
            MarkdownToTiptap::convert($markdown, Mode::Strict);
            $this->fail('strict should refuse');
        } catch (InvalidMarkdown $error) {
            $this->assertNotEmpty($error->errors);
            $this->assertSame(array_column($expected['warnings'], 'kind'), array_map(static fn ($warning): string => $warning->kind, $error->errors));
        }

        $result = MarkdownToTiptap::convert($markdown, Mode::Lenient);

        $this->assertEquals($expected['doc'], $result->doc);
        $this->assertSame(array_column($expected['warnings'], 'kind'), array_map(static fn ($warning): string => $warning->kind, $result->warnings));
        $this->assertNull(RichTextDocument::validate($result->doc));
        $this->assertGreaterThanOrEqual(1, $result->warnings[0]->line);
        $this->assertNotSame('', $result->warnings[0]->excerpt);
    }

    public function test_every_file_has_its_counterpart(): void
    {
        $files = array_map('basename', glob(self::dir().'*') ?: []);
        $missing = [];

        foreach ($files as $file) {
            $expected = match (true) {
                str_ends_with($file, '.in.md') => [str_replace('.in.md', '.out.md', $file), str_replace('.in.md', '.tiptap.json', $file)],
                str_ends_with($file, '.out.md') => [str_replace('.out.md', '.in.md', $file)],
                str_ends_with($file, '.tiptap.json') => [str_replace('.tiptap.json', '.in.md', $file)],
                str_ends_with($file, '.error.md') => [str_replace('.error.md', '.lenient.json', $file)],
                str_ends_with($file, '.lenient.json') => [str_replace('.lenient.json', '.error.md', $file)],
                default => ["no rule for {$file}"],
            };

            foreach ($expected as $counterpart) {
                if (! in_array($counterpart, $files, true)) {
                    $missing[] = "{$file} needs {$counterpart}";
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_the_cases_use_every_node_and_mark_of_the_whitelist(): void
    {
        $constants = (new ReflectionClass(RichTextDocument::class))->getConstants();
        $nodes = array_diff(array_keys($constants['CHILDREN']), ['doc']);
        $marks = $constants['MARKS'];

        $seen = [];
        $walk = function (array $node) use (&$walk, &$seen): void {
            $seen[$node['type']] = true;
            foreach ($node['marks'] ?? [] as $mark) {
                $seen['mark:'.$mark['type']] = true;
            }
            foreach ($node['content'] ?? [] as $child) {
                $walk($child);
            }
        };

        foreach (glob(self::dir().'*.tiptap.json') ?: [] as $file) {
            $walk(json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR));
        }

        $this->assertSame([], array_values(array_filter($nodes, static fn (string $type): bool => ! isset($seen[$type]))), 'nodes without a case');
        $this->assertSame([], array_values(array_filter($marks, static fn (string $mark): bool => ! isset($seen['mark:'.$mark]))), 'marks without a case');
    }

    // ─── Details beyond the shared cases ─────────────────────────

    public function test_strict_lists_every_construct_with_its_line(): void
    {
        try {
            MarkdownToTiptap::convert("Text\n\n# Titel\n\n| a |\n|---|\n| 1 |\n\n~~weg~~", Mode::Strict);
            $this->fail('strict should refuse');
        } catch (InvalidMarkdown $error) {
            $this->assertSame(
                [['heading_level', 3], ['table', 5], ['strikethrough', 9]],
                array_map(static fn ($warning): array => [$warning->kind, $warning->line], $error->errors),
            );
            $this->assertSame('# Titel', $error->errors[0]->excerpt);
        }
    }

    public function test_the_details_a_file_may_lose_are_warnings_only_in_lenient_mode(): void
    {
        $markdown = "5. fünf\n6. sechs\n\n[x](https://a.ch \"Titel\")";

        $this->assertSame([], MarkdownToTiptap::convert($markdown, Mode::Strict)->warnings);
        $this->assertSame(
            ['ordered_start', 'link_title'],
            array_map(static fn ($warning): string => $warning->kind, MarkdownToTiptap::convert($markdown, Mode::Lenient)->warnings),
        );
    }

    public function test_things_that_only_look_like_markup_stay_text(): void
    {
        $doc = MarkdownToTiptap::convert("- [ ] offen\n- [x] erledigt\n\nFussnote[^1] und 2 * 3 = 6 und \\*\\*kein Fett\\*\\*\n\n[^1]: Text", Mode::Strict)->doc;

        $this->assertSame('bulletList', $doc['content'][0]['type']);
        $this->assertSame('[ ] offen', $doc['content'][0]['content'][0]['content'][0]['content'][0]['text']);
        $this->assertSame('Fussnote[^1] und 2 * 3 = 6 und **kein Fett**', $doc['content'][1]['content'][0]['text']);
        $this->assertSame('[^1]: Text', $doc['content'][2]['content'][0]['text']);
    }

    public function test_lenient_turns_indented_and_nested_code_into_text_too(): void
    {
        $result = MarkdownToTiptap::convert("Davor\n\n    eingerückter Code\n\n- Punkt\n\n  ```\n  a\n  b\n  ```\n", Mode::Lenient);

        $this->assertSame(['code_block', 'code_block'], array_map(static fn ($warning): string => $warning->kind, $result->warnings));
        $this->assertSame('hardBreak', $result->doc['content'][2]['content'][0]['content'][1]['content'][1]['type']);
    }

    public function test_a_document_the_app_would_refuse_is_an_error_in_both_modes(): void
    {
        $markdown = str_repeat('- ', 30).'zu tief';

        foreach ([Mode::Strict, Mode::Lenient] as $mode) {
            try {
                MarkdownToTiptap::convert($markdown, $mode);
                $this->fail('should refuse');
            } catch (InvalidMarkdown $error) {
                $this->assertSame('document_limit', $error->errors[0]->kind);
            }
        }
    }

    public function test_an_empty_text_is_an_empty_document(): void
    {
        $this->assertSame(['type' => 'doc', 'content' => []], MarkdownToTiptap::convert("  \n\n", Mode::Strict)->doc);
        $this->assertSame('', TiptapToMarkdown::convert(['type' => 'doc', 'content' => []]));
    }

    public function test_marked_text_keeps_its_spaces_outside_the_marks(): void
    {
        $doc = $this->doc([['type' => 'paragraph', 'content' => [
            $this->text('Hallo ', ['bold']),
            $this->text('Welt'),
            $this->text(' und ', ['italic']),
            $this->text('mehr', ['bold', 'italic']),
        ]]]);

        $this->assertSame('**Hallo** Welt *und* ***mehr***', TiptapToMarkdown::convert($doc));
    }

    public function test_spaces_at_the_edge_of_a_line_are_dropped(): void
    {
        $doc = $this->doc([['type' => 'paragraph', 'content' => [
            $this->text('  vorn '),
            ['type' => 'hardBreak'],
            $this->text('  nach dem Umbruch  '),
        ]]]);

        $this->assertSame("vorn\\\nnach dem Umbruch", TiptapToMarkdown::convert($doc));
    }

    public function test_text_that_would_read_as_markup_is_escaped_where_needed(): void
    {
        $paragraphs = ['# kein Titel', '> kein Zitat', '- kein Punkt', '+ auch nicht', '12. keine Liste', '---', '===', '~~~', '| a', '&amp; & &', 'a_b*c[d]e<f`g\\h'];
        $doc = $this->doc(array_map(fn (string $text): array => ['type' => 'paragraph', 'content' => [$this->text($text)]], $paragraphs));
        $markdown = TiptapToMarkdown::convert($doc);

        $this->assertSame(
            "\\# kein Titel\n\n\\> kein Zitat\n\n\\- kein Punkt\n\n\\+ auch nicht\n\n12\\. keine Liste\n\n\\---\n\n\\===\n\n\\~\\~\\~\n\n\\| a\n\n\\&amp; & &\n\na\\_b\\*c\\[d\\]e\\<f\\`g\\\\h",
            $markdown,
        );
        $this->assertEquals($doc, MarkdownToTiptap::convert($markdown, Mode::Strict)->doc);
    }

    public function test_a_heading_does_not_end_in_a_closing_sequence(): void
    {
        $doc = $this->doc([['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [$this->text('C #')]]]);

        $this->assertSame('## C \\#', TiptapToMarkdown::convert($doc));
        $this->assertEquals($doc, MarkdownToTiptap::convert('## C \\#', Mode::Strict)->doc);
    }

    public function test_items_of_a_numbered_list_are_indented_by_their_marker(): void
    {
        $item = fn (int $n): array => ['type' => 'listItem', 'content' => [
            ['type' => 'paragraph', 'content' => [$this->text("Punkt {$n}")]],
            ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [$this->text('tief')]]]]]],
        ]];
        $doc = $this->doc([['type' => 'orderedList', 'content' => array_map($item, range(1, 10))]]);
        $markdown = TiptapToMarkdown::convert($doc);

        $this->assertStringContainsString("9. Punkt 9\n   - tief\n10. Punkt 10\n    - tief", $markdown);
        $this->assertEquals($doc, MarkdownToTiptap::convert($markdown, Mode::Strict)->doc);
    }

    public function test_paragraphs_in_one_item_are_separated_by_a_blank_line(): void
    {
        $doc = $this->doc([['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [
            ['type' => 'paragraph', 'content' => [$this->text('eins')]],
            ['type' => 'paragraph', 'content' => [$this->text('zwei')]],
            ['type' => 'orderedList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [$this->text('a')]]]]]],
        ]]]]]);
        $markdown = TiptapToMarkdown::convert($doc);

        $this->assertSame("- eins\n\n  zwei\n  1. a", $markdown);
        $this->assertEquals($doc, MarkdownToTiptap::convert($markdown, Mode::Strict)->doc);
    }

    public function test_code_with_backticks_and_addresses_with_odd_characters(): void
    {
        $doc = $this->doc([['type' => 'paragraph', 'content' => [
            $this->text('`x`', ['code']),
            $this->text(' '),
            $this->text('a``b', ['code']),
            $this->text(' '),
            $this->text('Wiki', ['link' => 'https://a.ch/Foo_(Bar)']),
            $this->text(' '),
            $this->text('Offen', ['link' => 'https://a.ch/x)y']),
        ]]]);
        $markdown = TiptapToMarkdown::convert($doc);

        $this->assertSame('`` `x` `` ```a``b``` [Wiki](https://a.ch/Foo_(Bar)) [Offen](<https://a.ch/x)y>)', $markdown);
        $this->assertEquals($doc, MarkdownToTiptap::convert($markdown, Mode::Strict)->doc);
    }

    public function test_the_addresses_are_the_ones_commonmark_normalises(): void
    {
        $doc = MarkdownToTiptap::convert('[Zürich](<https://a.ch/Zürich?q=a b>)', Mode::Strict)->doc;

        $this->assertSame('https://a.ch/Z%C3%BCrich?q=a%20b', $doc['content'][0]['content'][0]['marks'][0]['attrs']['href']);
    }

    public function test_nodes_outside_the_whitelist_and_empty_blocks_are_left_out(): void
    {
        $doc = $this->doc([
            ['type' => 'paragraph'],
            ['type' => 'blockquote', 'content' => [['type' => 'paragraph']]],
            ['type' => 'codeBlock', 'content' => [$this->text('x')]],
            ['type' => 'paragraph', 'content' => [$this->text('bleibt')]],
        ]);

        $this->assertSame('bleibt', TiptapToMarkdown::convert($doc));
    }

    // ─── Property: write, read, same document ────────────────────

    public function test_random_documents_survive_writing_and_reading(): void
    {
        $generator = new RandomRichText(20261117);
        $failures = [];

        for ($i = 0; $i < 400; $i++) {
            $doc = $generator->document();
            $markdown = TiptapToMarkdown::convert($doc);

            try {
                $read = MarkdownToTiptap::convert($markdown, Mode::Strict)->doc;
            } catch (InvalidMarkdown $error) {
                $failures[] = "#{$i} not valid: {$error->getMessage()}\n".$markdown;

                continue;
            }

            $expected = RandomRichText::normalize($doc);

            if ($expected !== $read) {
                $failures[] = "#{$i} differs\n--- markdown\n{$markdown}\n--- expected\n".json_encode($expected, JSON_UNESCAPED_UNICODE)."\n--- read\n".json_encode($read, JSON_UNESCAPED_UNICODE);
            } elseif (MarkdownToTiptap::convert(TiptapToMarkdown::convert($read), Mode::Strict)->doc !== $read) {
                $failures[] = "#{$i} not stable\n".$markdown;
            }
        }

        $this->assertSame([], array_slice($failures, 0, 3), count($failures).' of 400 documents failed');
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private function doc(array $blocks): array
    {
        return ['type' => 'doc', 'content' => $blocks];
    }

    /**
     * @param  list<string|int>|array<string, string>  $marks  Names, and 'link' => address
     * @return array<string, mixed>
     */
    private function text(string $text, array $marks = []): array
    {
        $list = [];
        foreach (['bold', 'italic', 'code'] as $name) {
            if (in_array($name, $marks, true)) {
                $list[] = ['type' => $name];
            }
        }
        if (isset($marks['link'])) {
            $list[] = ['type' => 'link', 'attrs' => ['href' => $marks['link']]];
        }

        return $list === [] ? ['type' => 'text', 'text' => $text] : ['type' => 'text', 'text' => $text, 'marks' => $list];
    }
}
