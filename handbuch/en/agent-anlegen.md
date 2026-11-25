---
id: agent-anlegen
title: Create an agent and set up its access
area: admin
scope: core
right: users.manage
pages: /admin/users
tools:
commands: page-users, users-create-agent
since: 1.6
---

## Purpose

An agent is an account of its own for Claude or an assistant. It does not sign in in the browser but works through the connector (MCP) with an access, and it may do exactly what its role in the project allows.

## How to

1. Open **{{ui:nav.admin}} → {{ui:nav.users}}**.
2. Choose **{{ui:admin.users.newAgent.title}}** at the top right.
3. Enter a **{{ui:projects.fields.name}}** and an **{{ui:auth.email}}**. No mailbox is needed, no mail is sent.
4. Choose **{{ui:admin.users.newAgent.submit}}**. The dialog closes, and the row of the new agent is highlighted.

   {{bild:neue-zeile|The list of agents with the new agent and the button «Set up access»}}

5. In its row, choose **{{ui:admin.users.access.state.none}}**.
6. Keep the suggested **{{ui:admin.users.agent.name}}** or change it. Choose **{{ui:admin.users.agent.create}}**. If the dialog asks for your password, enter it in the same dialog.
7. Choose **{{ui:admin.users.agent.copyToken}}** and keep the access in your password manager at once. It is shown only once.

   {{bild:zugang|The dialog with the new access and the button to copy it}}

8. Under **{{ui:admin.users.access.projects}}**, assign a project to the agent.
9. Choose **{{ui:security.done}}**.

## Good to know

- The access is a secret. It belongs in a password manager or an environment variable, never in a file under version control or in a chat.
- If you lost the access, revoke it in the same dialog and create a new one.
- Without a project the agent sees nothing. What it may do in the project depends on its role there.
- The address of the MCP server and a guide for the connector are in the same dialog.

## Example prompts

- Connect to the MCP server of Mollo Planer with the variable [VARIABLE] and show me the projects you see.
- Check with `list_projects` which role you have in the project [project] and what you may change there.
