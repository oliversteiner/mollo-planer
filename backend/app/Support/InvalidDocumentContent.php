<?php

namespace App\Support;

use RuntimeException;

/**
 * The key is one of lang/{de,en}/richtext.php (the text has the field name as :attribute).
 */
final class InvalidDocumentContent extends RuntimeException
{
    /**
     * @param  array<string, int|string>  $parameters
     */
    public function __construct(public readonly string $key, public readonly array $parameters = [])
    {
        parent::__construct($key);
    }
}
