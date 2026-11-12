# Planer-Datei `<projekt>.planer.json`: Format v2, Werkzeug, Plan

Stand: 2026-11-12. Status: Entscheidungen getroffen (Abschnitt 2). Umsetzung in einer separaten Sitzung.
Rolle: Analyse und Plan. Es wird hier kein Code geändert.
Abgleich mit dem Bestand: `docs/planer-vs-mollo-version.md`.
Ersetzt den früheren Entwurf. Das eigene Werkzeug `planer-tools` entfällt, das Werkzeug ist `mollo-version`.

---

## 1. Ziel

- Eine Datei `<projekt>.planer.json` im Ordner `docs/` des Projekts ist die Quelle der Planung: Milestones, ToDos und Tasks.
- Das Format bildet das Schema der App exakt ab, damit Markdown, App-Oberfläche und MCP-Connector dieselbe Struktur bearbeiten können.
- Ein Validator garantiert, dass jede Datei gültig für ihre Formatversion ist und sich importieren lässt.
- Agenten ändern die Datei über Helfer-Befehle und nicht durch vollständiges Neuschreiben. Das spart Tokens.
- Die Werkzeuge sind projektübergreifend nutzbar, wie `mollo-version` heute schon.

---

## 2. Entscheidungen (2026-11-12)

| # | Thema | Entscheidung |
|---|---|---|
| 1 | Basis | Erweiterung von `mollo-version` auf Format v2. Kein eigenes Werkzeug. |
| 2 | Live-Speicher | Die App-Datenbank. Die Datei ist ein versionierter Export, den der Validator prüft. |
| 3 | Status | Milestone- und ToDo-Status werden berechnet, wie in der App. Die Freigabe steht getrennt im Feld `release.status`. |
| 4 | Task-Status | Die vier Werte der App: `open`, `in_progress`, `review`, `done`. Markierungen in Markdown: `[ ]` für `open`, `[~]` für `in_progress`, `[?]` für `review`, `[x]` für `done`. |
| 5 | Art | Kein eigenes Feld. Die Art (`feature`, `bug`, `planning`, `research`, `design`, `blocked`) ist ein Label aus einem festen Label-Set. |
| 6 | Commit | Pflicht nur für Tasks mit `status: done` in einer Version mit `release.status: veröffentlicht`. Sonst optional. |
| 7 | Versionsnummer | Eigenes Feld `release.version` (`M.m`). Die Reihenfolge bleibt die Array-Reihenfolge. |
| 8 | Dateiname | `<projekt>.planer.json` im Ordner `docs/`. |
| 9 | User-Story | Task-Feld `userStory` als Objekt aus `role`, `goal`, `benefit` (Abschnitt 4.4a). |
| 10 | Neues App-Feld | Ja. Das Feld kommt in die App und wird im Schema-Review als Punkt A6 geführt. |
| 11 | Formatierung | Die Whitelist der App (`RichTextDocument.php`) gilt auch für das Dateiformat (Abschnitt 4.4b). |

---

## 3. Werkzeug

Das Werkzeug ist `mollo-version` (Node-CLI, keine Abhängigkeiten, Repository `~/www/mollo-version`). Es bekommt das Format v2 und die Helfer. Das Laravel-Paket `mollo-version/laravel` bekommt einen zweiten Importpfad für die Planung.

