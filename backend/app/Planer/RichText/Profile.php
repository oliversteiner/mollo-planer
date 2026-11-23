<?php

namespace App\Planer\RichText;

/**
 * Which rich text is meant (docs/dokumentenverwaltung.md §5.2, spec/format-v2.md §6.1 and §6.2). The converters
 * and App\Support\RichTextDocument take it as a parameter; "Description" is the default everywhere and unchanged.
 */
enum Profile
{
    /** Milestone, todo, task, comment, planer file: spec/format-v2.md §6.1. */
    case Description;

    /** Documents (documents.content): headings 1 to 4, code block, rule, strike, table, task list. §6.2. */
    case Document;
}
