<?php

namespace App\GraphQL\Mutations;

use App\Assistant\AssistantRequest;
use App\Assistant\PlanAssistant;
use App\Assistant\PlanTargetKind;
use App\Enums\Right;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Idea;
use App\Models\Project;
use App\Models\Template;
use App\Support\Rights;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Asks Claude for the next step of a plan from the chat. Writes nothing.
 */
class DraftPlan
{
    use ResolvesViewer;

    public function __construct(private readonly PlanAssistant $assistant) {}

    /**
     * @param  array{messages: list<array{role: string, content: string}>, projectId?: int|string|null, templateId?: int|string|null, targetKind?: string|null, currentPlan?: string|null, ideaId?: int|string|null}  $args
     * @return array<string, mixed>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        $viewer = $this->viewer($context);

        if (! Rights::allows($viewer, Right::ASSISTANT_USE)) {
            throw new AuthorizationException;
        }

        $project = isset($args['projectId']) ? Project::query()->visibleTo($viewer)->find($args['projectId']) : null;

        if (isset($args['projectId']) && $project === null) {
            throw ValidationException::withMessages(['projectId' => __('assistant.no_project')]);
        }

        $template = isset($args['templateId']) ? Template::query()->find($args['templateId']) : null;

        if (isset($args['templateId']) && $template === null) {
            throw ValidationException::withMessages(['templateId' => __('planer.import.template_unknown')]);
        }

        $idea = isset($args['ideaId']) ? Idea::query()->find($args['ideaId']) : null;

        if (isset($args['ideaId']) && ($idea === null || ! Gate::forUser($viewer)->allows('update', $idea))) {
            throw ValidationException::withMessages(['ideaId' => __('assistant.idea_unknown')]);
        }

        $messages = array_map(fn (array $message): array => [
            'role' => $message['role'] === 'ASSISTANT' ? 'assistant' : 'user',
            'content' => $message['content'],
        ], $args['messages']);

        $targetKind = isset($args['targetKind']) ? PlanTargetKind::from($args['targetKind']) : null;

        return $this->assistant->draft(new AssistantRequest($viewer, $messages, $project, $targetKind, $template, $args['currentPlan'] ?? null, $idea, app()->getLocale()));
    }
}
