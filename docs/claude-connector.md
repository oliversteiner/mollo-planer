# Claude anbinden: der Connector für Mollo Planer

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
| Werkzeuge | `backend/app/Mcp/Tools/*.php` | Einzelne Aktionen (Projekte lesen, anlegen, Mitglieder setzen, Plan anlegen) |
| Token-Prüfung | `backend/app/Http/Middleware/EnsureMcpToken.php` | Lässt nur Zugänge mit der Fähigkeit `mcp` zu |
| Token-Regeln | `backend/app/Support/McpTokens.php` | Name der Fähigkeit und Gültigkeit der Zugänge |
| Oberfläche | `frontend/app/components/profile/ClaudeAccessCard.vue` | Zugang erzeugen, anzeigen, widerrufen, Konfiguration kopieren |
| Konfiguration | `frontend/app/utils/claude.ts` | Baut den Eintrag für `claude_desktop_config.json` |

Die Werkzeuge führen GraphQL-Mutationen im Namen der Person aus. Dadurch gelten dieselben Regeln wie in der Oberfläche.

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

| Werkzeug | Was es macht | Wer darf es |
|---|---|---|
| `list_projects` | Projekte auflisten, die du siehst | alle |
| `get_project` | Ein Projekt mit Milestones, ToDos und Tasks lesen | alle, die das Projekt sehen |
| `list_users` | Benutzer und Funktionen auflisten, zum Zuweisen | alle |
| `create_project` | Projekt anlegen | Admins, Projektleitung, Koordination |
| `set_project_member` | Mitglied hinzufügen oder die Rolle ändern | nach Projektrolle, siehe unten |
| `create_project_plan` | Milestones, ToDos, Tasks und Zuweisungen in einem Projekt anlegen | nach Projektrolle |

**Löschen kann Claude nicht.** Es gibt kein Werkzeug dafür.

Die Rechte im Projekt folgen der Tabelle in `docs/rollen-plan.md` und `ProjectPermissions`. Ein Mitglied kann also Tasks anlegen, aber keine Mitglieder verwalten.

Personen werden über ihre **E-Mail-Adresse** erkannt. Hat jemand noch kein Konto, sagt Claude das und verweist auf die Einladung durch einen Admin (Verwaltung → Benutzer). Claude legt keine Konten an.

### Beispiel

> „Erstelle ein Projekt für einen Podcast mit Milestones, ToDos und Tasks; Oliver macht Technik und Schnitt, Lindita moderiert.“

Claude prüft zuerst, welche Personen es gibt, schlägt den Plan vor und legt ihn anschließend an. Standardmäßig gehen **keine** Mails für Zuweisungen raus. Nur wenn `notify_assignees` auf `true` gesetzt ist, bekommt jede zugewiesene Person eine Mail.

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
- **Mails:** Ohne `notify_assignees` verschickt der Connector keine Mails. Mit dieser Option gibt es eine Mail je zugewiesenem Task, wie beim Zuweisen in der Oberfläche.
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
- **Keine Abfrage von Benachrichtigungen oder Kommentaren** über den Connector. Die Werkzeuge decken Projekte, Mitglieder und Pläne ab.
- **Die Bridge braucht Node.js** auf dem Rechner. Dieser Schritt entfällt, wenn ein Connector mit OAuth gebaut würde.

---

## Pflege

- Neue Werkzeuge: eine Klasse in `backend/app/Mcp/Tools/`, Eintrag in `MolloPlanerServer`, Tests nach `backend/tests/Feature/Mcp/`. Die Werkzeuge müssen dieselben Regeln einhalten wie die GraphQL-Mutationen.
- Gültigkeit und Fähigkeit eines Zugangs stehen in `backend/app/Support/McpTokens.php`. Änderungen dort wirken auf alle Zugänge.
- Die Konfiguration für Claude Desktop wird in `frontend/app/utils/claude.ts` gebaut. Tests dazu liegen in `frontend/tests/unit/claude.test.ts`.
