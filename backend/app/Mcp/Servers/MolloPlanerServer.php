<?php

namespace App\Mcp\Servers;

use App\Mcp\ToolAdapter;
use App\Tools\Tool;
use App\Tools\ToolRegistry;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Contracts\Transport;

/**
 * MCP server of the app (POST /mcp, routes/ai.php): lets AI clients read a
 * project as a planer-v2 document (export_plan) and write changes back
 * (import_plan / update_tasks), acting as the owner of the personal access
 * token with their exact permissions.
 */
#[Name('mollo-planer')]
#[Version('2.0.0')]
#[Instructions(<<<'TEXT'
Mollo Planer: Project -> Milestone -> Todo -> Task (status idea, planned, open, in_progress, review, done). Milestone and todo status are derived from their tasks; never set them. You act as the person of your token, with their exact permissions.

Work cheaply (a whole export of a big project costs 15000+ tokens):
1. list_projects: IDs and the plan revision.
2. Overview: get_project_statistics, or export_plan with view "outline".
3. Find work: list_tasks (assignee "me", status open/in_progress). One task with detail: list_tasks with ids and include.
4. Status, commit, assignee: update_tasks directly (no export, no dry_run). Give base_revision only if you need the guarantee that nothing changed.
5. Change structure: export_plan filtered to one milestone, edit it, import_plan with mode "partial" (dry_run first). The whole export only to rebuild the whole plan.
6. Notes: add_comment, list_comments. Ideas: create_idea, list_ideas. Background: get_project_ai_context.

Rules: write titles and descriptions in the user's language. Markdown in descriptions and comments: paragraphs, H2/H3, lists, quotes, bold, italic, inline code, links (http, https, mailto); no HTML, images, tables, code blocks. Assignees are project members, by email. An excerpt (outline, filter, no descriptions) goes back only with mode "partial"; a missing description means unchanged. IDs m-<id>, t-<id>, k-<id>; new items m-new-1, t-new-1, k-new-1. Limits: 200 milestones/project, 50 todos/milestone, 50 tasks/todo, 50 members, 30 labels, descriptions 5000 characters.
TEXT)]
class MolloPlanerServer extends Server
{
    /**
     * Filled from the registry: the tools are described once (App\Tools), this server only offers them.
     *
     * @var array<int, Server\Tool>
     */
    protected array $tools = [];

    public function __construct(Transport $transport)
    {
        parent::__construct($transport);

        $this->tools = array_map(fn (Tool $tool): ToolAdapter => new ToolAdapter($tool), ToolRegistry::all());
    }

    protected array $resources = [];

    protected array $prompts = [];
}
