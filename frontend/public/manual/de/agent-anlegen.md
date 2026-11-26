# Agent anlegen und Zugang einrichten

- Recht: users.manage
- Werkzeuge: –
- Seiten der App: /admin/users

Diese Aufgabe erledigen Menschen in der App. Die Schritte stehen in der Hilfe der App (Hilfe).

## Wozu

Ein Agent ist ein eigenes Konto für Claude oder eine Assistenz. Er meldet sich nicht im Browser an, sondern arbeitet über den Connector (MCP) mit einem Zugang und darf genau das, was seine Rolle im Projekt erlaubt.

## Gut zu wissen

- Der Zugang ist ein Geheimnis. Er gehört in den Passwortmanager oder in eine Umgebungsvariable, nie in eine Datei im Repository oder in einen Chat.
- Hast du den Zugang verloren, widerrufe ihn im selben Dialog und erzeuge einen neuen.
- Ohne Projekt sieht der Agent nichts. Was er im Projekt darf, bestimmt seine Rolle dort.
- Die Adresse des MCP-Servers und eine Anleitung für den Connector stehen im selben Dialog, dazu die Kennung des Agenten. Mit ihr weist du ihm Tasks zu (Planer-Datei, MCP).
- Ein Agent hat in jedem Projekt die Rolle «Agent», keine andere.

## Beispielprompts

- Verbinde dich mit dem MCP-Server des Mollo Planers über die Variable \[VARIABLE\] und zeige mir die Projekte, die du siehst.
- Prüfe mit `list_projects`, welche Rolle du im Projekt \[Projekt\] hast und was du dort ändern darfst.
