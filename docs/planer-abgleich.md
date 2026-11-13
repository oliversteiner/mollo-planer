# Planer-Datei und App: offene Probleme und Entscheidungen

Stand: 2026-11-13. Rolle: Analyse und Plan. Ergänzt `docs/planer-datei-ssot.md`, `docs/umsetzung-planer-v2.md` und `docs/spur-b-sichere-schritte.md`.
Hier werden die Punkte geklärt, die unter „Nicht in diesem Auftrag“ in `docs/spur-b-sichere-schritte.md` stehen. Jeder Punkt bekommt eine Entscheidung und die Folgen für Spur A (`mollo-version`) und Spur B (`mollo-planer`).

| # | Problem | Stand |
|---|---|---|
| 1 | Speicherort für `release`, `commit`, `revision` | **entschieden: in der App** |
| 1a | Automatischer Export bei Änderungen, auch außerhalb der App | **entschieden: Vorschläge übernommen** |
| 2 | Import-Semantik: ergänzen oder abgleichen | **entschieden: `full` und `partial`, alle Formatfelder, `file_id`** |
| 3 | Labels in der Datei, Vorlagen (Templates) in der App | **entschieden** (3.6) |
| 4 | Ort der Werkzeuge: `mollo-version` als Abhängigkeit oder in die App integrieren | **entschieden: V2, Umzug sofort** (4.7) |
| 5 | Schreibweise der Werte, mehrsprachige Labels, Sprache der Markdown-Ansicht | **entschieden** (§5) |
| 6 | Entscheidungen zum Assistenten (`docs/assistent-ideen-konzept.md` §12) | Empfehlungen vorgelegt, offen |
| 7 | Vereinfachungen durch das neue Konzept | **entschieden:** V1 bis V5 angenommen, V6 abgelehnt (§7, §9) |
| 8 | Task-Status `idea` und `planned` | **entschieden** (§8) |
| 10 | Webhook-Details, Neuladen über `planerRevision` statt C1 | **entschieden** (§10) |
| 11 | Name des Formats: `planer/2` → `planer-v2` | **entschieden** (§11) |
| 9 | Rich Text: TipTap bleibt, Konverter Markdown ↔ TipTap-JSON | **entschieden: TipTap bleibt** (V6 abgelehnt), Umsetzung als Vorschlag (§9) |

---

## 1. Speicherort für `release`, `commit`, `revision` (entschieden 2026-11-13: A)

Die App speichert alle drei. Die Datenbank bleibt der einzige Live-Speicher, der Roundtrip `export(import(datei)) = datei` ist vollständig.

**Schema-Review, neuer Punkt A7:**

- `milestones.release_version` (String `M.m`, nullable) und `milestones.release_status` (`planned`, `released`, nullable; beide gesetzt oder beide leer). Werte nach §5.
- `tasks.commit` (String, nullable): Hash aus 7 bis 40 Kleinbuchstaben und Ziffern oder `none`.
- `projects.planer_revision` (ganze Zahl, Standard 0).
- GraphQL: Typ `Release { version: String!, status: ReleaseStatus! }`, `Milestone.release: Release`, `Task.commit: String`, `Project.planerRevision: Int!`. Eingaben in `createMilestone`/`updateMilestone` und `createTask`/`updateTask`.
- Regeln der App wie im Validator (`spec/format-v2.md` §5.3): Eine veröffentlichte Version darf keinen Task ungleich `done` haben; ein `done`-Task in einer veröffentlichten Version braucht einen Commit oder `none`. Geprüft beim Setzen von `release_status: released` und beim Ändern von Task-Status oder Commit in einer veröffentlichten Version.
- Oberfläche: Versionsnummer und Freigabe-Status im Zeitstrahl am Milestone und in dessen Formular; Commit im Task-Detail (Anzeige als Kurz-Hash, bearbeitbar).
- Rechte: Release ändern wie `milestone.update`, Commit wie Task bearbeiten.
- Umsetzung: als Nachtrag zu S3 in einer eigenen Migration (S3b in `docs/spur-b-sichere-schritte.md`).

`planer_revision` wird erst mit 1a festgelegt (wer sie erhöht).

---

## 1a. Automatischer Export bei Änderungen (entschieden 2026-11-13)

**Wunsch (Oliver):** Änderungen in der App sollen automatisch in die Datei `<projekt>.planer.json` exportiert werden können, auch außerhalb der App, als Werkzeug in `mollo-version`.

### Ausgangslage

- Die App läuft auf dem Server (Hostpoint). Sie kann nicht in ein Git-Repository auf einem Entwicklerrechner schreiben.
- Die Datei liegt im Repository des jeweiligen Projekts (z. B. FCAarau DB). Dorthin muss jemand sie holen und committen.
- Darum: **Die App liefert, das Werkzeug holt.** Die App bietet den Export an, `mollo-version` holt ihn und schreibt die Datei. Das „automatisch“ entsteht dadurch, wer das Werkzeug wann startet.

### Bausteine

**In der App (Spur B):**

1. **Export und Import als MCP-Werkzeuge:** `export_plan(project_id)` liefert das kanonische `planer/2`-Dokument, `import_plan(project_id, plan)` trägt es ein (Semantik: Problem 2). Gleiche Rechte und Token wie die anderen MCP-Werkzeuge (persönlicher Zugang mit Fähigkeit `mcp`, 90 Tage). Kein neuer REST-Endpunkt, die Regel „GraphQL ist die einzige API, dazu `/mcp`“ bleibt.
2. **Revision zählt die App:** Jede Änderung an Projekt, Milestone, ToDo, Task (inkl. Status, Reihenfolge, Papierkorb) erhöht `projects.planer_revision` um 1. Umsetzung zentral über die Observer bzw. `Ordering`, `Deletion`, `ItemStatusSync`. Kommentare zählen nicht (nicht im Format).
3. **Optional: Benachrichtigung nach außen.** Projekteinstellung „Export-Webhook“ (URL und Geheimnis). Nach einer Änderung schickt ein Queue-Job, gebündelt (z. B. höchstens einmal pro 5 Minuten je Projekt), einen POST mit Projekt-ID und Revision. Gedacht für GitHub (`repository_dispatch`), das dann eine Action startet. Läuft über die Queue per Cron, passt zu Hostpoint.

**In `mollo-version` (Spur A):**

| Befehl | Zweck |
|---|---|
| `mollo-version pull [datei] --project <id>` | holt den Export über MCP, prüft ihn mit dem Validator, schreibt die Datei kanonisch. Schreibt nur, wenn sich die Revision geändert hat. |
| `mollo-version push [datei] --project <id>` | trägt die Datei über `import_plan` ein, mit Konfliktprüfung (unten). Schreibt danach die neuen `appId` und die Revision zurück in die Datei. |
| `mollo-version sync [datei]` | erst `push` bei lokalen Änderungen, dann `pull`. |
| `mollo-version pull --watch [--interval 60]` | fragt in Abständen nach und schreibt bei neuer Revision. Für die lokale Arbeit. |

- Verbindung: Node `fetch` gegen `POST /mcp` (JSON-RPC `tools/call`), keine neue Abhängigkeit. URL und Token aus Umgebungsvariablen (`MOLLO_PLANER_URL`, `MOLLO_PLANER_TOKEN`) oder einer Datei `.mollo-planer` im Projekt, die nicht ins Repository gehört. Kein Token in der Planer-Datei.
- Das Werkzeug committet nicht selbst. Commit macht der Mensch, der Agent oder die Action.
- Vorlage für eine GitHub Action im Repository `mollo-version` (`templates/github-action/`): ausgelöst durch `repository_dispatch` (Webhook) und zusätzlich zeitgesteuert, führt `pull` aus und committet nur bei Änderungen.
- `SKILL.md` für Agenten um `pull`, `push` und `sync` ergänzen.

### Konflikte: Änderung der Spezifikation nötig

Heute erhöhen die Helfer in `mollo-version` die `revision` bei jeder Änderung, und ein Import wird abgelehnt, wenn die Datei-Revision kleiner ist als die der App. Das reicht nicht, wenn beide Seiten unabhängig zählen:

- App ändert 5 → 6, lokal ändert jemand 5 → 6. Beide sind 6, der Import würde angenommen und die Änderung in der App überschreiben, ohne dass es jemand merkt.

**Vorschlag:**

- `revision` ist der Stand der App beim letzten `pull` oder `push`. Nur die App erhöht ihn.
- Die Helfer erhöhen `revision` nicht mehr. Sie setzen stattdessen `"changed": true` auf Dateiebene (fehlt in der kanonischen Form, wenn nicht gesetzt).
- `push` ist nur erlaubt, wenn `revision` der Datei gleich der Revision der App ist. Sonst Abbruch mit Hinweis „Zuerst `pull`, Unterschiede: …“. Nach erfolgreichem `push` hat die Datei die neue Revision der App und kein `changed`.
- `pull` überschreibt keine Datei mit `changed: true`. Dann Abbruch mit Hinweis auf `push` oder `sync`.
- Ein echtes Zusammenführen (beide Seiten haben geändert) gibt es in der ersten Fassung nicht. Der Mensch entscheidet: lokale Änderungen verwerfen (`pull --force`) oder neu auf den aktuellen Stand anwenden.

