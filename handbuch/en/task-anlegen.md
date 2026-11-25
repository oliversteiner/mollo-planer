---
id: task-anlegen
title: Create and edit a task
area: plan
scope: app
right: task.create
pages: /projects/[id]/kanban
tools: list_tasks, update_tasks
commands: kanban-task-create
since: 1.6
---

## Purpose

A task is a single step of work in a todo. On the board you see what is open, what is running and what is done.

## How to

1. Open the project and choose the tab **{{ui:project.tabs.tasks}}**.

   {{bild:board|The board of a project with a column for each status}}

2. Choose **{{ui:task.create}}** at the top right.
3. Under **{{ui:task.todo}}**, choose the todo the task belongs to.
4. Enter a **{{ui:item.fields.title}}**.

   {{bild:formular|The form for a new task with todo and title}}

5. Fill in more fields if you need them, for example **{{ui:task.assignee}}** or **{{ui:item.fields.dueDate}}**.
6. Choose **{{ui:common.save}}**. The task is now in the column **{{ui:status.OPEN}}**.
7. To change the task, open its menu on the card and choose **{{ui:common.edit}}**.
8. To change the status, drag the card into another column.

## Good to know

- There is no task without a todo. If **{{ui:task.create}}** is missing or grey, create a todo first.
- In an archived project you cannot create tasks.
- Whether you may create or change tasks depends on your role in the project.
- In the calm view, **{{ui:task.create}}** is in the command palette.

## For agents

An agent creates new tasks with `import_plan` (an excerpt of the plan with `mode: "partial"`, `dry_run: true` first). It changes status, commit and assignee directly with `update_tasks`. With `list_tasks` it finds tasks by status, assignee or text. See the chapter «Change tasks over MCP».

## Example prompts

- In the project [project], in the todo [todo], create a task «[title]» and assign it to [person]. Check the result with a dry run first.
- Show me all my open tasks in the project [project], sorted by due date.
- Set the task [k-123] to «In progress».
