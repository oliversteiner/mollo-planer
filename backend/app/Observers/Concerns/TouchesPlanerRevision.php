<?php

namespace App\Observers\Concerns;

use App\Planer\PlanerRevision;

/**
 * For the observers of the items the planer file shows: a change counts
 * once for the project (App\Planer\PlanerRevision).
 */
trait TouchesPlanerRevision
{
    protected function touchRevision(int|string|null $projectId): void
    {
        app(PlanerRevision::class)->touch($projectId);
    }
}
