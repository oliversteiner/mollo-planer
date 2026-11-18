<?php

namespace App\GraphQL\Mutations;

use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * archiveProject / unarchiveProject (right project.archive, docs/auftrag-m2.md
 * §1). An archived project is read only (Rights). Archiving again, or bringing
 * back a project that is not archived, changes nothing. The planer file does
 * not hold the archive, so the revision stays (ProjectObserver).
 */
class ArchiveProject
{
    /**
     * @param  array{id: string}  $args
     */
    public function archive(mixed $root, array $args): Project
    {
        return $this->setArchivedAt($args['id'], Carbon::now());
    }

    /**
     * @param  array{id: string}  $args
     */
    public function unarchive(mixed $root, array $args): Project
    {
        return $this->setArchivedAt($args['id'], null);
    }

    private function setArchivedAt(string $id, ?Carbon $archivedAt): Project
    {
        return DB::transaction(function () use ($id, $archivedAt): Project {
            $project = Project::query()->lockForUpdate()->findOrFail($id);

            if ($project->isArchived() !== ($archivedAt !== null)) {
                $project->archived_at = $archivedAt;
                $project->save();
            }

            return $project;
        });
    }
}
