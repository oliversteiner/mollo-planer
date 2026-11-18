<?php

namespace App\Enums;

/**
 * Where an idea stands (docs/auftrag-m6.md §2.1). Not the status IDEA of a task: that one is part of a project.
 */
enum IdeaState: string
{
    case OPEN = 'open';
    case ADOPTED = 'adopted';
    case DISCARDED = 'discarded';
}
