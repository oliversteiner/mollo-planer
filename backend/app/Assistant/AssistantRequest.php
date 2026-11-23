<?php

namespace App\Assistant;

use App\Models\Idea;
use App\Models\Project;
use App\Models\Template;
use App\Models\User;

/**
 * What one call of draftPlan is about, after the server has looked at it: who asks, the chat, the project (if
 * known), the requested target kind, the template (new project), the draft in the preview and the idea.
 */
final class AssistantRequest
{
    /**
     * @param  list<array{role: string, content: string}>  $messages  Chat so far, the last one from the person
     */
    public function __construct(
        public readonly User $viewer,
        public readonly array $messages,
        public readonly ?Project $project,
        public readonly ?PlanTargetKind $targetKind,
        public readonly ?Template $template,
        public readonly ?string $currentPlan,
        public readonly ?Idea $idea,
        public readonly string $locale,
    ) {}
}
