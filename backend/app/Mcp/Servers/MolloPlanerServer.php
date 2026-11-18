<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateIdeaTool;
use App\Mcp\Tools\ExportPlanTool;
use App\Mcp\Tools\ImportPlanTool;
use App\Mcp\Tools\ListIdeasTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListTemplatesTool;
use App\Mcp\Tools\ListUsersTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * MCP server of the app (POST /mcp, routes/ai.php): lets an AI client such
 * as Claude read a project as a planer-v2 file (export_plan) and write one
 * back (import_plan), acting as the owner of the personal access token with
 * their permissions. One way to read and one to write: the file format.
 */
#[Name('mollo-planer')]
#[Version('2.0.0')]
#[Instructions(<<<'TEXT'
Project management app: projects contain milestones, milestones contain todos, todos contain tasks. Tasks are the work of one person and appear on a Kanban board (status idea, planned, open, in_progress, review, done). Statuses of todos and milestones are derived automatically from their tasks; to finish work, set the status of its tasks.

You act as the person who created the access token, with exactly their permissions; every change you make is checked on its own. The abilities in list_projects say what you may do in a project. Only admins create accounts.

The whole plan of a project is one planer-v2 document (format, revision, project with members and labels, milestones with todos with tasks). You read it and you write it back; there is no other way to change a plan.

How to work:
1. Find the project: list_projects (id, your role, abilities). list_users names the people (by e-mail); list_templates shows templates for new projects.
2. Read: export_plan(project_id) gives the document. It has the revision and the ids (m-<id>, t-<id>, k-<id>) of the app.
3. Change the document: edit what you need, keep the ids of what exists, give new items ids like m-new-1, t-new-1, k-new-1 (the result maps them to real ids). Order in a list is the order in the app. People are named by e-mail and must be members (project.members) and have an account; someone without an account cannot be added, tell the user an admin must invite them in the app first.
4. Always try import_plan with dry_run: true first. Nothing is written; the result lists every change and every error with its path in the document. Fix the errors, then import for real.
5. mode "full" (default): the document is the whole plan. What it lacks goes to the trash as one deletion the user can undo in the app, and its revision must be the one of the project (export again if somebody changed it). mode "partial": use it to add to a plan or change a part: it only adds and changes, fields you leave out stay, nothing is deleted, the revision is not checked.
6. To create a new project, call import_plan without project_id and with a complete document (revision 0, ids like m-new-1).
7. Write titles and descriptions in the language of the user. Descriptions are Markdown with a small whitelist: paragraphs, headings level 2 and 3, lists, quotes, bold, italic, inline code, links (http, https, mailto). No HTML, images, tables or code blocks.
8. Limits: 30 milestones per project, 50 todos per milestone, 50 tasks per todo, 50 members, 30 labels, descriptions up to 5000 characters.
Assignment mails are not sent unless you ask with notify_assignees.
TEXT)]
class MolloPlanerServer extends Server
{
    protected array $tools = [
        ListProjectsTool::class,
        ListTemplatesTool::class,
        ListUsersTool::class,
        ExportPlanTool::class,
        ImportPlanTool::class,
        CreateIdeaTool::class,
        ListIdeasTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
