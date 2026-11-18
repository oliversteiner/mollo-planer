<?php

namespace App\Assistant;

use GraphQL\Error\ClientAware;
use RuntimeException;

/**
 * A failure of the assistant with a message for the person: no technical details, nothing was saved.
 */
final class AssistantError extends RuntimeException implements ClientAware
{
    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return 'assistant';
    }
}
