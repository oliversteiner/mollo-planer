<?php

namespace App\Models\Concerns;

use App\Enums\SystemAbility;
use App\Models\User;
use App\Support\Rights;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Read filter for everything that lives in a project.
 *
 * The rule itself (who sees which project) is in App\Support\Rights.
 * This trait only follows the relations up to the project.
 */
trait VisibleToViewer
{
    /**
     * Relation path from this model to its project, for example
     * "todo.milestone.project". Null for the project itself.
     */
    abstract protected static function projectPath(): ?string;

    /**
     * Only what the logged-in user may see. Used by the GraphQL schema
     * (scopes: ["visibleToViewer"]); Lighthouse passes the field
     * arguments, which are not needed here.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleToViewer(Builder $query): void
    {
        $user = Auth::user();

        $this->scopeVisibleTo($query, $user instanceof User ? $user : null);
    }

    /**
     * Only what the given user may see.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, ?User $user): void
    {
        if (Rights::systemAllows($user, SystemAbility::PROJECTS_VIEW_ALL)) {
            return;
        }

        $path = static::projectPath();

        if ($path === null) {
            Rights::constrainToVisibleProjects($query, $user);

            return;
        }

        $query->whereHas($path, fn (Builder $projects) => Rights::constrainToVisibleProjects($projects, $user));
    }
}
