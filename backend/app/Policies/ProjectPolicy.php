<?php

namespace App\Policies;

use App\Enums\Right;
use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAbilities;
use App\Support\Rights;

class ProjectPolicy
{
    use ChecksProjectAbilities;

    /**
     * Who may create projects is decided by the global role (projects.create).
     */
    public function create(User $user): bool
    {
        return Rights::allows($user, Right::PROJECTS_CREATE);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->allows($user, $project, Right::PROJECT_UPDATE);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->allows($user, $project, Right::PROJECT_DELETE);
    }

    /** Archive the project and bring it back (allowed in the archive too). */
    public function archive(User $user, Project $project): bool
    {
        return $this->allows($user, $project, Right::PROJECT_ARCHIVE);
    }

    /** The planer file of the project: reading, so it holds in the archive too. */
    public function export(User $user, Project $project): bool
    {
        return $this->allows($user, $project, Right::PLANER_EXPORT);
    }

    /** The AI context of the project: whoever has aiContext.update (the project rights; refused in the archive). */
    public function updateAiContext(User $user, Project $project): bool
    {
        return $this->allows($user, $project, Right::AI_CONTEXT_UPDATE);
    }

    /** Writes a planer file into the project. Every single change is checked again by its own policy. */
    public function import(User $user, Project $project): bool
    {
        return $this->allows($user, $project, Right::PLANER_IMPORT);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->allows($user, $project, Right::MEMBERS_MANAGE);
    }

    /** Create a document in the project. */
    public function createDocument(User $user, Project $project): bool
    {
        return $this->allows($user, $project, Right::DOCUMENT_CREATE);
    }

    public function createMilestone(User $user, Project $project): bool
    {
        return $this->allows($user, $project, Right::MILESTONE_CREATE);
    }

    /**
     * A deleted project comes back with whoever may delete it there, or with
     * projects.manageAny (Rights answers both). The rights come from the
     * membership as always; the project is read withTrashed.
     */
    public function restore(User $user, Project $project): bool
    {
        return $this->allows($user, $project, Right::PROJECT_DELETE);
    }

    /** A deleted project for good: trash.purge there, or projects.manageAny. */
    public function forceDelete(User $user, Project $project): bool
    {
        return $this->allows($user, $project, Right::TRASH_PURGE);
    }

    /** Delete everything in the project's trash for good. */
    public function emptyTrash(User $user, Project $project): bool
    {
        return $this->allows($user, $project, Right::TRASH_PURGE);
    }
}