| Befehl | Zweck | Wer |
|---|---|---|
| `mollo-version validate [datei]` | Prüft v1 (`version-*.md`) und v2 (`*.planer.json`). Fehler mit Pfad oder Zeile. | Mensch, CI, Agent |
| `mollo-version json [datei]` | Gibt die Struktur als JSON aus. Bei v2 ist das die Datei selbst, kanonisch formatiert. | Agent, Importer |
| `mollo-version render [datei] [--out]` | Erzeugt die Markdown-Ansicht für Menschen. | Mensch |
| `mollo-version convert v1-zu-v2 <version-*.md> [--out]` | Führt v1-Dateien nach v2 über. | Mensch, einmalig |
| `mollo-version convert md2json <datei.md>` | Liest die Markdown-Ansicht nach fester Grammatik zurück. Mit IDs in HTML-Kommentaren. | Mensch |
| `mollo-version add milestone\|todo\|task` | Element anlegen, ID vergeben, eine Zeile Ergebnis. | Agent |
| `mollo-version move <id> --to <ziel>` | Element verschieben, ID bleibt. | Agent |
| `mollo-version status <task-id> open\|in_progress\|review\|done` | Task-Status setzen. | Agent |
| `mollo-version list --compact` | IDs und Titel, ohne Beschreibungen. | Agent |
| `mollo-version show <id>` | Ein Element mit Kindern. | Agent |

**Eingabe der Helfer:** Flags für einfache Fälle, JSON über stdin für größere Einträge. **Ausgabe:** kurz, mit der neuen ID. Der Agent liest danach nicht die ganze Datei erneut.

**Tokenbilanz:** Die Einsparung ist nicht belegt. Sie wird in Phase P4 an einer Beispieldatei gemessen.

**Abgrenzung zu den MCP-Werkzeugen:** `add_to_plan`, `set_task_status` und `move_milestone` ändern heute die Datenbank. Die Helfer ändern die Datei. Beides wird über Import und Export abgeglichen. Die Helfer sollen die MCP-Werkzeuge nicht ersetzen.

---

## 4. Dateiformat v2

### 4.1 Beispiel

```json
{
  "format": "planer/2",
  "revision": 3,
  "project": {
    "name": "Mollo Planer",
    "description": "Projektverwaltung mit Milestones, ToDos und Kanban.",
    "locale": "DE",
    "members": [{ "email": "oliver@mollo.ch", "role": "<ProjectRole>" }]
  },
  "milestones": [
    {
      "id": "m-001",
      "appId": null,
      "title": "Schema-Refactoring",
      "dueDate": "2026-12-15",
      "release": { "version": "1.4", "status": "geplant" },
      "todos": [
        {
          "id": "t-001",
          "title": "Ordering-Service ausbauen",
          "tasks": [
            { "id": "k-001", "title": "Sperre für Milestones ergänzen", "status": "done", "labelNames": ["backend"], "commit": null },
            { "id": "k-002", "title": "Lückenschluss nach Löschen", "status": "open", "assigneeEmail": "oliver@mollo.ch", "labelNames": ["backend"] }
          ]
        }
      ]
    }
  ]
}
```

Das `role` ist ein Platzhalter. Die gültigen Werte kommen aus dem Enum `ProjectRole` der App.

### 4.2 Felder, mit Bezug zum App-Schema

| Ebene | Feld | Typ | Quelle im App-Schema |
|---|---|---|---|
| Datei | `format` | `"planer/2"` | neu |
| Datei | `revision` | ganze Zahl ≥ 0 | neu, für Konflikterkennung |
| Projekt | `name` | String, 2 bis 120 | `ProjectPlanInput.name` |
| Projekt | `description` | String, höchstens 2000 | `ProjectPlanInput.description` |
| Projekt | `locale` | `DE` oder `EN` | `ProjectPlanInput.locale` |
| Projekt | `members` | Liste, höchstens 50, `email` und `role` | `ProjectPlanMemberInput` |
| Milestone | `id`, `appId`, `title`, `description`, `dueDate` | siehe Tabelle unten | `ProjectPlanMilestoneInput`, `Milestone` |
| Milestone | `release` | optional: `version`, `status` | Erweiterung aus mollo-version |
| ToDo | `id`, `appId`, `title`, `description`, `dueDate` | | `ProjectPlanTodoInput`, `Todo` |
| Task | `id`, `appId`, `title`, `description`, `dueDate`, `status`, `assigneeEmail`, `labelNames`, `commit` | | `ProjectPlanTaskInput`, `Task`, `TaskStatus`, Erweiterung `commit` |

