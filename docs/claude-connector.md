# Claude anbinden: der Connector für Mollo Planer

> **Stand (2026-11-17, M4 I3):** Der Connector hat fünf Werkzeuge. Einen Plan liest `export_plan`, und schreibt `import_plan`. Die früheren Werkzeuge (`get_project`, `create_project`, `set_project_member`, `create_project_plan`, `set_task_status`, `add_to_plan`, `update_plan_item`, `move_milestone`) gibt es nicht mehr. Siehe „Breaking Change“ unten.

Dieser Text erklärt, wie Claude auf Mollo Planer zugreift, wie du den Zugang einrichtest und was dabei passiert. Die Einrichtung in einem Satz: Im Profil einen Zugang erzeugen und die Konfiguration in Claude Desktop oder Claude Code eintragen.

---

## Überblick

Claude spricht mit dem Backend über das **Model Context Protocol (MCP)**. Der Server heißt „Mollo Planer“ und läuft unter `https://api.planer.mollo.dev/mcp` (lokal `http://localhost:8000/mcp`).

Der Zugang funktioniert so:

1. Du erzeugst im Profil unter **Zugang für Claude** einen persönlichen Zugang (Token).
2. Du trägst den Zugang in die Konfiguration von Claude Desktop oder den Befehl für Claude Code ein.
3. Claude schickt bei jeder Anfrage den Zugang mit. Das Backend prüft ihn und handelt **mit deinen Rechten**.

Claude sieht also nur, was du in der App auch sehen würdest, und kann nur ändern, was du ändern dürftest.

---

## Bausteine im Repo

| Teil | Datei | Aufgabe |
|---|---|---|
| Route | `backend/routes/ai.php` | Registriert den MCP-Server unter `/mcp`, mit Anmeldung, Token-Prüfung und Drosselung |
| Server | `backend/app/Mcp/Servers/MolloPlanerServer.php` | Enthält die Werkzeuge und die Regeln für Claude |
| Werkzeuge | `backend/app/Mcp/Tools/*.php` | Die fünf Werkzeuge (Projekte, Vorlagen und Personen lesen, einen Plan exportieren und importieren) |
| Token-Prüfung | `backend/app/Http/Middleware/EnsureMcpToken.php` | Lässt nur Zugänge mit der Fähigkeit `mcp` zu |
| Token-Regeln | `backend/app/Support/McpTokens.php` | Name der Fähigkeit und Gültigkeit der Zugänge |
| Oberfläche | `frontend/app/components/profile/ClaudeAccessCard.vue` | Zugang erzeugen, anzeigen, widerrufen, Konfiguration kopieren |
| Konfiguration | `frontend/app/utils/claude.ts` | Baut den Eintrag für `claude_desktop_config.json` |

Die Werkzeuge laufen im Namen der Person durch dasselbe GraphQL-Schema wie die Oberfläche. Dadurch gelten dieselben Rechte und Regeln. Ein Import prüft darüber hinaus jede einzelne Änderung mit dem Recht, das die Oberfläche dafür verlangt.

---

## Einrichtung

### Schritt 1: Zugang erzeugen

1. In der App anmelden und **Profil** öffnen.
2. Im Abschnitt **Zugang für Claude** einen Namen eingeben, zum Beispiel „Claude Laptop“, und **Zugang erzeugen** wählen.
3. Der Zugang wird **nur einmal** angezeigt. Vorher nichts schließen, was ihn verdecken könnte.

Ein Zugang gilt **90 Tage**. Danach musst du einen neuen erzeugen.

### Schritt 2a: Claude Desktop

Voraussetzung: **Node.js** auf dem Rechner (für `npx`).

1. Im Profil die Konfiguration **Claude Desktop** kopieren. Sie sieht so aus:

   ```json
   {
     "mcpServers": {
       "mollo-planer": {
         "command": "npx",
         "args": ["-y", "mcp-remote", "https://api.planer.mollo.dev/mcp", "--header", "Authorization:${AUTH_TOKEN}"],
         "env": { "AUTH_TOKEN": "Bearer <dein Zugang>" }
       }
     }
   }
   ```

2. In Claude Desktop **Einstellungen → Entwickler → Konfiguration bearbeiten** öffnen. Das ist die Datei `claude_desktop_config.json`.
3. Den Eintrag `mcpServers` aus der Zwischenablage einfügen. Gibt es schon einen Block `mcpServers`, nur den Eintrag `"mollo-planer"` ergänzen, damit andere Server erhalten bleiben. Die Datei muss gültiges JSON bleiben.
4. Claude Desktop **vollständig beenden und neu starten**. Die Konfiguration wird nur beim Start gelesen.