Folge für Spur A: `spec/format-v2.md` §5.2 und die Helfer (P4) anpassen, Tests nachführen. Breaking Change am Format, laut Rahmen erlaubt.

### Entscheidungen (2026-11-13, alle Vorschläge übernommen)

1. Transport über die MCP-Werkzeuge `export_plan` und `import_plan`, kein eigener Endpunkt.
2. Nur die App erhöht `revision`. Lokale Änderungen setzen `"changed": true`. `push` nur bei gleicher Revision, `pull` überschreibt keine geänderte Datei. Spezifikation `planer/2` wird angepasst.
3. Optionaler Webhook pro Projekt für GitHub Actions, gebündelt höchstens einmal pro 5 Minuten.
4. Die Action committet direkt, Nachricht `Docs: planer export rev <N>`.
5. Exportieren darf, wer das Projekt sieht. Importieren nur mit den jeweiligen Schreibrechten.

### Vertrag der MCP-Werkzeuge (für Spur A und B gemeinsam)

| Werkzeug | Argumente | Ergebnis |
|---|---|---|
| `export_plan` | `project_id` | `{ "document": <planer/2, kanonisch> }`. `revision` = `projects.planer_revision`, IDs `m-<id>`, `t-<id>`, `k-<id>` aus der Datenbank (§7 V1). |
| `import_plan` | `project_id`, `plan` (planer/2), `mode` (`full` oder `partial`, Standard `full`), `notify_assignees` (optional, Standard `false`) | dasselbe wie `export_plan` nach dem Import, also echte IDs statt `-new-` und neue `revision`. Fehler als MCP-Fehler mit Pfad im Dokument. |

`import_plan` lehnt ab, wenn `plan.revision` ungleich `planer_revision` ist (Fehlertext nennt beide Zahlen). Was der Import mit dem Inhalt macht, legt Problem 2 fest.

### Arbeitspakete

**Spur A (`mollo-version`), sofort möglich:**

- A-S1: `spec/format-v2.md` §5.2 und Schema: `revision` nur von der App, neues optionales Feld `changed` (Boolean, nur `true` oder fehlend). Helfer (P4) setzen `changed: true` statt `revision` zu erhöhen. Tests und Golden Files nachführen. `CHANGELOG.md`: Breaking Change, Version 2.0.0.
- A-S2: `pull`, `push`, `sync`, `pull --watch` gegen den Vertrag oben. Verbindung über `fetch` an `POST /mcp` (JSON-RPC `tools/call`). Konfiguration über `MOLLO_PLANER_URL`, `MOLLO_PLANER_TOKEN` oder `.mollo-planer` (in `.gitignore`). Tests mit einem lokalen Testserver, der den Vertrag nachbildet.
- A-S3: Vorlage `templates/github-action/planer-export.yml` (`repository_dispatch` und Zeitplan, `pull`, Commit nur bei Änderung). `SKILL.md` ergänzen.

**Spur B (`mollo-planer`), sofort möglich (Nachtrag S3c in `docs/spur-b-sichere-schritte.md`):**

- Zählung `projects.planer_revision`: +1 bei jeder Änderung an Projekt, Milestone, ToDo, Task (Felder, Status, Reihenfolge, Zuweisung, Labels, Papierkorb, Wiederherstellen). Kommentare zählen nicht. Mehrere Änderungen in einer Mutation oder einem Import ergeben +1, nicht mehrere.

**Spur B, nach Problem 2, 3 und 5:** `export_plan`, `import_plan`, Webhook.

---

## 2. Import-Semantik (entschieden 2026-11-13, Empfehlungen übernommen)

### 2.1 Zwei Modi

| Modus | Für | Elemente in der App, die im Dokument fehlen | Revision |
|---|---|---|---|
| `full` | Datei (`push`, `import_plan` Standard) | kommen in den Papierkorb, als **ein** Löschvorgang (eine `deletion_id`), wiederherstellbar | `plan.revision` muss gleich `planer_revision` sein |
| `partial` | Assistent, Ausschnitte, `import_plan` mit `mode: partial` | bleiben unverändert, nie löschen | wird nicht geprüft (nur Anker, siehe `docs/assistent-ideen-konzept.md` §6.2) |

- Löschen im Modus `full` braucht die Löschrechte der Person (`milestone.delete`, `todo.delete`, `task.delete`). Fehlt eines, bricht der ganze Import ab und nennt die Elemente.
- Alles oder nichts in einer Transaktion. Ein Import ergibt `planer_revision` +1 (S3c).
- `full` auf ein archiviertes Projekt: Abbruch, wie alle Schreibwege.
- Ein Element mit `appId`, das zu einem anderen Projekt gehört oder im Papierkorb liegt: Abbruch mit Pfad.

### 2.2 Welche Felder ein Import schreibt

- **Alle Felder des Formats** werden übernommen: Projekt (`name`, `description`, `locale`, `members`), Milestone (`title`, `description`, `dueDate`, `release`), ToDo (`title`, `description`, `dueDate`), Task (`title`, `description`, `dueDate`, `status`, `assigneeEmail`, `labelNames`, `userStory`, `commit`) und die **Reihenfolge** (Array-Reihenfolge), auch über Elternteile hinweg (Task in anderes ToDo, ToDo in anderen Milestone).
- **Nicht im Format, bleibt unverändert:** `icon`, Kommentare, `created_by`, Zeitstempel. Neue Elemente bekommen `created_by` = importierende Person.
- **Wege:** Statusänderungen über `moveTask` (Rechte `task.move`, Observer), Verschieben über `moveTaskToTodo`/`moveTodo`/`moveMilestone` bzw. `Ordering`, Zuweisung über `assignTask` (Rechte `task.assign`/`task.assignSelf`), Mitglieder über `setProjectMember`/`removeProjectMember` (Recht `members.manage`). Damit gelten dieselben Rechte und Mails wie in der App. Mails nur mit `notify_assignees: true`.
- **Mitglieder im Modus `full`:** Wer in der App Mitglied ist und im Dokument fehlt, wird entfernt (seine Tasks werden unzugewiesen, wie heute). Der letzte Owner kann nicht entfernt werden, dann Abbruch.
- Die Freigabe-Regeln (A7) werden nach dem Import für das Ergebnis geprüft, nicht Schritt für Schritt.

### 2.3 Datei-IDs in der App (ersetzt durch V1 in §7)

- Neue Spalte `file_id` (String, nullable) an `milestones`, `todos`, `tasks`, eindeutig je Projekt (`unique(project_id, file_id)`, bei Milestones über `project_id` direkt, bei ToDos und Tasks über `project_id` aus A1).
- Der Import speichert die `id` aus dem Dokument in `file_id`. Der Export schreibt `file_id` als `id`.
- Elemente ohne `file_id` (in der App angelegt) bekommen beim Export die nächste freie Nummer je Präfix, und der Export speichert sie sofort in `file_id`. Damit bleibt die ID über alle weiteren Exporte stabil.
- „Nächste freie Nummer“ zählt über alle je vergebenen Nummern, auch gelöschte (im Papierkorb oder endgültig gelöscht). Dafür je Projekt ein Zähler `projects.planer_next_ids` (JSON mit `m`, `t`, `k`), damit eine Nummer nie wiederverwendet wird (`spec/format-v2.md` §5.2).
- Die Vergabe beim Export erhöht `planer_revision` nicht (nur Metadaten).
- Umsetzung: sicherer Schritt S3d in `docs/spur-b-sichere-schritte.md`.

### 2.4 Folge für Spur A

- `push` sendet `mode: full`. Ein späterer Befehl für Ausschnitte (`push --partial <datei>`) ist möglich, aber nicht Teil dieses Auftrags.
- Keine Änderung an der Spezifikation nötig.

---

## 3. Labels in der Datei und Vorlagen (Templates)

### 3.1 Entscheidungen (Oliver, 2026-11-13)

- **Das Label-Set steht vollständig in der Datei** (Variante b, erweitert): nicht nur ein Verweis, sondern die Labels selbst.
- **In der Markdown-Ansicht** erscheint es **ganz am Schluss als Legende**.
- **Die mitgelieferten Sets der App sind Vorlagen (Templates).**
- **Jede Planer-Datei kann als Ausgangspunkt für eine neue Vorlage dienen.**
- **Die App kann Vorlagen erstellen, speichern und verwalten.**

