<?php

namespace App\Mcp\Tools;

/**
 * Turns the errors of a GraphQL operation into the wording the AI client
 * reads: a denied right and a missing item each get the tool's own sentence
 * (the English words for the AI client). Other messages pass unchanged.
 */
trait ExplainsGraphQLErrors
{
    /**
     * @param  string  $denied  Sentence for "unauthorized"
     * @param  string|null  $missing  Sentence for "No query results"; null keeps the message
     */
    protected function explain(string $message, string $denied, ?string $missing = null): string
    {
        if (str_contains($message, 'unauthorized')) {
            return $denied;
        }

        if ($missing !== null && str_contains($message, 'No query results')) {
            return $missing;
        }

        return $message;
    }
}
