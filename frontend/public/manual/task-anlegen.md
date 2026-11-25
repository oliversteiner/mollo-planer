# Create and edit a task

- Right: task.create
- Tools: list_tasks, update_tasks
- Pages of the app: /projects/[id]/kanban

## Purpose

A task is a single step of work in a todo. On the board you see what is open, what is running and what is done.

## Good to know

- There is no task without a todo. If **New task** is missing or grey, create a todo first.
- In an archived project you cannot create tasks.
- Whether you may create or change tasks depends on your role in the project.
- In the calm view, **New task** is in the command palette.

## For agents

An agent creates new tasks with `import_plan` (an excerpt of the plan with `mode: "partial"`, `dry_run: true` first). It changes status, commit and assignee directly with `update_tasks`. With `list_tasks` it finds tasks by status, assignee or text. See the chapter «Change tasks over MCP».

## Example prompts

- In the project \[project\], in the todo \[todo\], create a task «\[title\]» and assign it to \[person\]. Check the result with a dry run first.
- Show me all my open tasks in the project \[project\], sorted by due date.
- Set the task \[k-123\] to «In progress».