### 3.2 Folge für das Modell der App

Heute sind Label-Sets app-weit, ein Projekt verweist auf ein Set (`projects.label_set_id`), mitgelieferte Sets sind unveränderlich und werden zum Anpassen dupliziert. Mit Labels in der Datei und Vorlagen ändert sich das:

| Heute | Neu |
|---|---|
| `label_sets` (app-weit, geteilt) | `templates` (Vorlagen, app-weit) |
| `labels.label_set_id` | `labels.project_id`: **jedes Projekt hat eigene Labels**, kopiert aus einer Vorlage |
| `projects.label_set_id` | entfällt; optional `projects.template_id` als Herkunft (nur Anzeige) |
| Seite `/label-sets` | Seite `/templates` |
| `labelSets.manage`, `labelSets.manageAny` | `templates.manage`, `templates.manageAny` (gleiche Rollen) |
| Set wechseln, Tasks behalten alte Labels | Labels des Projekts bearbeiten (`project.update`), oder Labels aus einer Vorlage übernehmen |

Gründe:
- Ein Import im Modus `full` ersetzt die Labels des Projekts durch die der Datei. Wären Labels geteilt, würde ein Import andere Projekte verändern.
- Die Datei beschreibt dann genau, was im Projekt gilt. `labelNames` lässt sich schon im Validator prüfen (bisher erst beim Import, `spec/format-v2.md` §5.4).

### 3.3 Format: `project.labels`

```json
"project": {
  "name": "Mollo Planer",
  "locale": "DE",
  "members": [ … ],
  "labels": [
    { "key": "feature", "names": { "de": "Feature", "en": "Feature" }, "category": "type", "color": "green" },
    { "key": "bug", "names": { "de": "Fehler", "en": "Bug" }, "category": "type", "color": "red" },
    { "key": "backend", "names": { "de": "Backend" }, "category": "area", "color": "cyan" }
  ]
}
```

- `key`: Pflicht, 1 bis 40 Zeichen, `a-z`, `0-9`, `_`, `-`, eindeutig je Datei. Stabiler Bezug für `labelNames`.
- `names`: Pflicht, Objekt mit Sprachcode → Name (1 bis 40 Zeichen). Der Name in der Sprache des Projekts (`project.locale`) ist Pflicht, weitere Sprachen optional. Je Sprache eindeutig in der Datei. Mehrsprachigkeit siehe §5.2.
- `category`: optional, 1 bis 40 Zeichen.
- `color`: Pflicht, einer der zehn Töne der App (`gray`, `red`, `orange`, `yellow`, `green`, `cyan`, `blue`, `indigo`, `purple`, `pink`).
- Reihenfolge = Array-Reihenfolge (wie `labels.position`).
- Höchstens 30 Labels (Grenze der App).
- `labelNames` am Task enthält **Schlüssel** aus `project.labels`. Der Validator prüft das. (Der bisherige Name `labelNames` bleibt, um das Format nicht unnötig zu brechen; Umbenennung in `labels` ist möglich, siehe 3.6.)
- Übersetzung: Mitgelieferte Vorlagen haben Namen in `de` und `en`. Beim Anlegen eines Projekts werden alle Sprachen kopiert (§5.2).

**Markdown-Ansicht:** Legende am Schluss, nach dem letzten Milestone:

```markdown
---

## Labels

| Schlüssel | Name | Kategorie | Farbe |
|---|---|---|---|
| feature | Feature | type | green |
| bug | Fehler | type | red |
| backend | Backend | area | cyan |
```

Sprache der Ansicht und Legende mit Übersetzungen: §5.3.

- Am Task stehen in der Zeile `Labels:` die **Namen** (lesbar), `md2json` ordnet sie über die Legende den Schlüsseln zu.
- Die Tabelle ist die einzige Tabelle in der Ansicht. `md2json` erkennt sie an der Überschrift `## Labels` nach dem letzten Trenner.

### 3.4 Vorlagen in der App

**Was eine Vorlage ist:** ein **gültiges `planer/2`-Dokument mit festen Werten** (§3.8). Gespeichert als JSON in `templates.content` und mit demselben Validator geprüft, plus die Zusatzregeln von `validate --template`. Umfang siehe 3.6.

**Mitgelieferte Vorlagen:** „Softwareentwicklung“, „Event“, „Podcast“ aus `BuiltinLabelSets`, mit Namen in DE und EN. Unveränderlich, nur duplizieren.

**Funktionen:**

| Funktion | Wo | Recht |
|---|---|---|
| Vorlagen auflisten, ansehen | `/templates` | alle Angemeldeten außer `lesend` |
| Neue Vorlage leer anlegen, Labels bearbeiten | `/templates` (Editor wie heute `/label-sets`) | `templates.manage` |
| Vorlage duplizieren (auch mitgelieferte) | `/templates` | `templates.manage` |
| **Vorlage aus Datei**: `.planer.json` hochladen, prüfen, als Vorlage speichern (App-Bezüge werden entfernt) | `/templates` | `templates.manage` |
| **Vorlage aus Projekt**: „Als Vorlage speichern“ in den Projekteinstellungen | `/projects/:id/settings` | `templates.manage` und Projekt sichtbar |
| Vorlage als Datei herunterladen (`.planer.json`) | `/templates` | wer sie sehen darf |
| Vorlage ändern, löschen | `/templates` | Ersteller mit `templates.manage`, sonst `templates.manageAny`; mitgelieferte nie |
| **Projekt aus Vorlage** anlegen | `/projects` (Neues Projekt) | `projects.create` |
| **Labels aus Vorlage übernehmen** in ein bestehendes Projekt | `/projects/:id/settings` | `project.update` |

- Labels aus Vorlage übernehmen: ersetzen oder ergänzen (Auswahl). Beim Ersetzen bleiben Labels, die an Tasks hängen, erhalten und werden ans Ende gestellt, bis jemand sie löscht (gleiches Verhalten wie heute beim Set-Wechsel).
- Löschen einer Vorlage betrifft keine Projekte, weil die Projekte Kopien haben.
- Papierkorb: Vorlagen sind wie Label-Sets ausgenommen (endgültig löschen mit Bestätigung).

**MCP:** `list_templates`, `create_project` mit optionalem `template_id`. Später `save_template_from_project`.

**Assistent:** Bei neuen Projekten schlägt er eine Vorlage vor (oder fragt), bei bestehenden nutzt er die Labels des Projekts (wie geplant).

### 3.5 Folgen für die Spuren

**Spur A (`mollo-version`):**
- Spezifikation und Schema: `project.labels` (3.3), `labelNames` enthält Schlüssel, Validator prüft Verweise. Vorlagen-Regeln (3.4) als eigener Prüfmodus `validate --template`.
- `render`: Legende am Schluss. `md2json`: Legende lesen, Namen am Task auf Schlüssel abbilden.
- `convert v1-zu-v2`: erzeugt die Labels aus der Liste der Arten (`feature`, `bug`, `planning`, `research`, `design`, `blocked`) mit den Namen und Farben der mitgelieferten Vorlage „Softwareentwicklung“.
- Neuer Befehl `template <datei> [--out]`: macht aus einer Planer-Datei eine Vorlage (entfernt App-Bezüge nach 3.4).
- Beispiel `fixtures/beispiel-planer.planer.json` und `docs/beispiel-planer.md` mit Legende nachführen.
- Version 2.0.0 (zusammen mit A-S1).

**Spur B (`mollo-planer`):** Umbau Label-Sets → Vorlagen und Projekt-Labels, neue Seite `/templates`, Projekt aus Vorlage, „Als Vorlage speichern“, Upload. Wird nach der Klärung von 3.6 als sicherer Schritt S7 in `docs/spur-b-sichere-schritte.md` aufgenommen. **Bis dahin den Label-Code nicht umbauen.**

### 3.6 Entscheidungen zum Umfang (Oliver, 2026-11-13)

1. **Voller Umfang:** Eine Vorlage enthält immer die Labels und optional das ganze Plan-Gerüst: Milestones, ToDos und Tasks mit Titel, Beschreibung, User-Story, Labels und Reihenfolge, Milestones optional mit `release.version`. Die festen Werte stehen in §3.8. Beim Anlegen eines Projekts aus der Vorlage wird das Gerüst vollständig angelegt (Import `full` in ein leeres Projekt), die echten IDs vergibt der Import.
2. **Fälligkeiten:** in der ersten Fassung weggelassen. Relative Fälligkeiten („Tag 14 nach Projektstart“) sind eine mögliche spätere Erweiterung.
3. **Feldname:** `labelNames` am Task heißt neu **`labels`** und enthält Schlüssel aus `project.labels`. Breaking Change am Format.
4. **Sichtbarkeit von Vorlagen:** Alle Vorlagen sind für alle Angemeldeten lesbar, auch für die Rolle `lesend`. Ändern nach `templates.manage` bzw. `templates.manageAny`.

