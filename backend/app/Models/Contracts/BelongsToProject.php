<?php

namespace App\Models\Contracts;

use App\Models\Project;

/**
 * A model that lives inside a project. Policies use this to find the
 * project whose permission table applies.
 */
interface BelongsToProject
{
    public function owningProject(): Project;
}
