<?php

namespace App\Services;

use App\Enums\DocumentAccess;
use App\Enums\Right;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\Project;
use App\Models\User;
use App\Support\Rights;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The effective access of a person to a document (docs/dokumentenverwaltung.md §4.2), the one place that
 * computes it. The highest level wins:
 *
 * 1. document.manageAny in the project (projects.manageAny too): MANAGE.
 * 2. The owner: MANAGE.
 * 3. A share to the person: READ or EDIT.
 * 4. The level for all project members (documents.project_access): NONE, READ or EDIT.
 *
 * Steps 2 to 4 hold for members of the project only (shares and the owner are cleaned when somebody leaves).
 * Editing needs the level EDIT or higher AND the right document.update: whoever has the level without the right
 * (the system role "Lesend", a guest, an archived project) reads only. A document in the trash is visible to the
 * level MANAGE only (§4.3 rule 5).
 *
 * visibleTo() is the same as a query, so lists and single reads agree. A test compares both.
 */
final class DocumentAccessService
{
    /**
     * The shares of one person read for a list: document id => level. Without it the share of a single document
     * is read when asked.
     *
     * @var array<int, array<int, DocumentAccess|null>>
     */
    private array $shares = [];

    /**
     * Reads the shares of this person for these documents in one query, so a list needs no query per row.
     *
     * @param  iterable<Document>  $documents
     */
    public function preload(User $user, iterable $documents): void
    {
        $ids = collect($documents)->map(fn (Document $document): int => $document->getKey())->all();
        $found = DocumentShare::query()->where('user_id', $user->getKey())->whereIn('document_id', $ids)->pluck('access', 'document_id');

        foreach ($ids as $id) {
            $this->shares[$user->getKey()][$id] = $found->has($id) ? $found[$id]->level() : null;
        }
    }

    public function levelOf(?User $user, Document $document): DocumentAccess
    {
        if ($user === null) {
            return DocumentAccess::NONE;
        }

        $project = $document->project;
        $level = $this->untrashedLevel($user, $document, $project);

        // In the trash only those who manage it see it.
        return $document->trashed() && $level !== DocumentAccess::MANAGE ? DocumentAccess::NONE : $level;
    }

    /** May this person change title, kind and content? */
    public function canEdit(?User $user, Document $document): bool
    {
        return $this->levelOf($user, $document)->atLeast(DocumentAccess::EDIT);
    }

    /**
     * Only the documents the person may read (level READ or higher), without those in the trash. The query form
     * of levelOf().
     *
     * @param  Builder<Document>  $query
     */
    public function visibleTo(Builder $query, ?User $user): void
    {
        if ($user === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $member = fn (Builder $inner): Builder => $inner->whereExists(
            DB::table('project_user')->whereColumn('project_user.project_id', 'documents.project_id')->where('project_user.user_id', $user->getKey())->select(DB::raw(1)),
        );

        $query->where(function (Builder $visible) use ($user, $member): void {
            // 1. document.manageAny: everywhere (projects.manageAny) or as a member whose role has it. An archived
            // project keeps them reading (see untrashedLevel), so it is no condition here.
            if (Rights::allows($user, Right::PROJECTS_MANAGE_ANY)) {
                $visible->orWhereNotNull('documents.project_id');
            }

            if (Rights::withinCap($user, Right::DOCUMENT_MANAGE_ANY)) {
                $visible->orWhere(fn (Builder $managed) => $member($managed)
                    ->whereExists(DB::table('project_user')
                        ->whereColumn('project_user.project_id', 'documents.project_id')
                        ->where('project_user.user_id', $user->getKey())
                        ->whereIn('project_user.role_id', Rights::roleIdsWith(Right::DOCUMENT_MANAGE_ANY))
                        ->select(DB::raw(1))));
            }

            // 2. The owner.
            $visible->orWhere('documents.owner_id', $user->getKey());

            // 3. and 4. Members: the level for all members, or a share to them.
            $visible->orWhere(fn (Builder $members) => $member($members)->where(fn (Builder $any) => $any
                ->whereIn('documents.project_access', [DocumentAccess::READ->value, DocumentAccess::EDIT->value])
                ->orWhereExists(DB::table('document_shares')
                    ->whereColumn('document_shares.document_id', 'documents.id')
                    ->where('document_shares.user_id', $user->getKey())
                    ->select(DB::raw(1)))));
        });
    }

    private function untrashedLevel(User $user, Document $document, Project $project): DocumentAccess
    {
        if (Rights::allows($user, Right::DOCUMENT_MANAGE_ANY, $project)) {
            return DocumentAccess::MANAGE;
        }

        // The archive ends writing, not reading: who would manage the documents still reads them.
        if ($project->isArchived() && $this->wouldManage($user, $project)) {
            return DocumentAccess::READ;
        }

        $isMember = $project->roleIdOf($user) !== null;
        $isOwner = $document->owner_id !== null && (int) $document->owner_id === (int) $user->getKey();

        if (! $isMember && ! $isOwner) {
            return DocumentAccess::NONE;
        }

        $level = $isOwner ? DocumentAccess::MANAGE : DocumentAccess::NONE;

        if ($isMember) {
            $level = $level->max($document->project_access)->max($this->shareOf($user, $document) ?? DocumentAccess::NONE);
        }

        // Writing needs the right as well; without it the person reads at most.
        if ($level->atLeast(DocumentAccess::EDIT) && ! Rights::allows($user, Right::DOCUMENT_UPDATE, $project)) {
            return DocumentAccess::READ;
        }

        return $level;
    }

    /** document.manageAny, asked as if the project were not archived. */
    private function wouldManage(User $user, Project $project): bool
    {
        return Rights::allows($user, Right::PROJECTS_MANAGE_ANY)
            || (Rights::withinCap($user, Right::DOCUMENT_MANAGE_ANY) && Rights::roleAllows($project->roleOf($user), Right::DOCUMENT_MANAGE_ANY));
    }

    private function shareOf(User $user, Document $document): ?DocumentAccess
    {
        $known = $this->shares[$user->getKey()] ?? [];

        if (array_key_exists($document->getKey(), $known)) {
            return $known[$document->getKey()];
        }

        if ($document->relationLoaded('shares')) {
            $share = $document->shares->firstWhere('user_id', $user->getKey());

            return $share?->access->level();
        }

        return DocumentShare::query()->where(['document_id' => $document->getKey(), 'user_id' => $user->getKey()])->first()?->access->level();
    }
}