Danach erscheint „mollo-planer“ unter den verfügbaren Werkzeugen.

### Schritt 2b: Claude Code

Im Terminal den Befehl aus dem Profil ausführen. Er sieht so aus:

```bash
claude mcp add --transport http mollo-planer https://api.planer.mollo.dev/mcp --header "Authorization: Bearer <dein Zugang>"
```

Danach kennt Claude Code den Server „mollo-planer“.

Für dieses Repository liegt außerdem eine `.mcp.json` bei. Sie nutzt die Umgebungsvariable `MOLLOPLANER_TOKEN`:

```bash
export MOLLOPLANER_TOKEN="<dein Zugang>"
```

Claude Code danach neu starten.

---

## Was Claude tun kann

Ein Projekt ist für Claude **eine Datei im Format planer-v2** (`spec/format-v2.md`): Projekt mit Mitgliedern und Labels, Milestones, ToDos und Tasks, mit der Revision und den IDs der App. Claude liest sie, ändert sie und schreibt sie zurück. Einen anderen Weg, einen Plan zu ändern, gibt es nicht.

| Werkzeug | Was es macht | Wer darf es |
|---|---|---|
| `list_projects` | Projekte auflisten, die du siehst, mit Rolle, Rechten, Fortschritt und Mitgliedern. Archivierte nur mit `archived: true` | alle |
| `list_users` | Personen mit E-Mail-Adresse auflisten, zum Zuweisen | Admins |
| `list_templates` | Vorlagen mit Labels und Gerüst zeigen | alle |
| `export_plan` | Das ganze Projekt als planer-v2-Dokument (`{ "document": … }`) mit Revision und IDs (`m-<id>`, `t-<id>`, `k-<id>`). Der Papierkorb fehlt. Ändert nichts | Recht „Planer-Datei exportieren“, auch im Archiv |
| `import_plan` | Schreibt ein planer-v2-Dokument in ein Projekt (`project_id`) oder ohne `project_id` in ein neues. Siehe unten | Recht „Planer-Datei importieren“, dazu für jede einzelne Änderung das Recht der Oberfläche. Ein neues Projekt: Recht, Projekte anzulegen |

### `import_plan`

| Argument | Bedeutung |
|---|---|
| `project_id` | Das Projekt. Ohne: ein neues Projekt aus dem Dokument (Revision 0, IDs wie `m-new-1`) |
| `plan` | Das Dokument, zum Beispiel das aus `export_plan` mit Änderungen |
| `mode` | `full` (Standard): das Dokument ist der ganze Plan. Was darin fehlt, kommt als **ein** Vorgang in den Papierkorb und lässt sich in der App zurückholen. Felder, die das Dokument nicht nennt, werden geleert. Die `revision` muss die des Projekts sein (sonst Abbruch mit beiden Zahlen, dann neu exportieren). `partial`: ergänzt und ändert, löscht nie, Felder, die fehlen, bleiben, die Revision wird nicht geprüft |
| `dry_run` | `true`: nichts wird geschrieben. Das Ergebnis zeigt, was geschähe (alle Änderungen) und was scheitern würde (alle Fehler mit Pfad). **Immer zuerst so versuchen.** |
| `notify_assignees` | `true`: Mails für Zuweisungen. Standard `false` |

Das Ergebnis enthält `changes` (je Eintrag `kind` `CREATE`/`UPDATE`/`MOVE`/`DELETE`, `path` im Dokument, `id`, betroffene `fields`), `errors` (je Eintrag `path`, `kind`, `message`), `idMap` (die IDs `…-new-…` des Dokuments und die echten IDs, die die App vergeben hat) und nach einem echten Import das neue `document`. Bei Fehlern wird **nichts** geschrieben (alles oder nichts), und das Ergebnis kommt als MCP-Fehler.

Was ein Import beachtet:

- **Reihenfolge:** Die Reihenfolge in den Listen ist die Reihenfolge in der App, auch über Elternteile hinweg (ein Task in einem anderen ToDo, ein ToDo in einem anderen Milestone).
- **Nicht im Format, bleibt unverändert:** Icons, Kommentare, wer angelegt hat, Zeitstempel.
- **Personen** werden über die E-Mail-Adresse erkannt. Wer kein freigegebenes Konto hat, lässt sich nicht aufnehmen, ein Import legt keine Konten an. Die Rolle eines Mitglieds ist der Schlüssel einer Projektrolle der App (`owner`, `manager`, `member`, `guest` oder eine eigene). Im Modus `full` werden Mitglieder, die im Dokument fehlen, entfernt. Wer importiert, entfernt sich damit nicht selbst, und es bleibt immer jemand, die oder der das Projekt löschen darf.
- **Grenzen** der Datei gelten für das Ergebnis: 30 Milestones, 50 ToDos je Milestone, 50 Tasks je ToDo, 50 Mitglieder, 30 Labels, Beschreibungen bis 5000 Zeichen.
- **Freigabe-Regeln:** Eine veröffentlichte Version braucht erledigte Tasks mit Commit. Sie werden für das Ergebnis geprüft, nicht Schritt für Schritt.
- **Beschreibungen** sind Markdown mit einer kleinen Auswahl (Absätze, Überschriften 2 und 3, Listen, Zitate, fett, kursiv, Code, Links). Alles andere ist ein Fehler.
- **Archivierte Projekte** lassen sich lesen (`export_plan`), nicht schreiben.

**Löschen** geht nur über `import_plan` im Modus `full`, immer in den Papierkorb und mit dem Recht zum Löschen. Endgültig löscht Claude nie.

**Status:** ToDos und Milestones bekommen ihren Status nie direkt, er ergibt sich aus den Tasks.

Personen werden über ihre **E-Mail-Adresse** erkannt. Hat jemand noch kein Konto, sagt Claude das und verweist auf die Einladung durch einen Admin (Verwaltung → Benutzer).

### Beispiele

> „Zeig mir, was im Projekt Podcast offen ist.“

Claude ruft `list_projects` und `export_plan` auf und liest die Tasks mit Status `open`, `in_progress` und `review`.

> „Leg im ToDo ‚Technik‘ zwei Tasks für Oliver an.“

Claude exportiert den Plan, hängt im ToDo zwei Tasks mit den IDs `k-new-1` und `k-new-2` und `assigneeEmail` an und prüft zuerst mit dem Modus `partial` und `dry_run: true`:

```json
{
  "project_id": "12",
  "mode": "partial",
  "dry_run": true,
  "plan": {
    "format": "planer-v2",
    "revision": 41,
    "project": { "name": "Podcast", "locale": "de", "members": [ { "email": "oliver@mollo.ch", "role": "owner" } ] },
    "milestones": [ { "id": "m-31", "title": "Konzept", "todos": [ { "id": "t-77", "title": "Technik", "tasks": [
      { "id": "k-new-1", "title": "Mikrofone vergleichen", "status": "open", "assigneeEmail": "oliver@mollo.ch" },
      { "id": "k-new-2", "title": "Aufnahmesoftware testen", "status": "open", "assigneeEmail": "oliver@mollo.ch" }
    ] } ] } ]
  }
}
```

Das Ergebnis nennt zwei `CREATE` mit Pfad. Ist es richtig, wiederholt Claude den Aufruf ohne `dry_run`, und `idMap` verrät die echten IDs. Standardmäßig gehen **keine** Mails für Zuweisungen raus.

### Breaking Change gegenüber früheren Ständen

Wer den Connector schon eingerichtet hat, verliert diese Werkzeuge: `get_project` (jetzt `export_plan`), `create_project`, `set_project_member`, `create_project_plan`, `add_to_plan`, `update_plan_item`, `move_milestone` und `set_task_status` (alles jetzt `import_plan`). Die Zugänge und die Konfiguration bleiben gültig; Claude sieht beim nächsten Start die neue Liste. Eigene Anweisungen oder Skills, die die alten Namen nennen, müssen angepasst werden.

---

## Planer-Datei im Repository

Eine Planer-Datei (`*.planer.json`) in einem Repository bleibt mit der App im Gleichstand:

- **Lokal** mit `mollo-planer pull`, `push` und `sync` (`tools/planer`). `pull` holt den Plan über denselben MCP-Zugang wie Claude (`export_plan`), `push` trägt Änderungen ein (`import_plan`), zuerst als Trockenlauf mit der Liste der Änderungen. Befehle, Optionen und Exit-Codes stehen in `tools/planer/skills/planer/SKILL.md` unter „Mit der App abgleichen“.
- **Automatisch auf GitHub** mit der Vorlage `tools/planer/templates/github-action/planer-export.yml`: Die App stößt die Action nach jeder Änderung per Webhook an (Projekteinstellungen › Benachrichtigung bei Änderungen), die Action holt den Plan und committet die Datei. Einrichtung in vier Schritten: `tools/planer/templates/github-action/README.md`.

