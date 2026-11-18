<?php

namespace App\Assistant;

use App\Enums\Right;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Template;
use App\Models\Todo;
use App\Models\User;
use App\Support\Rights;

/**
 * The buttons of a question of the assistant (docs/auftrag-m6.md §4.1): what Claude proposes, checked by the
 * server. A value must be something the person sees, in a project that is not archived and where they may create
 * todos; NEW_PROJECT needs projects.create, IDEA needs ideas.create. At most five proposals, then "show all".
 */
final class PlanChoices
{
    public const KINDS = ['PROJECT', 'NEW_PROJECT', 'TEMPLATE', 'NO_TEMPLATE', 'MILESTONE', 'NEW_MILESTONE', 'CUSTOM_MILESTONE', 'APPEND_TO_TODO', 'IDEA', 'SHOW_ALL'];

    public const MAX = 5;

    /**
     * @param  list<array<string, mixed>>  $choices  As Claude gave them
     * @param  Project|null  $project  The project of the conversation, if known
     * @return list<array{kind: string, label: string, detail: ?string, value: ?string, recommended: bool}>
     */
    public static function clean(array $choices, User $viewer, ?Project $project): array
    {
        $valid = [];
        $showAll = false;

        foreach ($choices as $choice) {
            $kind = (string) ($choice['kind'] ?? '');
            $value = isset($choice['value']) && $choice['value'] !== '' ? (string) $choice['value'] : null;
            $label = trim((string) ($choice['label'] ?? ''));

            if (! in_array($kind, self::KINDS, true) || $label === '') {
                continue;
            }

            if ($kind === 'SHOW_ALL') {
                $showAll = true;

                continue;
            }

            if (! self::allowed($kind, $value, $viewer, $project)) {
                continue;
            }

            $valid[] = [
                'kind' => $kind,
                'label' => mb_substr($label, 0, 160),
                'detail' => isset($choice['detail']) && trim((string) $choice['detail']) !== '' ? mb_substr(trim((string) $choice['detail']), 0, 200) : null,
                'value' => $value,
                'recommended' => (bool) ($choice['recommended'] ?? false),
            ];
        }

        if (count($valid) > self::MAX) {
            $valid = array_slice($valid, 0, self::MAX);
            $showAll = true;
        }

        if ($showAll && $valid !== []) {
            $valid[] = ['kind' => 'SHOW_ALL', 'label' => __('assistant.show_all'), 'detail' => null, 'value' => null, 'recommended' => false];
        }

        // One recommendation at most: the first.
        $seen = false;

        foreach ($valid as $index => $choice) {
            if ($choice['recommended'] && $seen) {
                $valid[$index]['recommended'] = false;
            }
            $seen = $seen || $choice['recommended'];
        }

        return $valid;
    }

    /** May the person create todos in this project (and it is not archived)? */
    public static function mayPlanIn(User $viewer, Project $project): bool
    {
        return $project->archived_at === null
            && Project::query()->visibleTo($viewer)->whereKey($project->getKey())->exists()
            && Rights::allows($viewer, Right::TODO_CREATE, $project);
    }

    private static function allowed(string $kind, ?string $value, User $viewer, ?Project $project): bool
    {
        return match ($kind) {
            'PROJECT' => $value !== null && ($found = Project::query()->visibleTo($viewer)->find($value)) !== null && self::mayPlanIn($viewer, $found),
            'NEW_PROJECT' => Rights::allows($viewer, Right::PROJECTS_CREATE),
            'IDEA' => Rights::allows($viewer, Right::IDEAS_CREATE),
            'TEMPLATE' => $value !== null && Template::query()->whereKey($value)->exists(),
            'NO_TEMPLATE', 'NEW_MILESTONE', 'CUSTOM_MILESTONE' => true,
            'MILESTONE' => $project !== null && self::numericId($value, 'm') !== null && Milestone::query()->where('project_id', $project->getKey())->whereKey(self::numericId($value, 'm'))->exists(),
            'APPEND_TO_TODO' => $project !== null && self::numericId($value, 't') !== null && Todo::query()->where('project_id', $project->getKey())->whereKey(self::numericId($value, 't'))->exists(),
            default => false,
        };
    }

    /** "m-12" or "12" as 12. */
    private static function numericId(?string $value, string $prefix): ?int
    {
        if ($value === null || preg_match('/^(?:'.$prefix.'-)?(\d+)$/', $value, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }
}
