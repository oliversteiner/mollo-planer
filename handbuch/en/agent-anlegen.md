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
3. In the field **{{ui:admin.users.newAgent.name}}**, enter a name, and in the field **{{ui:auth.email}}**, an address. No mailbox is needed, no mail is sent.
4. Choose **{{ui:admin.users.newAgent.submit}}**. The dialog closes, and the row of the new agent is highlighted.

   {{bild:neue-zeile|The row of the new agent with the button «Set up access»}}

5. In its row, choose **{{ui:admin.users.access.state.none}}**.
6. In the field **{{ui:admin.users.agent.name}}**, keep the suggested name or change it. Choose **{{ui:admin.users.agent.create}}**. If the dialog asks for your password, enter it in the same dialog.
7. Choose the button with the copy symbol to the right of the field with the access, and keep the access in your password manager at once. It is shown only once.

   {{bild:zugang|The dialog with the new access and the button to copy it}}

8. Under **{{ui:admin.users.access.projects}}**, in the field **{{ui:admin.users.access.assign}}**, select the project. The role stays «Agent».
9. Choose **{{ui:admin.users.access.assignConfirm}}**. The project appears in the agent's list of projects.
10. Choose **{{ui:security.done}}** at the bottom right.

## Good to know

- The access is a secret. It belongs in a password manager or an environment variable, never in a file under version control or in a chat.
- If you lost the access, revoke it in the same dialog and create a new one.
- Without a project the agent sees nothing. What it may do in the project depends on its role there.
- The address of the MCP server and a guide for the connector are in the same dialog.

## Example prompts

- Connect to the MCP server of Mollo Planer with the variable [VARIABLE] and show me the projects you see.
- Check with `list_projects` which role you have in the project [project] and what you may change there.
