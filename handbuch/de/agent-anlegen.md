---
id: agent-anlegen
title: Agent anlegen und Zugang einrichten
area: admin
scope: core
right: users.manage
pages: /admin/users
tools:
commands: page-users, users-create-agent
since: 1.6
---

## Wozu

Ein Agent ist ein eigenes Konto für Claude oder eine Assistenz. Er meldet sich nicht im Browser an, sondern arbeitet über den Connector (MCP) mit einem Zugang und darf genau das, was seine Rolle im Projekt erlaubt.

## So geht's

1. Öffne **{{ui:nav.admin}} → {{ui:nav.users}}**.
2. Wähle oben rechts **{{ui:admin.users.newAgent.title}}**.
3. Gib im Feld **{{ui:admin.users.newAgent.name}}** einen Namen und im Feld **{{ui:auth.email}}** eine Adresse ein. Ein Postfach ist nicht nötig, es wird keine Mail verschickt.
4. Wähle **{{ui:admin.users.newAgent.submit}}**. Der Dialog schliesst sich, und die Zeile des neuen Agenten ist hervorgehoben.

   {{bild:neue-zeile|Die Zeile des neuen Agenten mit der Taste «Zugang einrichten»}}

5. Wähle in seiner Zeile **{{ui:admin.users.access.state.none}}**.
6. Lass im Feld **{{ui:admin.users.agent.name}}** den vorgeschlagenen Namen stehen oder ändere ihn. Wähle **{{ui:admin.users.agent.create}}**. Fragt der Dialog nach deinem Passwort, gib es im selben Dialog ein.
7. Wähle die Taste mit dem Kopier-Symbol rechts neben dem Feld mit dem Zugang und lege den Zugang sofort in deinem Passwortmanager ab. Er wird nur einmal angezeigt.

   {{bild:zugang|Der Dialog mit dem neuen Zugang und der Kopier-Taste}}

8. Wähle unter **{{ui:admin.users.access.projects}}** im Feld **{{ui:admin.users.access.assign}}** das Projekt aus. Die Rolle bleibt «Agent».
9. Wähle **{{ui:admin.users.access.assignConfirm}}**. Das Projekt erscheint in der Liste der Projekte des Agenten.
10. Wähle unten rechts **{{ui:security.done}}**.

## Gut zu wissen

- Der Zugang ist ein Geheimnis. Er gehört in den Passwortmanager oder in eine Umgebungsvariable, nie in eine Datei im Repository oder in einen Chat.
- Hast du den Zugang verloren, widerrufe ihn im selben Dialog und erzeuge einen neuen.
- Ohne Projekt sieht der Agent nichts. Was er im Projekt darf, bestimmt seine Rolle dort.
- Die Adresse des MCP-Servers und eine Anleitung für den Connector stehen im selben Dialog.

## Beispielprompts

- Verbinde dich mit dem MCP-Server des Mollo Planers über die Variable [VARIABLE] und zeige mir die Projekte, die du siehst.
- Prüfe mit `list_projects`, welche Rolle du im Projekt [Projekt] hast und was du dort ändern darfst.
