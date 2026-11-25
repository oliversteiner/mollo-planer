# Change tasks over MCP

- Right: task.update
- Tools: list_projects, list_tasks, update_tasks, export_plan, import_plan, add_comment
- Pages of the app: –

## Purpose

An agent reads and changes tasks through the MCP server without loading the whole plan. Each change stays small, and the state in the planer matches the work.

## Good to know

- The agent acts as its own account, with exactly its rights. What it may not do in the project, the server refuses.
- The planer derives the status of todo and milestone from the tasks. An agent never sets them.
- In the workflow with Stuard, only Stuard sets «Done», after the merge. A coding agent sets «In progress» and «Review».

## For agents

- Start: `list_projects` gives the projects, your role and the revision of the plan.
- Find: `list_tasks` with filters (`assignee: "me"`, `status`, text), small with `limit`.
- Status, commit, assignee: `update_tasks` directly, without an export and without a dry run. `base_revision` only when nothing may have changed in between.
- New tasks or changed titles: `export_plan` filtered to one milestone, change it, `import_plan` with `mode: "partial"`, with `dry_run: true` first.
- Reports and questions: `add_comment` on the task.
- Not over MCP: deleting tasks for good, changing rights or roles.

## Example prompts

- With `list_tasks`, show me my tasks in the project \[project\] that are «In progress» or «Review».
- With `update_tasks`, set the task \[k-123\] to «Review» and enter the commit \[hash\]. Then write a short report on the task with `add_comment`.
- In the milestone \[milestone\] of the project \[project\], create a task «\[title\]»: export only this milestone, add the task and import with `mode: "partial"`, as a dry run first.
