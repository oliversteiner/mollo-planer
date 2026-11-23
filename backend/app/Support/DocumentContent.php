<?php

namespace App\Support;

use App\Planer\RichText\InvalidMarkdown;
use App\Planer\RichText\MarkdownToTiptap;
use App\Planer\RichText\Mode;
use App\Planer\RichText\Profile;
use App\Planer\RichText\TiptapToMarkdown;

/**
 * The content of a document in both forms (docs/dokumentenverwaltung.md §5): Markdown in the canonical form of
 * the profile "Document" is stored (documents.content), the editor works with TipTap JSON. The server converts,
 * strictly: what the profile does not allow is an error, never silently lost.
 */
final class DocumentContent
{
    /** At most this many characters of Markdown after the conversion (§5.3). */
    public const MAX_MARKDOWN_LENGTH = 200000;

    /**
     * The canonical Markdown of a TipTap document from the editor.
     *
     * @param  array<string, mixed>  $json
     *
     * @throws InvalidDocumentContent The document breaks the profile or is too long
     */
    public static function markdownFromJson(array $json): string
    {
        $error = RichTextDocument::validate($json, Profile::Document);

        if ($error !== null) {
            throw new InvalidDocumentContent($error[0], $error[1]);
        }

        $markdown = TiptapToMarkdown::convert($json, Profile::Document);
        $length = mb_strlen($markdown);

        if ($length > self::MAX_MARKDOWN_LENGTH) {
            throw new InvalidDocumentContent('markdown_too_long', ['max' => self::MAX_MARKDOWN_LENGTH, 'length' => $length]);
        }

        return $markdown;
    }

    /**
     * The TipTap document of stored Markdown (for the editor and the reading mode). Stored Markdown is canonical,
     * so this does not fail for it; Markdown from elsewhere (an import, MCP) is read strictly.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidMarkdown Something outside the profile; the errors list every place with its line
     * @throws InvalidDocumentContent Too long
     */
    public static function jsonFromMarkdown(string $markdown): array
    {
        $length = mb_strlen($markdown);

        if ($length > self::MAX_MARKDOWN_LENGTH) {
            throw new InvalidDocumentContent('markdown_too_long', ['max' => self::MAX_MARKDOWN_LENGTH, 'length' => $length]);
        }

        return MarkdownToTiptap::convert($markdown, Mode::Strict, Profile::Document)->doc;
    }
}