### 3.8 Feste Werte einer Vorlage (entschieden 2026-11-13, korrigiert §3.4 und §3.6)

Eine Vorlage ist ein **gültiges `planer/2`-Dokument**. Es gibt kein eigenes Vorlagen-Format und kein zweites Schema. `validate --template` ist die normale Prüfung plus diese Regeln, `template <datei>` setzt genau diese Werte:

| Feld | Wert in der Vorlage |
|---|---|
| `revision` | `0` |
| `changed` | fehlt |
| `id` (Milestone, ToDo, Task) | nur `-new-`-IDs: `m-new-1`, `t-new-1`, `k-new-1` … (nach V1, T5) |
| `project.members` | `[]` |
| `assigneeEmail` | fehlt |
| `dueDate` (alle Ebenen) | fehlt |
| `commit` | fehlt |
| `release` | optional; wenn gesetzt, `status: planned` |
| Task-`status` | immer `planned` |
| `project.labels` | Pflicht, mindestens ein Label |
| `project.name`, `project.description` | Name und Beschreibung der Vorlage |
| `project.locale` | Sprache der Vorlage (Titel, Beschreibungen) |

Grund: Ein Format, ein Schema, ein Importer ohne Sonderfall (Grundregel „eine Beschreibung eines Plans“). Hinweis des Umsetzungs-Agenten vom 2026-11-13: §3.4 und §3.6 widersprachen dem Schema (fehlende `id`, `revision`, `release.status`). Das ist hiermit korrigiert.

**Folge für die Reihenfolge:** T5 (IDs aus der Datenbank, `-new-`-IDs) kommt **vor** T4-Rest.

### 3.7 Arbeitspakete

**Spur A (`mollo-version`), nach 3.6 sofort möglich (A-S4):**

- `spec/format-v2.md` und Schema: `project.labels` (3.3), Task-Feld `labels` statt `labelNames`, Validator prüft Verweise auf `project.labels`.
- Vorlagen: Prüfmodus `validate --template` (Regeln aus 3.4 und 3.6), Befehl `template <datei> [--out]`.
- `render` mit Legende am Schluss, `md2json` liest die Legende und bildet Namen auf Schlüssel ab.
- `convert v1-zu-v2` erzeugt `project.labels` aus den Arten mit Namen und Farben der Vorlage „Softwareentwicklung“ (Schlüssel `research`, `design`, `feature`, `bug`, `planning`, `blocked`; Namen und Farben wie `BuiltinLabelSets.php` in `mollo-planer`).
- Fixtures, Golden Files, `fixtures/beispiel-planer.planer.json` und `mollo-planer/docs/beispiel-planer.md` nachführen (`backend` als eigenes Label in der Beispieldatei definieren).
- Zusammen mit A-S1 als Version 2.0.0.

**Spur B (`mollo-planer`), sicherer Schritt S7** in `docs/spur-b-sichere-schritte.md`.

---

## 5. Schreibweise der Werte und Mehrsprachigkeit (entschieden 2026-11-13)

### 5.1 Alle Werte klein und auf Englisch

| Feld | Werte in Datei und Datenbank | Bisher in `planer/2` |
|---|---|---|
| `project.locale` | `de`, `en` | `DE`, `EN` |
| `members[].role` | `owner`, `manager`, `member` | unverändert |
| `task.status` | `open`, `in_progress`, `review`, `done` | unverändert |
| `release.status` | `planned`, `released` | `geplant`, `veröffentlicht` |
| `task.commit` ohne Hash | `none` | `ohne` |
| `label.color` | `gray`, `red`, … | unverändert |
| `label.category` | Schlüssel, z. B. `type`, `phase`, `area` | unverändert |

- Deutsche (oder englische) Wörter erscheinen nur in der Markdown-Ansicht und in der Oberfläche. `render` und `md2json` übersetzen in beide Richtungen.
- Breaking Change am Format, gehört in T1. A7 (S3b) speichert gleich `planned`/`released`/`none`.
- Assistent und MCP: `PlanSchema::ROLES` (heute `OWNER`, …) und die Locale-Werte werden auf die kleinen Werte umgestellt, wenn der Assistent auf `planer/2` umgebaut wird (B5a).

### 5.2 Mehrsprachige Labels

- Jedes Label hat **`names`**: ein Objekt Sprachcode → Name (§3.3). Pflicht ist der Name in der Sprache des Projekts, weitere Sprachen sind optional.
- Unterstützte Sprachcodes: die Sprachen der App (`de`, `en`). Eine neue Sprache der App erweitert die Liste in `spec/`.
- **App:** Spalte `labels.names` (JSON) statt `labels.name`, ebenso in Vorlagen. Eindeutig je Projekt und Sprache.
- **Anzeige in der App:** Name in der Sprache der Oberfläche der Person, sonst in der Sprache des Projekts.
- **Bearbeiten:** Im Label-Editor (Projekt und Vorlage) je Label ein Feld pro Sprache, die Sprache des Projekts ist Pflicht. Hinweis bei fehlenden Übersetzungen.
- **Mitgelieferte Vorlagen** haben beide Sprachen (heute schon über die Übersetzungsdateien). Die Übersetzungen wandern aus `lang/` in den Inhalt der Vorlage, damit eine Kopie sie mitnimmt.
- **Kategorien** bleiben Schlüssel (`type`, `phase`, …). Übersetzt werden sie nur für die mitgelieferten Schlüssel über die Übersetzungsdateien der App. Eigene Kategorien erscheinen, wie sie geschrieben sind.
- **Abgleich in `md2json`:** Ein Name am Task wird über die Legende in jeder Sprache erkannt.

### 5.3 Sprache der Markdown-Ansicht und Legende

- **Sprache wählbar:** `mollo-planer render --lang de|en` (Standard: Sprache des Projekts). Übersetzt werden die festen Wörter (Status, Fällig, Zuständig, Labels, Commit, `planned`/`released`, `none`, Rahmen der User-Story „Als … möchte ich …, damit …“ bzw. „As … I want …, so that …“) und die Label-Namen. Inhalte (Titel, Beschreibungen, Texte der User-Story) bleiben, wie sie sind.
- Die gewählte Sprache steht in der Kopfzeile der Ansicht (`<!-- Ansicht von planer/2, revision N, lang de -->`), damit `md2json` die festen Wörter richtig liest.
- **Legende am Schluss**, immer mit der gewählten Sprache. **Optional mit Übersetzungen:** `render --legend-translations` fügt je weitere Sprache eine Spalte hinzu:

```markdown
## Labels

| Schlüssel | Name | en | Kategorie | Farbe |
|---|---|---|---|---|
| feature | Feature | Feature | type | green |
| bug | Fehler | Bug | type | red |
| backend | Backend | – | area | cyan |
```

  `–` steht für eine fehlende Übersetzung. `md2json` liest die zusätzlichen Spalten als `names`.
- **In der App:** Download „Als Markdown“ in den Projekteinstellungen bzw. der Zeitachse, mit Auswahl der Sprache und Schalter „Übersetzungen in der Legende“. Umgesetzt im Frontend mit `tools/planer/core` (gleicher Code wie die CLI). Daneben Download „Als planer.json“.

### 5.4 Folgen für die Schritte

- **T1** zusätzlich: Werte nach §5.1 in Spezifikation, Schema, Validator, `convert` (v1 `Status: geplant` → `planned`, `Ohne Commit` → `none`), Fixtures.
- **T4** zusätzlich: `names` statt `name` (§5.2), `render --lang`, `--legend-translations`, `md2json` mit Sprache aus der Kopfzeile und mehrsprachiger Legende.
- **S3b:** Werte `planned`, `released`, `none`.
- **S7:** `labels.names` und Vorlagen mit `names`, Anzeige nach Sprache der Oberfläche, Editor mit Feldern je Sprache.
- **Neu, nach Export und Import (P6):** Download als Markdown und als `planer.json` in der App (§5.3).

---

## 9. Rich Text: Konverter Markdown ↔ TipTap-JSON

**Entscheidung (Oliver, 2026-11-13):** Die App bleibt bei TipTap-JSON als Speicherform. Für Import, Export und Assistent wird ein Konverter gebaut. Vorher prüfen, ob es Pakete gibt; sonst festlegen, welche Formatierungen umgesetzt werden und welche selten genutzten Fälle ihre Formatierung verlieren und als Klartext ankommen.

### 9.1 Wo der Konverter gebraucht wird

- **Nur in PHP** (App auf dem Server): Import und Export von `planer/2`, Entwurf des Assistenten, Vorlagen.
- **Nicht in `tools/planer`:** Die Datei enthält nur Markdown. Die CLI prüft Markdown gegen die Whitelist und schreibt es kanonisch, kennt aber kein TipTap-JSON.
- **Nicht im Frontend:** Es bekommt TipTap-JSON über GraphQL. Der Download „Als Markdown“ nutzt den Export (Markdown kommt aus PHP).

