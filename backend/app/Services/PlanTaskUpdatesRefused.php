<?php

namespace App\Services;

use RuntimeException;

/**
 * The update of tasks was refused; the message is the text the AI client reads (a sentence, or JSON with the errors or
 * the current state). $revisionConflict: the plan changed between reading and writing, so trying again may work.
 */
class PlanTaskUpdatesRefused extends RuntimeException
{
    public function __construct(string $message, public readonly bool $revisionConflict = false)
    {
        parent::__construct($message);
    }
}