**Grenzwerte:** Titel von Milestone, ToDo und Task 2 bis 160 Zeichen. Beschreibungen höchstens 5000 Zeichen. Milestones höchstens 30, ToDos je Milestone höchstens 50, Tasks je ToDo höchstens 50. Mindestens ein Milestone beim Import (`milestones min:1`).

### 4.3 Regeln

- **Reihenfolge = Array-Reihenfolge.** Es gibt keine Positionsfelder in der Datei. Das vermeidet die Lückenprobleme aus R1 im Refactoring-Plan.
- **Berechnete Werte stehen nicht in der Datei:** Status von Milestone und ToDo, Fortschritt, Kommentare, Zeitstempel.
- **`id`** ist die Datei-ID (Präfix und Nummer: `m-001`, `t-001`, `k-001`). Der Validator prüft, dass sie eindeutig ist. Sie wird nie wiederverwendet.
- **`appId`** ist die ID in der App. Beim ersten Import ist sie `null`. Der Import setzt sie. Ein erneuter Import mit gesetzter `appId` aktualisiert das Element, statt es doppelt anzulegen. Das ist die Umsetzung von A4 aus dem Schema-Review.
- **`release`** ist nur auf Milestones erlaubt. `version` hat das Format `M.m` (ganze Zahlen, keine Patch-Stufe). `status` ist `geplant` oder `veröffentlicht`. Eine veröffentlichte Version darf keinen Task mit `status` ungleich `done` haben.
- **Commit:** Der Commit ist ein Hash mit 7 bis 40 Hex-Zeichen. Er ist Pflicht für `done`-Tasks in einer veröffentlichten Version. Für `Ohne Commit` (Altbestand) gilt die Ausnahme aus Regel 6 der Spezifikation v1.
- **Labels:** `labelNames` verweist auf das Label-Set des Projekts. Die Art einer Aufgabe ist ein solches Label.
- **Beschreibungen** sind Markdown-Strings. Der Import wandelt sie in das RichText der App um. Der Export wandelt zurück.
- **Kanonische Formatierung:** zwei Leerzeichen, ein Element pro Zeile wo sinnvoll, feste Schlüsselreihenfolge. Damit bleibt der Git-Diff lesbar.

### 4.4 User-Story und Formatierung (Task)

**Status: entschieden am 2026-11-12** (Entscheidungen 9 bis 11). Der Abschnitt ist eine Erweiterung über das heutige App-Schema hinaus. Die Umsetzung erfolgt über den neuen Punkt A6 im Schema-Review.

**a) Eigenes Feld `userStory` am Task.**

Entschieden: ein optionales Objekt mit drei Teilen, die zusammen den Satz „Als … möchte ich …, damit …“ ergeben.

```json
"userStory": {
  "role": "Mitglied",
  "goal": "meine Tasks nach Status filtern",
  "benefit": "ich in Meetings schnell den Stand zeige"
}
```

- Alle drei Felder sind Pflicht, wenn `userStory` gesetzt ist. Ohne Angabe ist das Feld `null`.
- Grenzen (entschieden): `role` höchstens 80 Zeichen, `goal` und `benefit` höchstens 160 Zeichen, jeweils Klartext. Keine Formatierung in diesen Feldern, damit der Satz einheitlich bleibt.
- Anzeige: Die Markdown-Ansicht rendert den Satz. Die App zeigt ihn über der Beschreibung.
- Verworfen: ein einzelner String mit dem vollständigen Satz. Der Validator könnte die drei Teile dann nicht prüfen.
- MD-Schreibweise (entschieden): eine Zeile direkt unter dem Task, eingerückt, `  Story: Als <Rolle> möchte ich <Ziel>, damit <Nutzen>.` Der Konverter zerlegt sie mit einem festen Muster. Passt das Muster nicht, ist es ein Fehler.
- Auswirkung auf den App-Stand: Eine neue Spalte an `tasks` (JSON), ein GraphQL-Feld am Typ `Task` und an `ProjectPlanTaskInput`, eine Ergänzung der MCP- und Assistenten-Schemas. Das wäre ein neuer Punkt A6 im Schema-Review. R2 (`PlanSchema`) übernimmt das Feld dann automatisch.
- Nicht umgesetzt, aber möglich: dieselbe Struktur für ToDos und Milestones.

