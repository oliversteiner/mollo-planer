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
3. Gib im Feld **{{ui:admin.users.newAgent.name}}** einen Namen ein. Der Planer macht daraus die Kennung des Agenten, zum Beispiel `claude-club-db@agent.invalid`. An einen Agenten geht nie eine Mail.
4. Wähle **{{ui:admin.users.newAgent.submit}}**. Der Dialog schliesst sich, und die Zeile des neuen Agenten ist hervorgehoben.

   {{bild:neue-zeile|Die Zeile des neuen Agenten mit der Taste «Zugang einrichten»}}

5. Wähle in seiner Zeile **{{ui:admin.users.access.state.none}}**.
6. Lass im Feld **{{ui:admin.users.agent.name}}** den vorgeschlagenen Namen stehen oder ändere ihn. Wähle **{{ui:admin.users.agent.create}}**. Fragt der Dialog nach deinem Passwort, gib es im selben Dialog ein.
7. Wähle die Taste mit dem Kopier-Symbol rechts neben dem Feld mit dem Zugang und lege den Zugang sofort in deinem Passwortmanager ab. Er wird nur einmal angezeigt. Wähle danach **{{ui:admin.users.agent.tokenStored}}**.

   {{bild:zugang|Der Dialog mit dem neuen Zugang und der Kopier-Taste}}

8. Wähle unter **{{ui:admin.users.access.projects}}** im Feld **{{ui:admin.users.access.assign}}** das Projekt aus. Der Agent ist sofort im Projekt, mit der Rolle «Agent»; das Projekt erscheint in seiner Liste.
9. Wähle unten rechts **{{ui:security.done}}**.

## Gut zu wissen

- Der Zugang ist ein Geheimnis. Er gehört in den Passwortmanager oder in eine Umgebungsvariable, nie in eine Datei im Repository oder in einen Chat.
- Hast du den Zugang verloren, widerrufe ihn im selben Dialog und erzeuge einen neuen.
- Ohne Projekt sieht der Agent nichts. Was er im Projekt darf, bestimmt seine Rolle dort.
- Die Adresse des MCP-Servers und eine Anleitung für den Connector stehen im selben Dialog, dazu die Kennung des Agenten. Mit ihr weist du ihm Tasks zu (Planer-Datei, MCP).
- Ein Agent hat in jedem Projekt die Rolle «Agent», keine andere.

## Beispielprompts

- Verbinde dich mit dem MCP-Server des Mollo Planers über die Variable [VARIABLE] und zeige mir die Projekte, die du siehst.
- Prüfe mit `list_projects`, welche Rolle du im Projekt [Projekt] hast und was du dort ändern darfst.
