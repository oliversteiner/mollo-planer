<?php

namespace App\Mcp\Support;

use RuntimeException;

/**
 * A GraphQL operation run for an MCP tool failed. The message lists the
 * errors, validation messages per field included. The refusal of an
 * archived project becomes one English sentence the AI client can act on.
 */
class GraphQLFailed extends RuntimeException
{
    public const ARCHIVED = 'This project is archived and read only. Ask a person with the right to archive to bring it back in the app; nothing was changed.';

    /**
     * @param  list<array{message?: string, extensions?: array{validation?: array<string, list<string>>}}>  $errors
     */
    public static function fromErrors(array $errors): self
    {
        $messages = [];

        foreach ($errors as $error) {
            $validation = $error['extensions']['validation'] ?? [];

            if ($validation === []) {
                $messages[] = self::isArchiveRefusal($error['message'] ?? null) ? self::ARCHIVED : ($error['message'] ?? 'Unknown error.');

                continue;
            }

            foreach ($validation as $field => $fieldMessages) {
                $messages[] = $field.': '.implode(' ', $fieldMessages);
            }
        }

        return new self(implode("\n", array_unique($messages)));
    }

    private static function isArchiveRefusal(?string $message): bool
    {
        return $message !== null && in_array($message, [trans('projects.archived', [], 'de'), trans('projects.archived', [], 'en')], true);
    }
}
