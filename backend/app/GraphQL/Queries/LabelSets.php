<?php

namespace App\GraphQL\Queries;

use App\Models\LabelSet;
use Illuminate\Database\Eloquent\Collection;

/**
 * All label sets: built-in first (in their fixed order), then own sets by name.
 */
class LabelSets
{
    /**
     * @return Collection<int, LabelSet>
     */
    public function __invoke(): Collection
    {
        return LabelSet::query()
            ->with('labels')
            ->withCount('projects')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (LabelSet $set) => [$set->isBuiltin() ? 0 : 1, $set->isBuiltin() ? $set->getKey() : mb_strtolower($set->name)])
            ->values();
    }
}
