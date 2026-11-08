<?php

namespace App\Mcp\Support;

use RuntimeException;

/**
 * A GraphQL operation run for an MCP tool failed. The message lists the
 * errors, validation messages per field included.
 */
class GraphQLFailed extends RuntimeException
{
    /**
     * @param  list<array{message?: string, extensions?: array{validation?: array<string, list<string>>}}>  $errors
     */
    public static function fromErrors(array $errors): self
    {
        $messages = [];

        foreach ($errors as $error) {
            $validation = $error['extensions']['validation'] ?? [];

            if ($validation === []) {
                $messages[] = $error['message'] ?? 'Unknown error.';

                continue;
            }

            foreach ($validation as $field => $fieldMessages) {
                $messages[] = $field.': '.implode(' ', $fieldMessages);
            }
        }

        return new self(implode("\n", array_unique($messages)));
    }
}
