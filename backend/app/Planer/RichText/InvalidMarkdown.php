<?php

namespace App\Planer\RichText;

use RuntimeException;

/**
 * Strict mode met a construct that is not allowed. Nothing was converted.
 * The caller adds the path of the field (for example "project.description").
 */
final class InvalidMarkdown extends RuntimeException
{
    /**
     * @param  list<Warning>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode('; ', array_map(
            fn (Warning $error): string => "{$error->kind} (line {$error->line}): {$error->excerpt}",
            $errors,
        )));
    }
}
