<?php

namespace App\Mcp;

use App\Models\User;

/**
 * The instructions the server sends with `initialize` (Auftrag 49 D). Short on purpose: the fixed cost of every session
 * (McpBudgetTest keeps a ceiling). The account line tells an agent at once whose token it holds, the fields per level
 * come from spec/planer-v2.schema.json (the one description of the form), the limits from PlanerLimits.
 */
final class ServerInstructions
{
    /** More project IDs than this are cut off: the line must not grow with the account. */
    private const MAX_PROJECT_IDS = 12;

    /** Levels of the document in order, with the list that holds their children (not a field to write). */
    private const LEVELS = ['milestone' => 'todos', 'todo' => 'tasks', 'task' => null];

    public static function for(?User $user): string
    {
        $text = self::body();

        return $user === null ? $text : self::account($user)."\n\n".$text;
    }

    /** "You are Bob (bob@example.ch), member of projects 2, 5." */
    private static function account(User $user): string
    {
        $ids = $user->projects()->orderBy('projects.id')->pluck('projects.id');
        $listed = $ids->take(self::MAX_PROJECT_IDS)->map(fn ($id): string => 'p-'.$id)->implode(', ');
        $more = $ids->count() > self::MAX_PROJECT_IDS ? ', … ('.$ids->count().' in all)' : '';

        return sprintf('You are %s (%s), member of %s.', $user->name, $user->email, $ids->isEmpty() ? 'no project' : 'projects '.str_replace('p-', '', $listed).$more);
    }

    private static function body(): string
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return str_replace(
            ['{fields}', '{frontend}'],
            [self::fields(), $frontend],
            <<<'TEXT'
Mollo Planer: Project -> Milestone -> Todo -> Task (status idea, planned, open, in_progress, review, done). Milestone and todo status are derived from their tasks; never set them. You act as the person of your token, with their exact permissions.

Work cheaply (a whole export of a big project costs 15000+ tokens):
1. list_projects: IDs and the plan revision.
2. Overview: get_project_statistics, or export_plan with view "outline".
3. Find work: list_tasks (assignee "me", status open/in_progress). One task with detail: list_tasks with ids and include.
4. Status, commit, assignee: update_tasks directly (no export, no dry_run). Give base_revision only if you need the guarantee that nothing changed.
5. Change structure: export_plan filtered to one milestone, edit it, import_plan with mode "partial" (dry_run first). The whole export only to rebuild the whole plan.
6. Notes: add_comment, list_comments. Ideas: create_idea, list_ideas. Background: get_project_ai_context.

Document fields: {fields}

Rules: write titles and descriptions in the user's language. Markdown in descriptions and comments: paragraphs, H2/H3, lists, quotes, bold, italic, inline code, links (http, https, mailto); no HTML, images, tables, code blocks. Assignees are project members, by email. An excerpt (outline, filter, no descriptions) goes back only with mode "partial"; a missing description means unchanged. IDs m-<id>, t-<id>, k-<id>; new items m-new-1, t-new-1, k-new-1. Limits: 200 milestones/project, 50 todos/milestone, 50 tasks/todo, 50 members, 30 labels, titles 2-160 characters, descriptions 5000 characters. Unknown fields are refused.
Guide: {frontend}/mcp/llms.txt, format schema: {frontend}/mcp/format-v2.schema.json
TEXT
        );
    }

    /** "milestone: id, title, …; todo: …; task: …", the fields of the schema without the list of children. */
    private static function fields(): string
    {
        $spec = json_decode((string) file_get_contents(base_path('../spec/planer-v2.schema.json')), true, 512, JSON_THROW_ON_ERROR);
        $lines = [];

        foreach (self::LEVELS as $level => $children) {
            $names = array_keys($spec['$defs'][$level]['properties']);
            $lines[] = $level.': '.implode(', ', array_values(array_filter($names, fn (string $name): bool => $name !== $children)));
        }

        return implode('; ', $lines).'.';
    }
}
