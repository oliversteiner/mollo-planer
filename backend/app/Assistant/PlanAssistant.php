<?php

namespace App\Assistant;

use App\Models\Project;

/**
 * draftPlan (docs/auftrag-m6.md §4): checks the request, counts it against the limits, asks Claude and lets the
 * server work over the answer. Writes nothing.
 */
class PlanAssistant
{
    public function __construct(
        private readonly AssistantPrompt $prompt,
        private readonly ClaudeClient $claude,
        private readonly DraftProcessor $processor,
        private readonly AssistantLimits $limits,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws AssistantError
     */
    public function draft(AssistantRequest $request): array
    {
        if ($request->project instanceof Project && $request->project->archived_at !== null) {
            throw new AssistantError(__('assistant.archived'));
        }

        if ($request->project instanceof Project && ! PlanChoices::mayPlanIn($request->viewer, $request->project)) {
            throw new AssistantError(__('assistant.no_project'));
        }

        $this->limits->hit($request->viewer);

        $built = $this->prompt->build($request);
        $answer = $this->claude->ask($built['system'], $built['messages'], AssistantSchema::answer());

        return $this->processor->process($answer, $request);
    }
}