**b) Formatierung in Beschreibungen.**

Die App hat bereits eine Whitelist für Rich Text (`backend/app/Support/RichTextDocument.php`). Sie erlaubt: Absätze, Überschriften der Ebenen 2 und 3, Aufzählungen, nummerierte Listen, Zitate, harte Zeilenumbrüche, fett, kursiv und Links (nur `http`, `https`, `mailto`). Entschieden: Das Dateiformat nutzt genau diese Whitelist, und zwar in dieser Markdown-Schreibweise:

| Wunsch | Markdown in der Datei | Knoten in der App |
|---|---|---|
| Titel (Überschrift) | `### Text` (Ebene 3), `## Text` (Ebene 2) | `heading` mit `level` 2 oder 3 |
| fett | `**Text**` | Mark `bold` |
| kursiv | `*Text*` | Mark `italic` |
| Liste | `- Punkt` | `bulletList` mit `listItem` |
| nummerierte Liste | `1. Punkt` | `orderedList` mit `listItem` |
| Zitat | `> Text` | `blockquote` |
| Link | `[Text](https://…)` | Mark `link` |

- **Nicht erlaubt**, der Validator lehnt ab: HTML, Bilder, Tabellen, Code, Überschriften der Ebene 1, Ebene 4 und tiefer, Links mit anderem Schema als `http`, `https`, `mailto`.
- **Titel** (Milestone, ToDo, Task) bleiben Klartext, ohne Formatierung. Sie erscheinen in Listen und im Kanban, dort wirkt Formatierung störend.
- **Roundtrip:** Für alle erlaubten Konstrukte gilt `Markdown → RichText → Markdown` ohne Verlust. Das wird als Testfall aufgenommen. Die Grenze liegt bei Ausdrucksformen wie verschachtelten Listen mit gemischten Typen. Sie sind nicht Teil der Whitelist.
- **Validator:** Die Whitelist wird in `mollo-version` als Liste derselben Knotentypen geführt. Ein Test vergleicht sie mit `RichTextDocument.php`, damit beide nicht auseinanderlaufen.

**c) Nicht vorgeschlagen, aber möglich:** Akzeptanzkriterien als Liste am Task. Dann bräuchte es ein weiteres Feld. Sag Bescheid, wenn du das willst.

---

## 5. Validator

Drei Prüfungen, jede mit Zeilen- oder Pfadangabe:

1. **Schema:** JSON Schema für `planer/2`. Es wird aus den Typen der App abgeleitet (`plan.graphql`, `types.graphql`), damit beide nicht auseinanderlaufen. Ein Test vergleicht die Felder.
2. **Semantik:** eindeutige `id` je Datei, Gültigkeit der Verweise (`assigneeEmail` ist Mitglied, `labelNames` ist im Set), Release-Regeln, Grenzwerte, Commit-Regel.
3. **Kanonisch:** Die Datei entspricht der Formatierung des Werkzeugs. Abweichungen sind ein Hinweis, kein Fehler.

Für v1-Dateien (`version-*.md`) gelten die Regeln der Spezifikation v1 unverändert.

**Garantie:** Eine Datei, die der Validator ohne Fehler annimmt, ist gültig für ihre Formatversion und importierbar. Dass der App-Stand zur Datei passt, prüft der Roundtrip-Test (Abschnitt 6).

---

## 6. Bearbeitungswege, Live-Speicher und Konflikte

