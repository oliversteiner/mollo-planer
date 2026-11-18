<?php

namespace App\GraphQL\Mutations;

use App\Enums\IdeaState;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Idea;
use App\Models\Project;
use App\Planer\IdeaDraft;
use App\Services\Deletion;
use Illuminate\Validation\ValidationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Ideas (docs/auftrag-m6.md §2.3). The rights are checked by @can (create: ideas.create, the rest: the author);
 * the author comes from the login. A project can only be one the person sees. The draft is checked against the
 * schema and stored canonical. Ideas are no change of a plan.
 */
class IdeaMutations
{
    use ResolvesViewer;

    public function __construct(private readonly Deletion $deletion) {}

    /**
     * @param  array{input: array{title: string, description?: array<string, mixed>|null, projectId?: int|string|null, draft?: string|null}}  $args
     *
     * @throws ValidationException
     */
    public function create(mixed $root, array $args, GraphQLContext $context): Idea
    {
        $viewer = $this->viewer($context);
        $input = $args['input'];

        $idea = new Idea(['title' => trim($input['title']), 'description' => $input['description'] ?? null, 'draft' => IdeaDraft::normalize($input['draft'] ?? null)]);
        $idea->user_id = $viewer->getKey();
        $idea->project_id = $this->projectId($viewer, $input['projectId'] ?? null);
        $idea->save();

        return $idea;
    }

    /**
     * @param  array{id: int|string, input: array<string, mixed>}  $args
     *
     * @throws ValidationException
     */
    public function update(mixed $root, array $args, GraphQLContext $context): Idea
    {
        $idea = Idea::query()->findOrFail($args['id']);
        $input = $args['input'];

        if (array_key_exists('title', $input)) {
            $idea->title = trim((string) $input['title']);
        }

        if (array_key_exists('description', $input)) {
            $idea->description = $input['description'];
        }

        if (array_key_exists('draft', $input)) {
            $idea->draft = IdeaDraft::normalize($input['draft']);
        }

        if (array_key_exists('projectId', $input)) {
            $idea->project_id = $this->projectId($this->viewer($context), $input['projectId']);
        }

        $idea->save();

        return $idea;
    }

    /**
     * @param  array{id: int|string, status: string}  $args
     */
    public function setStatus(mixed $root, array $args): Idea
    {
        $idea = Idea::query()->findOrFail($args['id']);
        $state = IdeaState::from($args['status']);

        $idea->status = $state;
        $idea->adopted_at = $state === IdeaState::ADOPTED ? ($idea->adopted_at ?? now()) : null;
        $idea->save();

        return $idea;
    }

    /**
     * @param  array{id: int|string}  $args
     */
    public function delete(mixed $root, array $args): Idea
    {
        /** @var Idea $idea */
        $idea = $this->deletion->delete(Idea::query()->findOrFail($args['id']));

        return $idea;
    }

    /**
     * @throws ValidationException
     */
    private function projectId(mixed $viewer, int|string|null $projectId): int|string|null
    {
        if ($projectId === null) {
            return null;
        }

        if (! Project::query()->visibleTo($viewer)->whereKey($projectId)->exists()) {
            throw ValidationException::withMessages(['input.projectId' => __('ideas.project_unknown')]);
        }

        return $projectId;
    }
}
