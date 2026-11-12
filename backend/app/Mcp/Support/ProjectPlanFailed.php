<?php

namespace App\Mcp\Support;

/**
 * One item of a project plan could not be created. The message (English,
 * for AI clients) names the item; localized() is the text for the app.
 */
class ProjectPlanFailed extends GraphQLFailed
{
    public const MILESTONE = 'milestone';

    public const TODO = 'todo';

    public const TASK = 'task';

    public const NOT_A_MEMBER = 'not_a_member';

    public const UNKNOWN_PERSON = 'unknown_person';

    public const DUPLICATE_TASK = 'duplicate_task';

    public const DUPLICATE_TODO = 'duplicate_todo';

    /**
     * @param  string  $kind  One of the constants
     * @param  string  $subject  Title of the item, or the e-mail for UNKNOWN_PERSON
     * @param  string  $detail  Reason (MILESTONE, TODO, TASK), the e-mail (NOT_A_MEMBER) or the parent item (DUPLICATE_*)
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $subject,
        public readonly string $detail = '',
    ) {
        parent::__construct(match ($kind) {
            self::MILESTONE => "Milestone \"{$subject}\": {$detail}",
            self::TODO => "Todo \"{$subject}\": {$detail}",
            self::TASK => "Task \"{$subject}\": {$detail}",
            self::NOT_A_MEMBER => "Task \"{$subject}\": {$detail} is not a member of this project. Add them with set_project_member first, or leave the task unassigned.",
            self::UNKNOWN_PERSON => "No person with the e-mail {$subject} has an account. An admin must invite them in the app first.",
            self::DUPLICATE_TASK => "Task \"{$subject}\" already exists in todo \"{$detail}\". Nothing was created.",
            self::DUPLICATE_TODO => "Todo \"{$subject}\" already exists in milestone \"{$detail}\". Nothing was created.",
        });
    }

    public function localized(): string
    {
        return __("projects.plan.{$this->kind}", ['subject' => $this->subject, 'detail' => $this->detail]);
    }
}