### 9.2 Vorhandene Pakete

| Paket | Kann | Bewertung |
|---|---|---|
| `league/commonmark` | Markdown parsen (AST), CommonMark-Spezifikation, Erweiterungen | **schon installiert** (Abhängigkeit von Laravel), gepflegt |
| `ueberdosis/tiptap-php` | TipTap-JSON ↔ **HTML**, Bereinigen | offizielles Paket, sehr verbreitet, aber **kein Markdown** |
| `league/html-to-markdown` | HTML → Markdown | verbreitet, letzte Version 2023 |
| `hyvor/phrosemirror`, `nadar/prosemirror-json-parser` | ProseMirror-JSON lesen, nach HTML | kein Markdown |
| `@tiptap/markdown` (JavaScript) | Markdown ↔ TipTap-JSON, auch serverseitig | nur Node, Hostpoint hat kein Node |
| TipTap Convert (Cloud-API) | Markdown ↔ TipTap-JSON | kostenpflichtig, externer Dienst, Inhalte verlassen den Server: **nicht geeignet** |

**Ein fertiges PHP-Paket für Markdown ↔ TipTap-JSON gibt es nicht.** Möglich wäre eine Kette über HTML (`commonmark` → HTML → `tiptap-php` → JSON und zurück `tiptap-php` → HTML → `html-to-markdown`). Nachteile: zwei zusätzliche Pakete, HTML als Zwischenschritt, die Markdown-Ausgabe von `html-to-markdown` müsste nachträglich auf die kanonische Form gebracht werden (Listenzeichen, Zeilenumbrüche, Einrückung), sonst ist der Roundtrip nicht byte-gleich.

### 9.3 Vorschlag: schlanker eigener Konverter auf `league/commonmark`

