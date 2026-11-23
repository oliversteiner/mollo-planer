<?php

namespace Tests\Unit;

use App\Planer\RichText\InvalidMarkdown;
use App\Planer\RichText\MarkdownToTiptap;
use App\Planer\RichText\Mode;
use App\Planer\RichText\Profile;
use App\Planer\RichText\TiptapToMarkdown;
use App\Support\DocumentContent;
use App\Support\InvalidDocumentContent;
use App\Support\RichTextDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * The profile "Document" of the converters (docs/dokumentenverwaltung.md §5.2, spec/format-v2.md §6.2): the cases
 * in spec/fixtures/document/ in both directions, the round trip, the strict errors, and what the profile rejects.
 * The profile "Description" must not change (RichTextConverterTest, tools/planer).
 */
class DocumentProfileTest extends TestCase
{
    private static function dir(): string
    {
        // Data providers run before the app exists, so no base_path().
        return dirname(__DIR__, 3).'/spec/fixtures/document/';
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pairs(): array
    {
        $names = [];
        foreach (glob(self::dir().'*.in.md') ?: [] as $file) {
            $names[basename($file, '.in.md')] = [basename($file, '.in.md')];
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
            $names[basename($file, '.error.md')] = [basename($file, '.error.md')];
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

    /**
     * @return array<string, mixed>
     */
    private static function read_doc(string $markdown): array
    {
        return MarkdownToTiptap::convert($markdown, Mode::Strict, Profile::Document)->doc;
    }

    #[DataProvider('pairs')]
    public function test_markdown_becomes_the_expected_document_and_writes_back_canonically(string $name): void
    {
        $doc = self::read_doc(self::read("{$name}.in.md"));

        $this->assertEquals(self::fixtureJson("{$name}.tiptap.json"), $doc, 'in.md to document');
        $this->assertNull(RichTextDocument::validate($doc, Profile::Document));
        $this->assertSame(self::read("{$name}.out.md"), TiptapToMarkdown::convert($doc, Profile::Document)."\n", 'document to out.md');
    }

    #[DataProvider('pairs')]
    public function test_the_round_trip_is_stable_so_saving_makes_no_apparent_changes(string $name): void
    {
        $doc = self::fixtureJson("{$name}.tiptap.json");
        $markdown = TiptapToMarkdown::convert($doc, Profile::Document);
        $again = self::read_doc($markdown);

        $this->assertEquals($doc, $again, 'JSON, Markdown, JSON');
        $this->assertSame($markdown, TiptapToMarkdown::convert($again, Profile::Document), 'Markdown, JSON, Markdown');
    }

    #[DataProvider('errorCases')]
    public function test_strict_refuses_what_the_profile_does_not_hold(string $name): void
    {
        $expected = self::fixtureJson("{$name}.error.json")['errors'];

        try {
            MarkdownToTiptap::convert(self::read("{$name}.error.md"), Mode::Strict, Profile::Document);
            $this->fail('strict should refuse');
        } catch (InvalidMarkdown $error) {
            $this->assertSame($expected, array_map(static fn ($warning): string => $warning->kind, $error->errors));
        }

        $this->assertNull(RichTextDocument::validate(MarkdownToTiptap::convert(self::read("{$name}.error.md"), Mode::Lenient, Profile::Document)->doc, Profile::Document));
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
                str_ends_with($file, '.error.md') => [str_replace('.error.md', '.error.json', $file)],
                str_ends_with($file, '.error.json') => [str_replace('.error.json', '.error.md', $file)],
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

    public function test_the_cases_use_every_node_and_mark_of_the_profile(): void
    {
        $constants = (new ReflectionClass(RichTextDocument::class))->getConstants();
        $nodes = array_diff(array_keys($constants['DOCUMENT_CHILDREN']), ['doc']);
        $marks = $constants['DOCUMENT_MARKS'];

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

    // ─── What the profile rejects (the editor does not make it either) ──

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function rejected(): array
    {
        $p = fn (string $text): array => ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]];
        $head = ['type' => 'tableRow', 'content' => [['type' => 'tableHeader', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A']]]]]]];
        $table = fn (array $cell): array => ['type' => 'doc', 'content' => [['type' => 'table', 'content' => [$head, ['type' => 'tableRow', 'content' => [$cell]]]]]];

        return [
            'two paragraphs in a cell' => [$table(['type' => 'tableCell', 'content' => [$p('a'), $p('b')]]), 'table_cell'],
            'a line break in a cell' => [$table(['type' => 'tableCell', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'a'], ['type' => 'hardBreak']]]]]), 'table_cell'],
            'a list in a cell' => [$table(['type' => 'tableCell', 'content' => [['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [$p('x')]]]]]]), 'table_cell'],
            'merged cells' => [$table(['type' => 'tableCell', 'attrs' => ['colspan' => 2], 'content' => [$p('a')]]), 'table_cell'],
            'a rowspan' => [$table(['type' => 'tableCell', 'attrs' => ['rowspan' => 2], 'content' => [$p('a')]]), 'table_cell'],
            'alignment' => [$table(['type' => 'tableCell', 'attrs' => ['align' => 'center'], 'content' => [$p('a')]]), 'table_cell'],
            'a heading of level 5' => [['type' => 'doc', 'content' => [['type' => 'heading', 'attrs' => ['level' => 5], 'content' => [['type' => 'text', 'text' => 'x']]]]], 'heading_level_document'],
            'a heading first in a list item' => [['type' => 'doc', 'content' => [['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'x']]], $p('y')]]]]]], 'invalid_structure'],
            'a mark in a code block' => [['type' => 'doc', 'content' => [['type' => 'codeBlock', 'attrs' => ['language' => null], 'content' => [['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'bold']]]]]]], 'invalid_structure'],
            'a language with a space' => [['type' => 'doc', 'content' => [['type' => 'codeBlock', 'attrs' => ['language' => 'a b']]]], 'invalid_structure'],
            'a link with javascript' => [['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]]]]]], 'link_not_allowed'],
            'an image' => [['type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['src' => 'x.png']]]], 'node_not_allowed'],
            'a task item that is not a boolean' => [['type' => 'doc', 'content' => [['type' => 'taskList', 'content' => [['type' => 'taskItem', 'attrs' => ['checked' => 'yes'], 'content' => [$p('x')]]]]]], 'invalid_structure'],
            'a mark outside the profile' => [['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'underline']]]]]]], 'mark_not_allowed'],
        ];
    }

    /**
     * @param  array<string, mixed>  $doc
     */
    #[DataProvider('rejected')]
    public function test_the_profile_rejects_it_with_an_error(array $doc, string $key): void
    {
        $this->assertSame($key, RichTextDocument::validate($doc, Profile::Document)[0] ?? null);

        $this->expectException(InvalidDocumentContent::class);
        DocumentContent::markdownFromJson($doc);
    }

    public function test_the_defaults_of_the_editor_are_accepted_and_do_not_show_in_the_markdown(): void
    {
        $cell = fn (string $type, string $text): array => ['type' => $type, 'attrs' => ['colspan' => 1, 'rowspan' => 1, 'colwidth' => null, 'align' => null], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
        $doc = ['type' => 'doc', 'content' => [
            ['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [$cell('tableHeader', 'A')]], ['type' => 'tableRow', 'content' => [$cell('tableCell', 'x')]]]],
            ['type' => 'taskList', 'content' => [['type' => 'taskItem', 'attrs' => ['checked' => false], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'z']]]]]]],
            ['type' => 'codeBlock', 'attrs' => ['language' => null], 'content' => [['type' => 'text', 'text' => 'c']]],
        ]];

        $this->assertSame("| A |\n| --- |\n| x |\n\n- [ ] z\n\n```\nc\n```", DocumentContent::markdownFromJson($doc));
    }

    public function test_a_table_without_a_header_row_gets_one_when_written(): void
    {
        $cell = fn (string $text): array => ['type' => 'tableCell', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
        $doc = ['type' => 'doc', 'content' => [['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [$cell('a'), $cell('b')]], ['type' => 'tableRow', 'content' => [$cell('c'), $cell('d')]]]]]];

        $this->assertSame("| a | b |\n| --- | --- |\n| c | d |", DocumentContent::markdownFromJson($doc));
        $this->assertSame('tableHeader', self::read_doc("| a | b |\n| --- | --- |\n| c | d |")['content'][0]['content'][0]['content'][0]['type']);
    }

    public function test_the_limit_of_characters_holds_for_the_text_and_for_the_markdown(): void
    {
        $text = fn (int $length): array => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => str_repeat('a', $length)]]]]];

        $this->assertSame(200000, strlen(DocumentContent::markdownFromJson($text(200000))));

        try {
            DocumentContent::markdownFromJson($text(200001));
            $this->fail('too long');
        } catch (InvalidDocumentContent $error) {
            $this->assertSame('too_long', $error->key);
        }

        // Few characters of text that need many more as Markdown (every `*` gets a backslash).
        try {
            DocumentContent::markdownFromJson(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => str_repeat('*', 150000)]]]]]);
            $this->fail('too long as Markdown');
        } catch (InvalidDocumentContent $error) {
            $this->assertSame('markdown_too_long', $error->key);
            $this->assertSame(300000, $error->parameters['length']);
        }

        $this->expectException(InvalidDocumentContent::class);
        DocumentContent::jsonFromMarkdown(str_repeat('a', 200001));
    }

    // ─── The profile "Description" stays as it is ────────────────

    public function test_the_description_does_not_know_the_nodes_of_the_document(): void
    {
        foreach ([['codeBlock', ['language' => null]], ['horizontalRule', null], ['table', null], ['taskList', null]] as [$type, $attrs]) {
            $node = ['type' => $type] + ($attrs === null ? [] : ['attrs' => $attrs]);

            $this->assertSame('node_not_allowed', RichTextDocument::validate(['type' => 'doc', 'content' => [$node]])[0], $type);
        }

        $this->assertSame('heading_level', RichTextDocument::validate(['type' => 'doc', 'content' => [['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'x']]]]])[0]);
        $this->assertSame('mark_not_allowed', RichTextDocument::validate(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'strike']]]]]]])[0]);
    }

    public function test_the_description_still_turns_the_same_markdown_into_plain_text_with_warnings(): void
    {
        $markdown = "# Titel\n\n~~weg~~\n\n- [ ] offen\n\n---\n\n| a |\n|---|\n| 1 |";
        $result = MarkdownToTiptap::convert($markdown, Mode::Lenient);

        $this->assertSame(['heading_level', 'strikethrough', 'thematic_break', 'table'], array_map(static fn ($warning): string => $warning->kind, $result->warnings));
        $this->assertSame('[ ] offen', $result->doc['content'][2]['content'][0]['content'][0]['content'][0]['text']);
        $this->assertNull(RichTextDocument::validate($result->doc));
    }
}
