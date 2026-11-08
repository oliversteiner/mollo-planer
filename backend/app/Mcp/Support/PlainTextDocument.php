<?php

namespace App\Mcp\Support;

/**
 * Turns plain text from an AI client into a TipTap document (the RichText
 * format). Blank lines separate paragraphs; consecutive lines starting
 * with "- " or "* " become a bullet list. No HTML or Markdown is parsed.
 */
final class PlainTextDocument
{
    /**
     * @return array<string, mixed>|null Null for empty text
     */
    public static function from(?string $text): ?array
    {
        $text = trim(str_replace("\r\n", "\n", (string) $text));

        if ($text === '') {
            return null;
        }

        $blocks = [];

        foreach (preg_split('/\n\s*\n/', $text) ?: [] as $block) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn (string $line) => $line !== ''));
            $isList = $lines !== [] && array_reduce($lines, fn (bool $carry, string $line) => $carry && preg_match('/^[-*]\s+/', $line) === 1, true);

            $blocks[] = $isList
                ? ['type' => 'bulletList', 'content' => array_map(fn (string $line) => [
                    'type' => 'listItem',
                    'content' => [self::paragraph(preg_replace('/^[-*]\s+/', '', $line) ?? $line)],
                ], $lines)]
                : self::paragraph(implode(' ', $lines));
        }

        return ['type' => 'doc', 'content' => $blocks];
    }

    /**
     * @return array<string, mixed>
     */
    private static function paragraph(string $text): array
    {
        return ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]];
    }
}
