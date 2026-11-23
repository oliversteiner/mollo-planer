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
Mollo Planer manages projects with hierarchy: Project -> Milestone -> Todo -> Task (Kanban status: idea, planned, open, in_progress, review, done). Todo and milestone statuses are derived automatically from their tasks; never set them manually.

You act as the owner of the personal access token with their exact permissions; every change is checked individually. Only admins create user accounts.

Token-efficient workflow:
1. Discover: Start with list_projects (compact by default; pass include_members or include_rights only when needed). It includes the current planer_revision. Use list_users or list_templates only when required; follow next_cursor while has_more is true.
2. Read: Use export_plan(project_id) before structural edits to get revision and app IDs (m-<id>, t-<id>, k-<id>). On large plans, filter with version, milestone_ids, task_status, or include_released: false (returns excerpt: true, which must be written back with import_plan mode "partial").
3. Update tasks: To change status, commit, or assigneeEmail, prefer update_tasks (atomic, base_revision is required and comes from export_plan; run dry_run: true first). Explicit null clears commit or assigneeEmail.
4. Edit structure: Use import_plan with dry_run: true first. Keep existing IDs; name new items m-new-1, t-new-1, k-new-1 (mapped in idMap). mode "full" (default) requires matching revision and trashes omitted items; mode "partial" only adds/updates without deleting. include_document defaults to false. New projects: omit project_id with revision 0.
5. Notes, ideas & context: Use add_comment and list_comments for short task notes (never part of planer-v2). Follow list_comments/list_ideas cursors, and request truncated text by comment_id + text_offset or idea_id + description_offset. get_project_ai_context returns a bounded text chunk; continue with offset when truncated.

Rules & limits:
- Write titles and descriptions in the user's language.
- Markdown whitelist for descriptions/comments: paragraphs, H2/H3, lists, quotes, bold, italic, inline code, links (http, https, mailto). No HTML, images, tables, or code blocks.
- Assignees are identified by email and must already be project members. Assignment emails are only sent when notify_assignees is true.
- Limits: 200 milestones/project, 50 todos/milestone, 50 tasks/todo, 50 members, 30 labels, descriptions up to 5000 chars.
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
