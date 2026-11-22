<?php

namespace App\Planer;

use App\Webhooks\PlanerWebhooks;
use Illuminate\Support\Facades\DB;

/**
 * The only place that counts projects.planer_revision (docs/umsetzung.md S3c,
 * docs/refactoring-m1-m3.md RF2). Every change that the planer file shows
 * touches its project. Inside a transaction the projects are remembered and
 * counted once each inside the transaction; updating the project row also
 * serializes concurrent plan writes. A rollback undoes the count. Outside a
 * transaction the count goes up at once.
 *
 * Not counted: derived statuses (written quietly), comments, archive and
 * templates. They do not change the file, or the change that caused them
 * has counted already.
 */
final class PlanerRevision
{
    /** @var array<int, true> */
    private array $pending = [];

    private bool $scheduled = false;

    public function touch(int|string|null $projectId): void
    {
        if ($projectId === null) {
            return;
        }

        $id = (int) $projectId;

        if (isset($this->pending[$id])) {
            return;
        }

        $this->pending[$id] = true;
        DB::table('projects')->where('id', $id)->increment('planer_revision');

        if ($this->scheduled) {
            return;
        }

        $this->scheduled = true;
        // Runs at once when no transaction is open.
        DB::afterRollBack(fn () => $this->forget());
        DB::afterCommit(fn () => $this->flush());
    }

    private function flush(): void
    {
        $ids = array_keys($this->pending);
        $this->forget();

        if ($ids !== []) {
            // Projects with a webhook announce the new revision (bundled, at most every five minutes).
            app(PlanerWebhooks::class)->changed($ids);
        }
    }

    private function forget(): void
    {
        $this->pending = [];
        $this->scheduled = false;
    }
}
