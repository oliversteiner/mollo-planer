<?php

namespace App\Planer;

/**
 * How an import treats what the project has and the file does not
 * (docs/planer-abgleich.md §2.1).
 */
enum ImportMode: string
{
    /** The file is the whole plan: what it lacks goes to the trash as one deletion, what it leaves out of a field is emptied. */
    case Full = 'full';

    /** The file is a part: it adds and changes, never deletes, and fields it leaves out stay. */
    case Partial = 'partial';
}