Der Zugang für die Action ist ein gewöhnlicher Zugang für Claude (90 Tage) und gehört in ein Secret des Repositories, nie in die Datei.

---

## Zugang verwalten

- **Liste:** Im Profil unter „Zugang für Claude“ stehen alle Zugänge mit Name, letzter Nutzung und Ablaufdatum.
- **Widerrufen:** Über das Papierkorb-Symbol. Claude kann den Zugang danach nicht mehr benutzen. Die Konfiguration in Claude Desktop oder Claude Code ist danach wirkungslos, kann aber stehen bleiben.
- **Neuer Zugang:** Einfach einen weiteren erzeugen. Für jeden Rechner ist ein eigener Zugang sinnvoll, dann lässt sich einer widerrufen, ohne die anderen zu verlieren.

---

## Sicherheit

- **Der Zugang ist ein Geheimnis.** Er gehört in die Konfiguration, nicht in den Chat, nicht in ein Repository und nicht in Screenshots.
- **Nur Zugänge mit der Fähigkeit `mcp`** werden angenommen. Andere Token (zum Beispiel der Login der App mit allen Rechten) lehnt der Server mit HTTP 403 ab.
- **Drosselung:** höchstens 60 Anfragen pro Minute und Person.
- **Mails:** Ohne `notify_assignees` verschickt der Connector keine Mails. Mit dieser Option gibt es eine Mail je neu zugewiesenem Task, wie beim Zuweisen in der Oberfläche.
- **Verlust:** Wurde ein Zugang kopiert oder ist er auf einem fremden Gerät gelandet, sofort widerrufen.

---

## Fehlersuche

| Symptom | Ursache und Lösung |
|---|---|
| In Claude Desktop erscheint „mollo-planer“ nicht | Claude Desktop vollständig beenden und neu starten. Ist die JSON-Datei ungültig, lädt Claude Desktop den Server nicht. Mit einem JSON-Prüfer kontrollieren. |
| Fehler „npx: command not found“ | Node.js ist nicht installiert oder nicht im Pfad. Node.js installieren, dann neu starten. |
| HTTP 401 oder „Unauthenticated“ | Zugang falsch oder widerrufen. Neuen Zugang erzeugen und die Konfiguration ersetzen. |
| HTTP 403 mit „cannot be used for the MCP server“ | Es wurde ein Token ohne die Fähigkeit `mcp` verwendet, zum Beispiel ein Login-Token. Einen Zugang aus dem Profil verwenden. |
| HTTP 429 | Zu viele Anfragen in kurzer Zeit. Eine Minute warten. |
| Claude findet Projekte nicht, die du siehst | Es werden nur Projekte gezeigt, in denen du Mitglied bist (oder Admin). Mitgliedschaft prüfen. |
| Zugang läuft ab | Zugänge gelten 90 Tage. Neuen erzeugen und die Konfiguration ersetzen. |
| Für Claude Code bleibt der Server unbekannt | Befehl im Terminal ausführen. Danach Claude Code neu starten. |

---

## Grenzen

- **Kein „Custom Connector“ über die Einstellungen von Claude Desktop.** Der braucht OAuth. Das Backend hat dafür keinen Authorization-Server. Der Weg über den Zugang funktioniert auch ohne.
- **Keine Abfrage von Benachrichtigungen oder Kommentaren** über den Connector. Die Werkzeuge decken Projekte, Mitglieder (als Teil der Datei) und Pläne ab.
- **Die Bridge braucht Node.js** auf dem Rechner. Dieser Schritt entfällt, wenn ein Connector mit OAuth gebaut würde.

---

## Pflege

- Neue Werkzeuge: eine Klasse in `backend/app/Mcp/Tools/`, Eintrag in `MolloPlanerServer`, Tests nach `backend/tests/Feature/Mcp/`. Die Werkzeuge müssen dieselben Regeln einhalten wie die GraphQL-Mutationen.
- Gültigkeit und Fähigkeit eines Zugangs stehen in `backend/app/Support/McpTokens.php`. Änderungen dort wirken auf alle Zugänge.
- Die Konfiguration für Claude Desktop wird in `frontend/app/utils/claude.ts` gebaut. Tests dazu liegen in `frontend/tests/unit/claude.test.ts`.