- **Live-Speicher ist die App-Datenbank.** MCP-Connector und App schreiben dorthin.
- **Die Datei ist ein versionierter Export.** Sie liegt im Repository und wird über Git nachgeführt. Import und Export laufen über `mollo-version` und das Laravel-Paket.
- **MD ist eine Ansicht.** Bearbeiten im MD ist nur über `convert md2json` möglich, mit Grammatik und IDs.
- **Roundtrip-Tests:**
  - `export(import(datei))` ergibt dieselbe kanonische Datei.
  - `import(export(db))` ändert die Datenbank nicht.
- **Konflikte:** Die Datei trägt `revision`. Ein Import mit veralteter `revision` wird abgelehnt und zeigt die Unterschiede. Die Helfer erhöhen `revision` bei jeder Änderung.

---

## 7. Umsetzungsphasen

Jede Phase ist ein eigener Commit mit Tests. Die Phasen P1 bis P5 in `mollo-version`, P6 in `mollo-planer`.

- **P0:** Entscheidungen. Erledigt.
- **P1: Spezifikation v2.** `spec/format-v2.md` und das JSON Schema `spec/planer-2.schema.json`. Feldliste wie in Abschnitt 4.
- **P2: Validator v2.** Schema-, Semantik- und Kanonik-Prüfung. Fixtures (gültig und ungültig), Tests.
- **P3: Ausgabe.** `json` für v2, `render` nach Markdown. Golden-File-Tests.
- **P4: Helfer.** `add`, `move`, `status`, `list`, `show`, `revision`. `SKILL.md` für Agenten. Tokenmessung vorher und nachher an einer Beispieldatei.
- **P5: Konvertierung.** `convert v1-zu-v2` und `convert md2json`. Roundtrip-Tests für beide.
- **P6: Anbindung Mollo Planer.** Laravel-Importer für v2 über `importPlanIntoProject` (A4), Export, Roundtrip-Tests in `mollo-planer`. Abhängig von A4 und R2.

**Hinweis zu R2:** R2 aus `refactoring-vor-umsetzung.md` sieht ein `PlanSchema` in PHP vor. Mit diesem Plan ist das JSON Schema v2 die Vorlage. Die PHP-Seite prüft dagegen. Das ist die Richtung, die R2 jetzt bekommt. Die Angabe in R2 muss entsprechend angepasst werden.

---

## 8. Risiken

- **Doppelte Wahrheit** zwischen Datenbank und Datei. Gegenmaßnahme: Roundtrip-Tests (Abschnitt 6).
- **Zwei Importpfade** für Planung: `importPlanIntoProject` für die App und der Laravel-Importer für `app_versions`. Gegenmaßnahme: eine Datei, zwei Importer, die nur ihre Felder schreiben.
- **Der Dateiname ändert die Suche.** Der Validator sucht bisher nach `docs/version-*.md`. Er muss auch `*.planer.json` finden. Das gehört in P2.
- **Leere Container:** Die App lässt leere ToDos und Tasks zu. mollo-version verlangt sie nicht. Das Format v2 folgt der App. Die Regel aus Spezifikation v1 bleibt nur für v1-Dateien gültig. Diese Abweichung ist bewusst, muss aber in P1 festgeschrieben werden.
- **Tokenersparnis ist nicht belegt.** Gegenmaßnahme: P4 misst es.

---

## 9. Noch festzulegen in P1

Alle Entscheidungen aus Abschnitt 2 sind getroffen. Diese Punkte werden in P1 festgeschrieben:

1. Die Liste der Rollen in `members` wird aus dem Enum `ProjectRole` übernommen.
2. Die Marker `[~]` und `[?]` für `in_progress` und `review` gelten als vorgeschlagen. Sie können in P1 noch geändert werden.
3. Der Ort des JSON Schema für `$schema`. Vorschlag: `spec/planer-2.schema.json` im Repository `mollo-version`.
4. Die Grenzen für `userStory` (Rolle 80, Ziel und Nutzen je 160 Zeichen) werden in P1 festgeschrieben.
