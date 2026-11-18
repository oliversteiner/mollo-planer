<?php

namespace App\GraphQL\Queries;

use App\Enums\Right;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Project;
use App\Models\User;
use App\Support\Rights;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * People who can be taken into a project (docs/fix-m1-mitglieder.md §1.1):
 * all approved people, by name, with whether they are a member already. Names
 * and avatars only, no e-mail address and no security status.
 *
 * With a project: for people who may manage its members. Without (the dialog
 * "new project"): for people who may create projects. Others get nothing.
 * All people at once is fine for the expected size; beyond about 500 people
 * this needs a search on the server.
 */
class MemberCandidates
{
    use ResolvesViewer;

    /**
     * @param  array{projectId?: int|string|null}  $args
     * @return list<array{id: int, name: string, avatarUrl: string|null, isMember: bool}>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        $viewer = $this->viewer($context);
        $projectId = $args['projectId'] ?? null;
        $memberIds = [];

        if ($projectId !== null) {
            $project = Project::query()->find($projectId);

            if ($project === null || ! Rights::allows($viewer, Right::MEMBERS_MANAGE, $project)) {
                return [];
            }

            $memberIds = $project->members()->pluck('users.id')->map(fn ($id): int => (int) $id)->all();
        } elseif (! Rights::allows($viewer, Right::PROJECTS_CREATE)) {
            return [];
        }

        return User::query()
            ->whereNotNull('approved_at')
            ->orderBy('name')
            ->get(['id', 'name', 'avatar_path'])
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'avatarUrl' => $user->avatar_url,
                'isMember' => in_array((int) $user->id, $memberIds, true),
            ])
            ->all();
    }
}
