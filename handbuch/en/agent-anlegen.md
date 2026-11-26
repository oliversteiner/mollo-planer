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
3. In the field **{{ui:admin.users.newAgent.name}}**, enter a name. Mollo Planer makes the agent's identifier from it, for example `claude-club-db@agent.invalid`. No mail is ever sent to an agent.
4. Choose **{{ui:admin.users.newAgent.submit}}**. The dialog closes, and the row of the new agent is highlighted.

   {{bild:neue-zeile|The row of the new agent with the button «Set up access»}}

5. In its row, choose **{{ui:admin.users.access.state.none}}**.
6. In the field **{{ui:admin.users.agent.name}}**, keep the suggested name or change it. Choose **{{ui:admin.users.agent.create}}**. If the dialog asks for your password, enter it in the same dialog.
7. Choose the button with the copy symbol to the right of the field with the access, and keep the access in your password manager at once. It is shown only once. Then choose **{{ui:admin.users.agent.tokenStored}}**.

   {{bild:zugang|The dialog with the new access and the button to copy it}}

8. Under **{{ui:admin.users.access.projects}}**, in the field **{{ui:admin.users.access.assign}}**, select the project. The agent is in the project at once, with the role «Agent»; the project appears in its list.
9. Choose **{{ui:security.done}}** at the bottom right.

## Good to know

- The access is a secret. It belongs in a password manager or an environment variable, never in a file under version control or in a chat.
- If you lost the access, revoke it in the same dialog and create a new one.
- Without a project the agent sees nothing. What it may do in the project depends on its role there.
- An agent has the role «Agent» in every project, no other. Its identifier, shown in the same dialog, is what you assign tasks to (planer file, MCP).
- The address of the MCP server and a guide for the connector are in the same dialog.

## Example prompts

- Connect to the MCP server of Mollo Planer with the variable [VARIABLE] and show me the projects you see.
- Check with `list_projects` which role you have in the project [project] and what you may change there.
