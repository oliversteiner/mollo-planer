<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateProjectPlanTool;
use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\GetProjectTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListUsersTool;
use App\Mcp\Tools\SetProjectMemberTool;
use App\Mcp\Tools\SetTaskStatusTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * MCP server of the app (POST /mcp, routes/ai.php): lets an AI client such
 * as Claude read projects and fill them with milestones, todos and tasks,
 * acting as the owner of the personal access token with their permissions.
 * Deleting is not offered.
 */
#[Name('mollo-planer')]
#[Version('1.0.0')]
#[Instructions(<<<'TEXT'
Project management app: projects contain milestones, milestones contain todos, todos contain tasks. Tasks are the work of one person and appear on a Kanban board (status open, in progress, review, done). Statuses of todos and milestones are derived automatically; progress is done tasks out of all tasks. Mark a task done with set_task_status (or with status done in create_project_plan), never by changing a todo or milestone directly.

You act as the person who created the access token, with exactly their permissions. Roles per project: OWNER, MANAGER (both manage members and tasks), MEMBER (creates milestones and todos, moves tasks). Only admins create projects and manage accounts. The abilities in list_projects say what you may do in a project.

How to work:
1. Read first: list_projects (and get_project for an existing plan), list_users to find people.
2. People are named by e-mail. Someone without an account cannot be added; tell the user that an admin must invite them in the app (Verwaltung > Benutzer) and ask how to go on.
3. To create a new project: create_project with its members, then create_project_plan with milestones, todos and tasks. Tasks can only be assigned to project members.
4. Do not create the same items twice; check get_project before adding to an existing plan.
5. Write titles and descriptions in the language of the user; descriptions are plain text.
Nothing can be deleted through this server; the user does that in the app.
TEXT)]
class MolloPlanerServer extends Server
{
    protected array $tools = [
        ListProjectsTool::class,
        GetProjectTool::class,
        ListUsersTool::class,
        CreateProjectTool::class,
        SetProjectMemberTool::class,
        CreateProjectPlanTool::class,
        SetTaskStatusTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
