<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ReadsProjectPlanInput;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Services\ProjectPlanAssistant;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Asks Claude for a project plan from the chat. Writes nothing.
 */
class DraftProjectPlan
{
    use ReadsProjectPlanInput;
    use ResolvesViewer;

    public function __construct(private readonly ProjectPlanAssistant $assistant) {}

    /**
     * @param  array{messages: list<array{role: string, content: string}>, currentPlan?: array<string, mixed>|null}  $args
     * @return array{reply: string, plan: array<string, mixed>|null}
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        $currentPlan = isset($args['currentPlan']) ? $this->planFromInput($args['currentPlan']) : null;
        $draft = $this->assistant->draft($this->viewer($context), $args['messages'], $currentPlan, app()->getLocale());

        if ($draft['plan'] === null) {
            return $draft;
        }

        // GraphQL enums resolve from their internal values.
        $plan = $draft['plan'];
        $plan['locale'] = mb_strtolower($plan['locale']);
        $plan['members'] = array_map(fn (array $member) => [...$member, 'role' => mb_strtolower($member['role'])], $plan['members']);

        return ['reply' => $draft['reply'], 'plan' => $plan];
    }
}
