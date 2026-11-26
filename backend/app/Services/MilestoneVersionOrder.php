<?php

namespace App\Services;

use App\Enums\Right;
use App\GraphQL\Errors\OrderRefused;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use App\Support\Rights;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Puts the milestones of a project in the order of their versions (task 47). Nothing sorts by itself: a person
 * (or an agent) asks for it, and it only works when the conditions hold. All conditions are decided here, in
 * plan(); the interface and the MCP tool show what plan() says and calculate nothing themselves.
 *
 * The versions are compared by numbers, part by part (1.3 < 1.20 < 2.0 < 2.10, 1.2 < 1.2.1), never as text.
 * Milestones without a version go to the end and keep their order among themselves. Todos and tasks are not touched.
 */
class MilestoneVersionOrder
{
    /** Conditions in the order they are checked; the first one that is not met is the reason shown. */
    public const NO_RIGHT = 'no_right';

    public const ARCHIVED = 'archived';

    public const TOO_FEW_VERSIONS = 'too_few_versions';

    public const INVALID_VERSION = 'invalid_version';

    public const DUPLICATE_VERSION = 'duplicate_version';

    public const ALREADY_ORDERED = 'already_ordered';

    public function __construct(private readonly Ordering $ordering) {}

    /**
     * The project of a request, or "not allowed" for somebody who may not see it (the same answer as for a project
     * that does not exist, so nothing is given away).
     *
     * @throws AuthorizationException
     */
    public static function visibleProject(int|string $id, User $viewer): Project
    {
        $project = Project::query()->find($id);

        if ($project === null || ! Rights::canView($viewer, $project)) {
            throw new AuthorizationException;
        }

        return $project;
    }

    /**
     * Writes nothing.
     *
     * @return array{
     *     revision: int,
     *     canApply: bool,
     *     condition: string|null,
     *     conditionMilestones: list<array{id: int, title: string, version: string|null}>,
     *     order: list<array{id: int, title: string, version: string|null, from: int, to: int, moved: bool}>,
     *     moveCount: int
     * }
     */
    public function plan(Project $project, User $viewer): array
    {
        $milestones = Milestone::query()
            ->where('project_id', $project->getKey())
            ->orderBy('position')->orderBy('id')
            ->get(['id', 'title', 'release_version', 'position']);

        $withVersion = $milestones->filter(fn (Milestone $m): bool => $this->hasVersion($m));
        $invalid = $withVersion->reject(fn (Milestone $m): bool => $this->isValid((string) $m->release_version))->values();
        $duplicates = $this->duplicates($withVersion->filter(fn (Milestone $m): bool => $this->isValid((string) $m->release_version)));

        $current = $milestones->values();
        $sorted = $this->sorted($current->all());

        $order = [];
        $moves = 0;
        foreach ($sorted as $to => $milestone) {
            $from = $current->search(fn (Milestone $m): bool => $m->getKey() === $milestone->getKey());
            $moved = $from !== $to;
            $moves += $moved ? 1 : 0;
            $order[] = [
                'id' => (int) $milestone->getKey(),
                'title' => (string) $milestone->title,
                'version' => $this->hasVersion($milestone) ? (string) $milestone->release_version : null,
                'from' => (int) $from,
                'to' => $to,
                'moved' => $moved,
            ];
        }

        $condition = null;
        $listed = [];

        if ($project->isArchived()) {
            $condition = self::ARCHIVED;
        } elseif (! Rights::allows($viewer, Right::MILESTONE_UPDATE, $project)) {
            $condition = self::NO_RIGHT;
        } elseif ($withVersion->count() < 2) {
            $condition = self::TOO_FEW_VERSIONS;
        } elseif ($invalid->isNotEmpty()) {
            $condition = self::INVALID_VERSION;
            $listed = $invalid->all();
        } elseif ($duplicates !== []) {
            $condition = self::DUPLICATE_VERSION;
            $listed = $duplicates;
        } elseif ($moves === 0) {
            $condition = self::ALREADY_ORDERED;
        }

        return [
            'revision' => (int) $project->planer_revision,
            'canApply' => $condition === null,
            'condition' => $condition,
            'conditionMilestones' => array_map(fn (Milestone $m): array => [
                'id' => (int) $m->getKey(),
                'title' => (string) $m->title,
                'version' => $m->release_version,
            ], $listed),
            'order' => $order,
            'moveCount' => $moves,
        ];
    }

