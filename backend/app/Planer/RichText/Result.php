<?php

namespace App\Planer\RichText;

/**
 * A converted document. It always passes RichTextRule.
 */
final readonly class Result
{
    /**
     * @param  array<string, mixed>  $doc  TipTap document
     * @param  list<Warning>  $warnings  Empty in strict mode
     */
    public function __construct(
        public array $doc,
        public array $warnings = [],
    ) {}
}
