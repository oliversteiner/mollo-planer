<?php

namespace App\Planer\RichText;

/**
 * How MarkdownToTiptap treats what the app's rich text cannot hold
 * (spec/format-v2.md 6.1 and docs/planer-abgleich.md 9.6).
 */
enum Mode
{
    /** A file or an import: such a construct is an error, nothing is written. */
    case Strict;

    /** Free input (assistant, ideas, MCP, pasting): plain text instead, one warning per case. */
    case Lenient;
}