    /**
     * Writes the order of plan() when every condition holds and the revision is the one the caller saw. One
     * transaction; the observers count the revision and the webhook goes out as for any other change.
     *
     * @return array{revision: int, moveCount: int, order: list<int>, before: list<int>}
     *
     * @throws OrderRefused with the reason (condition, revision conflict)
     */
    public function apply(Project $project, User $actor, ?int $expectedRevision, bool $dryRun = false): array
    {
        return DB::transaction(function () use ($project, $actor, $expectedRevision, $dryRun): array {
            $this->ordering->lockProject($project->getKey());
            $fresh = Project::query()->findOrFail($project->getKey());

            if ($expectedRevision !== null && $expectedRevision !== (int) $fresh->planer_revision) {
                throw OrderRefused::revision($expectedRevision, (int) $fresh->planer_revision);
            }

            $plan = $this->plan($fresh, $actor);

            if ($plan['condition'] === self::ARCHIVED) {
                throw new AuthorizationException(__('projects.archived'));
            }

            if (! $plan['canApply']) {
                throw OrderRefused::condition($plan['condition'], $plan['conditionMilestones']);
            }

            $before = Milestone::query()->where('project_id', $fresh->getKey())->orderBy('position')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $ids = array_map(fn (array $row): int => $row['id'], $plan['order']);

            if (! $dryRun) {
                $this->ordering->arrange(Milestone::query()->where('project_id', $fresh->getKey()), $ids);
            }

            return [
                'revision' => $dryRun ? (int) $fresh->planer_revision : (int) Project::query()->whereKey($fresh->getKey())->value('planer_revision'),
                'moveCount' => $plan['moveCount'],
                'order' => $ids,
                'before' => $before,
            ];
        });
    }

    /**
     * Versions first (numerically), the others after them in their present order.
     *
     * @param  list<Milestone>  $milestones  in the present order
     * @return list<Milestone>
     */
    private function sorted(array $milestones): array
    {
        $versioned = [];
        $others = [];

        foreach ($milestones as $milestone) {
            if ($this->hasVersion($milestone) && $this->isValid((string) $milestone->release_version)) {
                $versioned[] = $milestone;
            } else {
                $others[] = $milestone;
            }
        }

        // usort is stable since PHP 8.0: equal versions (refused anyway) keep their order.
        usort($versioned, fn (Milestone $a, Milestone $b): int => self::compare((string) $a->release_version, (string) $b->release_version));

        return [...$versioned, ...$others];
    }

    /**
     * Numeric comparison, part by part; a shorter version that is the start of a longer one is smaller (1.2 < 1.2.1).
     */
    public static function compare(string $a, string $b): int
    {
        $left = array_map('intval', explode('.', $a));
        $right = array_map('intval', explode('.', $b));

        for ($i = 0, $n = max(count($left), count($right)); $i < $n; $i++) {
            if (! isset($left[$i])) {
                return -1;
            }

            if (! isset($right[$i])) {
                return 1;
            }

            if ($left[$i] !== $right[$i]) {
                return $left[$i] <=> $right[$i];
            }
        }

        return 0;
    }

    /** Only numbers with dots: 1, 1.4, 2.0.1. */
    public static function isValid(string $version): bool
    {
        return preg_match('/^\d+(\.\d+)*$/', $version) === 1;
    }

    private function hasVersion(Milestone $milestone): bool
    {
        return $milestone->release_version !== null && $milestone->release_version !== '';
    }

    /**
     * Milestones whose version equals another one (1.2 and 1.02 count as the same).
     *
     * @param  iterable<Milestone>  $milestones
     * @return list<Milestone>
     */
    private function duplicates(iterable $milestones): array
    {
        $byKey = [];

        foreach ($milestones as $milestone) {
            $key = implode('.', array_map('intval', explode('.', rtrim((string) $milestone->release_version))));
            $key = preg_replace('/(\.0)+$/', '', $key) ?? $key;
            $byKey[$key][] = $milestone;
        }

        $result = [];

        foreach ($byKey as $group) {
            if (count($group) > 1) {
                array_push($result, ...$group);
            }
        }

        return $result;
    }
}
