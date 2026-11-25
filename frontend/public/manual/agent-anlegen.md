# Create an agent and set up its access

- Right: users.manage
- Tools: –
- Pages of the app: /admin/users

This task is done by people in the app. The steps are in the help of the app (Help).

## Purpose

An agent is an account of its own for Claude or an assistant. It does not sign in in the browser but works through the connector (MCP) with an access, and it may do exactly what its role in the project allows.

## Good to know

- The access is a secret. It belongs in a password manager or an environment variable, never in a file under version control or in a chat.
- If you lost the access, revoke it in the same dialog and create a new one.
- Without a project the agent sees nothing. What it may do in the project depends on its role there.
- An agent has the role «Agent» in every project, no other. Its identifier, shown in the same dialog, is what you assign tasks to (planer file, MCP).
- The address of the MCP server and a guide for the connector are in the same dialog.

## Example prompts

- Connect to the MCP server of Mollo Planer with the variable \[VARIABLE\] and show me the projects you see.
- Check with `list_projects` which role you have in the project \[project\] and what you may change there.
