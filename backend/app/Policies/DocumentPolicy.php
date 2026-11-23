<?php

namespace App\Policies;

use App\Enums\DocumentAccess;
use App\Enums\Right;
use App\Models\Document;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAbilities;
use App\Services\DocumentAccessService;
use App\Support\Rights;

/**
 * Who may do what with a document (docs/dokumentenverwaltung.md §4.2). The level comes from
 * DocumentAccessService; this class only turns it into abilities and adds the ground rules:
 * in an archived project documents are read only (reading, history and export stay), and a document in the
 * trash is visible to the level MANAGE only.
 *
 * Creating is asked of the project: ProjectPolicy::createDocument.
 */
class DocumentPolicy
{
    use ChecksProjectAbilities;

    public function __construct(private readonly DocumentAccessService $access) {}

    /** Read, see the history, export. */
    public function view(User $user, Document $document): bool
    {
        return $this->access->levelOf($user, $document)->atLeast(DocumentAccess::READ);
    }

    /** Title, kind, content, restore a version. */
    public function update(User $user, Document $document): bool
    {
        return $this->write($user, $document, DocumentAccess::EDIT);
    }

    /** Shares, owner, delete. */
    public function manage(User $user, Document $document): bool
    {
        return $this->write($user, $document, DocumentAccess::MANAGE);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->manage($user, $document);
    }

    /**
     * Back from the trash: the owner, or who has trash.restore in the project (document.manageAny does not need
     * to be asked: it comes with the project roles that restore). An archived project allows it (trash.restore
     * holds then).
     */
    public function restore(User $user, Document $document): bool
    {
        $isOwner = $document->owner_id !== null && (int) $document->owner_id === (int) $user->getKey();

        return $isOwner || Rights::allows($user, Right::DOCUMENT_MANAGE_ANY, $document->project) || $this->allows($user, $document->project, Right::TRASH_RESTORE);
    }

    /** Out of the trash for good. */
    public function forceDelete(User $user, Document $document): bool
    {
        return $this->allows($user, $document->project, Right::TRASH_PURGE);
    }

    private function write(User $user, Document $document, DocumentAccess $needed): bool
    {
        $level = $this->access->levelOf($user, $document);

        // Seen, but the project is archived: say so instead of a plain refusal (strangers learn nothing).
        if ($level->atLeast(DocumentAccess::READ) && $document->project->isArchived()) {
            $this->refuseAsArchived();
        }

        return $level->atLeast($needed);
    }
}