- **Markdown → TipTap-JSON:** `league/commonmark` parst zum AST (kein HTML). Ein Besucher (`App\Planer\RichText\MarkdownToTiptap`) übersetzt die erlaubten Knoten direkt in TipTap-Knoten. Etwa 150 Zeilen.
- **TipTap-JSON → Markdown:** eigener Serialisierer (`App\Planer\RichText\TiptapToMarkdown`), der direkt die **kanonische Form** schreibt. Etwa 150 Zeilen. Die Whitelist ist klein, ein eigener Serialisierer ist einfacher als eine Kette mit Nachbearbeitung.
- **Keine neue Abhängigkeit.**
- **Kanonische Markdown-Form** (in `spec/format-v2.md` festschreiben, gilt für CLI und PHP gleich):
  - Absätze durch eine Leerzeile getrennt.
  - Überschriften als `## ` und `### `.
  - Aufzählung mit `- `, nummerierte Liste mit `1. `, `2. ` … (fortlaufend ab 1).
  - Verschachtelte Listen: Einrückung um die Breite des Listenzeichens (2 Zeichen bei `- `, 3 bei `1. `).
  - Zitat mit `> ` je Zeile.
  - Fett `**…**`, kursiv `*…*`, Inline-Code `` `…` ``, Link `[Text](URL)`.
  - Harter Zeilenumbruch als `\` am Zeilenende (nicht zwei Leerzeichen, die unsichtbar sind).
  - Zeichen mit Markdown-Bedeutung im Text werden mit `\` maskiert, nur wo nötig.
- **Gemeinsame Testfälle:** `spec/fixtures/richtext/` mit Paaren `<name>.md` und `<name>.json` (TipTap). PHP prüft beide Richtungen. `tools/planer` prüft, dass jede `.md` die Whitelist besteht und kanonisch ist. So laufen PHP und CLI nicht auseinander.

### 9.4 Was umgesetzt wird (voll, verlustfrei hin und zurück)

| Markdown | TipTap |
|---|---|
| Absatz | `paragraph` |
| `## `, `### ` | `heading` Ebene 2, 3 |
| `- ` Liste | `bulletList` / `listItem` |
| `1. ` Liste | `orderedList` / `listItem` |
| verschachtelte Listen, auch gemischt (bis 3 Ebenen) | verschachtelte `bulletList`/`orderedList` |
| mehrere Absätze in einem Listenpunkt | mehrere `paragraph` im `listItem` |
| `> ` Zitat, auch mit Absätzen und Listen darin | `blockquote` |
| `\` am Zeilenende | `hardBreak` |
| `**fett**`, `*kursiv*`, `***beides***` | Marks `bold`, `italic`, kombiniert |
| `` `Code` `` | Mark `code` (ohne weitere Marks, wie in TipTap) |
| `[Text](https://…)`, `http`, `mailto`, auch mit fett/kursiv im Text | Mark `link` |
| `<https://…>` (Autolink) | Mark `link`, Export als `[https://…](https://…)` |

### 9.5 Was vereinheitlicht wird (Inhalt bleibt, Schreibweise ändert sich)

Diese Schreibweisen werden gelesen, aber in der kanonischen Form zurückgeschrieben. Die Datei sieht nach einem Roundtrip anders aus, der Inhalt ist derselbe. Der Validator meldet sie als **Hinweis** (nicht kanonisch), nicht als Fehler.

- `__fett__`, `_kursiv_` → `**fett**`, `*kursiv*`
- `* ` oder `+ ` als Listenzeichen → `- `
- Überschriften mit Unterstrich (`Titel` + `===`) → `## `
- harter Umbruch mit zwei Leerzeichen → `\`
- Referenz-Links (`[Text][1]` … `[1]: https://…`) → `[Text](https://…)`
- HTML-Entitäten (`&amp;`) → Zeichen
- nummerierte Liste mit Lücken oder `1.` überall → fortlaufend

### 9.6 Was die Formatierung verliert und als Klartext ankommt (selten)

Diese Fälle sind in der Datei nach dem Validator **Fehler** (Whitelist). Sie kommen aber vor, wenn Text aus freier Eingabe stammt: Antwort des Assistenten, Idee, MCP-Ausschnitt (`partial`), Einfügen aus anderen Quellen. Dort bricht der Konverter nicht ab, sondern macht Klartext daraus und meldet eine Warnung (im Assistenten in der Vorschau, im MCP in der Antwort).

| Konstrukt | wird zu |
|---|---|
| Überschrift Ebene 1 | Ebene 2 |
| Überschrift Ebene 4 bis 6 | Ebene 3 |
| Überschrift in Listenpunkt oder Zitat | Absatz mit fettem Text |
| Startnummer einer nummerierten Liste (`5. …`) | Liste ab 1 (Nummer geht verloren) |
| Liste tiefer als 3 Ebenen | Ebene 3, tiefere Punkte als Punkte der 3. Ebene |
| Code-Block (```` ``` ````, eingerückter Code) | Absatz, jede Zeile als Inline-Code, Zeilen mit `hardBreak` |
| Tabelle | je Zeile ein Absatz, Zellen mit ` · ` getrennt |
| Bild `![Alt](URL)` | Link mit dem Alt-Text auf die URL (bei `http`/`https`), sonst nur Alt-Text |
| Link mit anderem Schema (`javascript:`, `ftp:`, `file:` …) | nur der Linktext |
| Linktitel `[x](url "Titel")` | Titel geht verloren |
| Durchgestrichen `~~…~~` | Klartext |
| Aufgabenliste `- [ ] …` in einer Beschreibung | Aufzählung, `[ ]` bzw. `[x]` bleibt als Text |
| Trennlinie `---` | entfällt |
| HTML (Block oder inline) | Tags entfernt, Text bleibt |
| Fußnoten | Klartext, Verweis `[^1]` bleibt als Text |
| Code mit Fett/Kursiv kombiniert | nur Code |

### 9.7 Folgen

- **Assistent:** Die Wiederholung bei unerlaubtem Markdown (Konzept §6.4) entfällt. Der Konverter macht Klartext daraus, die Vorschau zeigt die Warnung. Einfacher und ohne zweiten Aufruf an Claude.
- **Datei:** unverändert streng. `validate` meldet Konstrukte aus 9.6 als Fehler, Schreibweisen aus 9.5 als Hinweis.
- **Neuer Schritt S3e** in `docs/spur-b-sichere-schritte.md`: Konverter mit gemeinsamen Testfällen. Unabhängig von Export und Import, sofort möglich.
- **`tools/planer`:** kanonische Markdown-Form (9.3) in Spezifikation und `json`/`validate` (Hinweis bei Abweichung), Testfälle in `spec/fixtures/richtext/`. Als Teil von T5 oder eigener kleiner Schritt T6.

## 8. Task-Status `idea` und `planned` (entschieden 2026-11-13)

**Entscheidung (Oliver):** Der Task-Status bekommt zwei weitere Werte: **Idee** und **geplant**. Werte nach §5.1 klein und englisch: `idea`, `planned`.

### 8.1 Reihenfolge und Bedeutung

| Wert | Oberfläche (de / en) | Bedeutung |
|---|---|---|
| `idea` | Idee / Idea | Vorschlag im Projekt, noch nicht beschlossen |
| `planned` | Geplant / Planned | beschlossen, noch nicht begonnen und noch nicht zur Arbeit freigegeben |
| `open` | Offen / Open | bereit zur Arbeit |
| `in_progress` | In Arbeit / In progress | |
| `review` | Prüfung / Review | |
| `done` | Erledigt / Done | |

### 8.2 Folgen (Vorschläge 1 bis 11 angenommen am 2026-11-13)

1. **Berechneter Status und Fortschritt:** Tasks mit `idea` zählen nicht. Ein ToDo ist `done`, wenn es mindestens einen Task außer `idea` hat und alle diese `done` sind. Der Fortschritt rechnet ohne `idea`. `planned` zählt normal mit (ist beschlossen).
2. **Freigabe (A7):** Eine Version mit `release.status: released` darf weiterhin nur `done`-Tasks haben. Ideen müssen vorher verschoben, auf einen anderen Status gesetzt oder gelöscht werden.
3. **Kanban:** sechs Spalten. `idea` und `planned` lassen sich einklappen bzw. ausblenden (Schalter „Ideen und Geplantes anzeigen“, pro Person gespeichert im Browser), damit das Board für die tägliche Arbeit schlank bleibt.
4. **Meine Tasks und Dashboard:** „Offene Tasks“ zeigen `planned` bis `review`, ohne `idea`.
5. **Mails:** Die Zuweisungs-Mail geht erst, wenn der Task mindestens `planned` ist. Wechselt ein zugewiesener Task von `idea` auf einen anderen Status, geht die Zuweisungs-Mail dann. Sonst bleiben die Mail-Regeln unverändert (Statuswechsel lösen keine Mail aus).
6. **Neuer Task in der Oberfläche:** Standard bleibt `open`. Im Formular wählbar.
7. **Assistent und Vorlagen:** Vom Assistenten vorgeschlagene Tasks bekommen standardmäßig `planned`. Tasks im Gerüst einer Vorlage sind `planned` (statt `open`, §3.6 Nr. 1).
8. **Rechte:** Status setzen wie bisher über `task.move`.
9. **Abgrenzung zur Ideen-Seite (B1):** Ein Task mit `idea` gehört zu einem Projekt, einem Milestone und einem ToDo und ist für alle Mitglieder sichtbar. Eine Idee auf der Ideen-Seite ist privat (Autor und Admins) und braucht kein Projekt. Beides bleibt. Im Assistenten heißt die bisherige Option „In Ideen ablegen“ weiterhin so (private Idee). Neu als fünfte Option bei der Milestone-Auswahl ist nicht nötig, weil ein Task mit `idea` ohnehin einen Milestone braucht. Der Assistent kann Tasks aber als `idea` vorschlagen, wenn die Person etwas nur festhalten will.
10. **Markdown-Ansicht:** `[i]` für `idea`, `[p]` für `planned` (entschieden), zu den bestehenden `[ ]` open, `[~]` in_progress, `[?]` review, `[x]` done.
11. **Umwandlung v1:** `[ ]` bleibt `open`, keine Abbildung auf die neuen Werte.

### 8.3 Folgen für die Schritte

- **T4** (läuft): Schema, Validator, `render`/`md2json` mit den neuen Werten und Markierungen, Fixtures.
- **S3:** `TaskStatus` in PHP, GraphQL und Frontend um `idea` und `planned` erweitern, `ItemStatusSync` und Fortschritt nach 8.2 Nr. 1, Kanban-Spalten und Schalter nach Nr. 3, Filter nach Nr. 4, Mails nach Nr. 5.
- **S7** (Vorlagen): Gerüst-Tasks `planned`.

## 7. Vereinfachungen durch das neue Konzept (entschieden 2026-11-13)

Stand der Prüfung: `mollo-planer` bis `072fcbb` (U0 und T1 erledigt).

Mit `planer/2` als einziger Beschreibung eines Plans, Import/Export in der App, Vorlagen als Dokumente und einem Repository gibt es mehrere Stellen, die doppelt sind oder ganz entfallen können.

### V1. IDs aus der Datenbank statt `appId` plus Datei-ID (entschieden 2026-11-13)

**Heute geplant:** zwei IDs je Element (`id` = Datei-ID `k-001`, `appId` = Datenbank-ID), dazu in der App `file_id` an drei Tabellen und der Zähler `projects.planer_next_ids` (S3d), damit Datei-IDs nie wiederverwendet werden.

**Einfacher:** Die Datei-ID **ist** die Datenbank-ID mit Präfix: `m-12`, `t-41`, `k-88`.
- Neue Elemente, die lokal (Helfer, Assistent, Markdown) entstehen, bekommen eine vorläufige ID `k-new-1`, `t-new-2` … Nach `push` bzw. Import ersetzt der Export sie durch die echte ID. Im Git-Diff erscheint das einmal als Umbenennung.
- Eindeutig und nie wiederverwendet: garantiert der Auto-Increment der Datenbank (gelöschte Elemente liegen im Papierkorb und behalten ihre ID; endgültig gelöschte IDs vergibt MariaDB nicht neu, der Zähler ist seit 10.2.4 dauerhaft).
- Es entfallen: Feld `appId` im Format, `file_id` (S3d), `planer_next_ids`, der ID-Dienst, die Regel „nie wiederverwenden“ im Validator (wird zu „Form `x-<Zahl>` oder `x-new-<Zahl>`, eindeutig je Datei“).
- Anker im Assistenten-Entwurf: einfach Elemente mit echter ID. Neu: Elemente mit `-new-`.
- **Folge:** S3d entfällt. Neuer Schritt **T5** passt Schema, Validator, Helfer, `md2json` und `convert` an (`convert` vergibt `-new-`-IDs).
- **Ersetzt** §2.3 (Datei-IDs in der App) und das Feld `appId` aus §1a und der SSoT. Der Vertrag von `export_plan`/`import_plan` liefert die IDs in dieser Form.
- Nachteil (bewusst in Kauf genommen): Ein neues Element ändert beim ersten Import einmal seine ID.

### V2. Ein Schreibweg für Pläne, MCP auf wenige Werkzeuge reduziert (angenommen 2026-11-13)

**Heute:** `ProjectPlanImporter` (339 Zeilen) und `ProjectPlanReader` schreiben über 15 GraphQL-Operationen als Strings (R3). Dazu kommen fünf Plan-Werkzeuge im MCP (`create_project_plan`, `add_to_plan`, `update_plan_item`, `move_milestone`, `set_task_status`), `PlanRules`, die MCP-Teile von `PlanSchema`, `ReadsProjectPlanInput`, `PlainTextDocument`, `plan.graphql` mit eigenen Typen, `importProjectPlan`.

**Einfacher:**
- **Ein Importer** `App\Planer\Import` für alle Wege: Datei (`full`), Assistent und Connector (`partial`), Vorlage (`full` in ein neues Projekt). Er ruft die Dienste der App direkt auf (`Ordering`, `Deletion`, `ItemStatusSync`, `TaskNotifier`) und prüft jede Änderung mit `Gate`/Policies. Gleiche Rechte wie die Oberfläche, ohne den Umweg über GraphQL-Strings, in einer Transaktion.
- **`partial` = ergänzen und ändern, nie löschen:** Ein Element mit echter ID wird mit den angegebenen Feldern aktualisiert (fehlende Felder bleiben), eines mit `-new-` angelegt. Damit deckt `import_plan` alles ab, was die fünf Plan-Werkzeuge heute tun. Ob der **Assistent** Anker ändern darf, bleibt eine Regel des Assistenten (§6 Frage 2), nicht des Importers.
- **Trockenlauf:** `import_plan(…, dry_run: true)` liefert die Liste der geplanten Änderungen (anlegen, ändern, verschieben, löschen, je mit Pfad) und alle Fehler, ohne zu schreiben. Dieselbe Liste nutzen die Vorschau des Assistenten („neu“/„vorhanden“/„geändert“), die Duplikat-Prüfung, die Fehlermeldung von `push` und eine Import-Vorschau in der App. Ein einziger Abgleich statt vier.
- **MCP danach:** `list_projects`, `export_plan`, `import_plan`, `list_templates`, `create_idea`, `list_ideas`, `get_project_ai_context`, `list_users` (nur für neue Projekte, eingeschränkt nach §6 Frage 3). Ein neues Projekt entsteht über `import_plan` ohne `project_id` (mit `projects.create`), aus einer Vorlage über `template_id`. `create_project` und `set_project_member` entfallen (Mitglieder stehen im Dokument).
- **Es entfallen:** die fünf Plan-Werkzeuge, `create_project`, `set_project_member`, `ProjectPlanImporter`, `ProjectPlanReader`, `PlanRules`, MCP-Teile von `PlanSchema`, `ReadsProjectPlanInput`, `PlainTextDocument`, `plan.graphql`-Typen, `importProjectPlan`, die meisten Dateien in `graphql/operations/` (bleiben nur, wenn noch etwas sie nutzt; sonst samt `GraphQLRunner::runNamed` entfernen).
- **Breaking Change am Connector:** laut Rahmen erlaubt. `docs/claude-connector.md` neu schreiben.

### V3. Vorlagen und Demo-Daten als Dateien (angenommen 2026-11-13)

- **Mitgelieferte Vorlagen** als Dateien `spec/templates/software-development.planer.json`, `event.planer.json`, `podcast.planer.json` (mit `names` in `de` und `en`). Ein Befehl synchronisiert sie in die Tabelle `templates` (wie heute `BuiltinLabelSets::sync`). Es entfallen `BuiltinLabelSets.php` und die Label-Übersetzungen in `lang/`.
- **Vorlage-Funktionen** werden zu Import/Export: Projekt aus Vorlage = `import_plan` (`full`) in ein neues Projekt; „Als Vorlage speichern“ = Export plus `template` (Entfernen der App-Bezüge, eine Funktion in PHP und `tools/planer/core`); Duplizieren = Kopie des JSON; Upload/Download = Datei. Der Editor in `/templates` bearbeitet nur Name, Beschreibung und Labels. Das Gerüst wird nicht in einem eigenen Editor bearbeitet, sondern über „Projekt aus Vorlage anlegen, ändern, als Vorlage speichern“ oder über die Datei.
- **Demo-Daten** als Dateien `database/seeders/demo/*.planer.json` (Website-Relaunch, Sommerfest 2027). `DemoSeeder` legt Personen an und importiert die Dateien. Das ist zugleich ein Roundtrip-Test (`export(import(datei)) = datei`, bis auf IDs).

### V4. Eine Baum-Ansicht für Plan-Dokumente im Frontend (angenommen 2026-11-13)

Ein Baustein `PlanerTree` zeigt ein `planer/2`-Dokument (Milestones, ToDos, Tasks, Labels, User-Story, Status) mit optionalen Markierungen aus dem Trockenlauf (neu, geändert, verschoben, gelöscht). Genutzt für: Vorschau des Assistenten (ersetzt `PlanPreview.vue`), Ansicht einer Vorlage, Entwurf an einer Idee, Vorschau beim Datei-Import. Typen aus `spec/planer-2.schema.json`.

### V5. Ideen als Entwurf (angenommen 2026-11-13)

Eine Idee hat Titel, Beschreibung und optional einen `planer/2`-Ausschnitt (`draft`). Einplanen = `import_plan` (`partial`) mit diesem Ausschnitt. Kein eigener Weg vom Entwurf ins Projekt.

### V6. Markdown statt TipTap-JSON als Speicherform für Beschreibungen (abgelehnt 2026-11-13, siehe §9)

**Heute:** Die Datenbank speichert TipTap-JSON. Die Datei speichert Markdown. Also braucht es Markdown ↔ TipTap-JSON in PHP (Import, Export, Assistent) **und** in JavaScript (CLI, Vorschau), mit derselben Whitelist an drei Stellen (`RichTextDocument.php`, `utils/rich-text.ts`, `tools/planer/core`).

**Einfacher:** Die App speichert Beschreibungen und Kommentare als Markdown (nach der Whitelist). TipTap lädt und speichert Markdown (offizielle Markdown-Erweiterung von TipTap 3).
- Import und Export übernehmen den Text ohne Umwandlung. Der Assistent liefert direkt Markdown.
- PHP prüft nur noch Markdown gegen die Whitelist (mit `league/commonmark`, das Laravel ohnehin mitbringt), keine Umwandlung.
- Sicherheit bleibt: Anzeige weiterhin über TipTap, nie `v-html`, nie HTML gespeichert.
- **Risiko:** Treue des Roundtrips TipTap ↔ Markdown (z. B. harte Zeilenumbrüche, verschachtelte Listen). Muss mit Tests über alle erlaubten Konstrukte belegt werden, bevor umgestellt wird.
- **Empfehlung:** als kurzer Spike vor S3 prüfen (ein Test mit allen Whitelist-Konstrukten durch den TipTap-Editor). Gelingt er, umstellen und sich den PHP-Konverter sparen. Sonst bei TipTap-JSON bleiben.

### V7. Kleinere

- **`list_users` im MCP:** nur noch für neue Projekte nötig. Mit §6 Frage 3 eingeschränkt.
- **`Project.planerRevision` im Frontend:** kann das Neuladen nach Änderungen steuern (nur neu laden, wenn sich die Revision geändert hat), statt nach jeder Mutation alle aktiven Abfragen. Ersetzt C1 (Payloads) ganz oder teilweise. Zu prüfen, wenn S3c steht.
- **Spur-Begriffe:** In `docs/umsetzung-planer-v2.md` die Spuren auflösen, sobald V1 bis V3 entschieden sind (ein neues kurzes Umsetzungsdokument statt Hinweisen in drei alten).

### Folgen für die laufende Umsetzung

- **V1 betrifft T4 und S3d.** Wenn V1 angenommen wird: S3d entfällt, T4 nutzt das neue ID-Schema. Der Agent sollte vor T4 auf die Entscheidung warten.
- **V2 betrifft nur spätere Schritte** (Export/Import, Assistent). S2 bis S6 sind unabhängig.
- **V3 betrifft S7.** **V6 betrifft S2, S3 und alle Beschreibungen** und sollte vor S3 entschieden sein.

---

## 4. Ort der Werkzeuge (entschieden 2026-11-13: V2)

### 4.1 Auftrag (Oliver, 2026-11-13)

Neu berechnen unter der Annahme, dass `mollo-version` eine Abhängigkeit der App ist, mit zwei Varianten:

- **V1:** `mollo-version` als offizielles Composer-Paket, die App hängt davon ab.
- **V2:** Alle Funktionen von `mollo-version` in die App integrieren, damit `planer/2` ohne Hin und Her zwischen zwei Repositories entwickelt werden kann. Wenn alles läuft, die unabhängigen Werkzeuge als **`mollo-planer-tools`** auslagern.

### 4.2 Was die App von den Werkzeugen braucht

| Funktion | Wer braucht sie | Sprache heute |
|---|---|---|
| JSON-Schema `planer/2` | App (Import, Vorlagen, Assistent), CLI, Frontend (Typen) | JSON |
| Semantik-Prüfung (IDs, Verweise, Release-Regeln, Vorlagen-Regeln) | App, CLI | JavaScript |
| Kanonisches JSON | App (Export), CLI | JavaScript |
| Markdown ↔ Rich Text (Beschreibungen, Whitelist) | App (Import, Export, Assistent), CLI | JavaScript (Markdown), PHP (Rich Text) |
| Markdown-Ansicht (`render`, `md2json`) | CLI, später App (Download als `.md`) | JavaScript |
| Helfer, `pull`/`push`/`sync`, `template`, `convert` | CLI, Agenten, GitHub Action | JavaScript |
| v1 (`version-*.md`), Tabelle `app_versions`, Vue-Versionsseite | andere Apps mit Versionsseite | JavaScript, PHP, Vue |

Hostpoint hat kein Node. Was die App auf dem Server braucht, muss es in PHP geben.

### 4.3 Vergleich

| | V1: Paket als Abhängigkeit | V2: erst integrieren, dann auslagern |
|---|---|---|
| Entwicklung von `planer/2` | über zwei Repositories, jede Änderung am Format braucht einen Tag und `composer update` | in einem Repository, ein Commit ändert Format, App und Tests zusammen |
| Abstimmung zwischen Agenten | zwei Agenten, Vertrag über Dokumente (wie bisher) | ein Agent bzw. eine Spur |
| Schema als einzige Quelle | im Paket, über `vendor/` | `spec/planer-2.schema.json` im Repository der App |
| PHP-Prüfung | im Paket (neu zu bauen) | in der App (neu zu bauen) |
| Frontend nutzt Render und Prüfung | nur über ein zusätzliches npm-Paket | direkt als gemeinsames JS-Modul (z. B. Markdown-Ansicht herunterladen, Vorschau) |
| Werkzeuge für andere Projekte | sofort als Paket nutzbar | zunächst aus dem Repository der App (Pfad oder `npx` auf das Repository), später als `mollo-planer-tools` |
| Aufwand jetzt | Paket veröffentlichen, Token für privates Repository lokal und beim Deployment | einmal umziehen |

**Empfehlung: V2.** Das Format ist noch in Bewegung (A-S1, A-S4, Vorlagen, Labels). Solange es sich ändert, kostet jede Änderung in V1 einen Umweg über Tag, Paket-Update und zwei Agenten. Nach dem Umzug bleibt die Auslagerung einfach, wenn die Ordner von Anfang an getrennt sind.

### 4.4 V2 im Detail

**Aufbau in `mollo-planer`:**

```
spec/                         # Format planer/2: format-v2.md, planer-2.schema.json, Fixtures mit erwarteten Fehlern
tools/planer/                 # Node, keine Abhängigkeiten (wie mollo-version)
  core/                       # gemeinsames ES-Modul: validate, canonical, render, md2json, Markdown-Whitelist
  cli/                        # bin, Befehle (validate, json, render, convert, helpers, pull/push/sync, template)
  test/
  skills/planer/SKILL.md
  templates/github-action/
backend/app/Planer/           # PHP: Schema-Prüfung (opis/json-schema), Semantik, kanonisches JSON,
                              # Markdown ↔ RichText, Import (full/partial), Export, Vorlagen
frontend/                     # nutzt tools/planer/core (Vorschau, Download .md) und Typen aus spec/
```

- **Eine Quelle für das Schema:** `spec/planer-2.schema.json`. PHP, Node und Frontend lesen dieselbe Datei.
- **Gegen Auseinanderlaufen von PHP und JavaScript:** Die Fixtures in `spec/fixtures/` bekommen je eine Datei mit den erwarteten Fehlern (Pfad und Code). Die Node-Tests und die PHP-Tests prüfen gegen dieselben Fixtures. Dazu ein Test: kanonisches JSON aus PHP und aus Node ist für alle gültigen Fixtures byte-gleich.
- **Kein Node auf dem Server:** `deploy/deploy.sh` nimmt `tools/` und `spec/fixtures/` nicht mit. `spec/planer-2.schema.json` muss mit (die App liest es).
- **Frontend-Build:** importiert `tools/planer/core` über einen Pfad-Alias (Vite), keine Veröffentlichung nötig.
- **Verwendung in anderen Projekten bis zur Auslagerung:** `node ~/www/mollo-planer/tools/planer/cli/bin/planer.mjs …` oder ein Alias. Die GitHub-Action-Vorlage holt das Werkzeug über `actions/checkout` des `mollo-planer`-Repositorys.
- **Name des Befehls:** `mollo-planer` (statt `mollo-version`). Das Format bleibt `planer/2`.

**Umzug:**

- Aus `mollo-version` übernommen werden: `spec/` (v1 und v2), `src/`, `bin/`, `test/`, `fixtures/`, `skills/`, `scripts/messung-helfer.mjs`. Nicht übernommen: `demo/`, `frontend/`, `templates/vue-webawesome/`, `Backend/` (v1-Versionsseite und `app_versions`, siehe 4.6 Nr. 2).
- Übernahme als Kopie in einem Commit, der den Quell-Commit von `mollo-version` nennt (`c169118`). Die Geschichte bleibt im Repository `mollo-version`.
- Danach in `mollo-version`: README-Hinweis „planer/2 wird in mollo-planer weiterentwickelt“, kein weiterer Commit für v2.

**Was mit den bisherigen Arbeitspaketen geschieht:**

- A-S1 (Revision, `changed`), A-S2 (`pull`/`push`/`sync`), A-S3 (GitHub Action), A-S4 (Labels, Vorlagen, Legende) gelten unverändert, werden aber **in `mollo-planer/tools/planer`** umgesetzt, nach dem Umzug.
- Der Vertrag der MCP-Werkzeuge (1a) bleibt. Er ist jetzt intern, kann aber durch gemeinsame Tests (Node-Client gegen den echten MCP-Server im Test) statt eines nachgebildeten Servers geprüft werden.
- R2 Rest (Wertobjekte, `PlanSchema` gegen das JSON-Schema) wird Teil von `backend/app/Planer`.

### 4.5 Auslagerung später: `mollo-planer-tools`

Wenn `planer/2` stabil ist (Import, Export, Vorlagen, Assistent laufen, Roundtrip-Tests grün):

- `spec/` und `tools/planer/` in ein eigenes Repository `mollo-planer-tools` (npm-Paket mit CLI `mollo-planer`, ohne Abhängigkeiten).
- Optional die PHP-Prüfung aus `backend/app/Planer` (ohne App-Teile wie Import und Export) als Composer-Paket im selben Repository.
- `mollo-planer` hängt dann von beiden ab (V1-Weg, aber mit stabilem Format).
- Kriterium für den Zeitpunkt: drei Wochen ohne Änderung an `spec/`, oder ein zweites Projekt nutzt die Werkzeuge regelmäßig.

### 4.6 Fragen

1. **V2 (integrieren, später auslagern)** statt V1? Vorschlag: ja.
2. **v1 und `app_versions`:** Die Versionsseite (v1-Format, Laravel-Paket, Vue-Vorlage) wird von anderen Apps genutzt (z. B. FCAarau DB?). Vorschlag: bleibt in `mollo-version` (eingefroren auf 1.x). Nur `convert v1-zu-v2` und der v1-Parser ziehen mit um. Oder soll die Versionsseite auch in die App bzw. später in die Tools?
3. **Ordner und Namen:** `spec/`, `tools/planer/`, `backend/app/Planer/`, Befehl `mollo-planer`? Vorschlag: ja.
4. **Zeitpunkt:** Umzug sofort, vor A-S1 bis A-S4. Der Agent in `mollo-version` hält dafür an. Vorschlag: ja.

### 4.7 Entscheidungen (Oliver, 2026-11-13)

1. **V2:** Werkzeuge und Format ziehen nach `mollo-planer`. Auslagerung als `mollo-planer-tools`, wenn `planer/2` stabil ist (4.5).
2. **Keine Altlasten, keine Abhängigkeiten:** Niemand nutzt die v1-Versionsseite, `app_versions` oder die Vue-Vorlage. Sie werden **nicht** übernommen. `mollo-version` wird nach dem Umzug archiviert (README-Hinweis, keine weiteren Commits). Übernommen werden nur der v1-Parser und `convert v1-zu-v2`, damit vorhandene `version-*.md` (z. B. `version-club-db.md` im Club-DB-Repository) einmalig umgewandelt werden können.
3. **Ordner und Namen** wie in 4.4: `spec/`, `tools/planer/` (mit `core/` und `cli/`), `backend/app/Planer/`, Befehl `mollo-planer`.
4. **Umzug sofort.** Kein Agent arbeitet gerade im Code. Es gibt nur noch **eine Spur** in `mollo-planer`. Die Arbeitspakete A-S1 bis A-S4 heißen dort T1 bis T4 (`docs/spur-b-sichere-schritte.md`).

---

## 10. Webhook-Details und Neuladen (entschieden 2026-11-13)

**Webhook (W1 in `docs/spur-b-sichere-schritte.md`):**
- Einrichten mit Recht `project.update`.
- Geheimnis verschlüsselt in der Datenbank, nach dem Speichern nie wieder angezeigt, nur ersetzbar. Signatur HMAC-SHA256.
- Bei Fehlern drei weitere Versuche mit wachsendem Abstand, danach Hinweis in den Projekteinstellungen mit Datum, keine Mail.
- Umsetzung nach T3.

**Neuladen über `planerRevision` (S3f), ersetzt C1:**
- Mutationen liefern die neue `planerRevision`. +1 = eigene Änderung, nur Projektbaum und Fortschritt neu laden. Mehr als +1 = jemand anderes hat geändert, alles neu laden und Hinweis zeigen.
- Zeitachse und Kanban fragen alle 30 Sekunden nur `planerRevision` ab (nicht bei verborgenem Tab). Keine WebSockets nötig (Hostpoint).
- C1 (Payloads mit Elternteilen) entfällt.

---

## 11. Name des Formats (entschieden 2026-11-13)

- Der technische Name `planer/2` wird zu **`planer-v2`**: `"format": "planer-v2"`, Schema `spec/planer-v2.schema.json`, `$id` `urn:mollo-planer:planer-v2`.
- Im Fließtext und in der Oberfläche: „Planer v2“.
- Gründe: `/` sieht aus wie ein Pfad oder MIME-Typ, geht nicht in Dateinamen (das Schema hieß deshalb uneinheitlich `planer-2.schema.json`) und muss in Shell und URN/URL teils maskiert werden. `planer-v2` passt zu `format-v2.md` und braucht keine Sonderzeichen.
- Die Dateiendung `<projekt>.planer.json` bleibt.
- Umsetzung: Schritt T0 in `docs/spur-b-sichere-schritte.md`. Ältere Erwähnungen von `planer/2` in den Dokumenten ersetzt T0 mit.
