# Abgleich: Planer-Dateiformat und mollo-version

> **Hinweis (2026-11-13, D1):** Historisch: Analyse vor dem Umzug der Werkzeuge nach `tools/planer` (`planer-abgleich.md` §4).

Stand: 2026-11-12. Status: Analyse und Plan, Entscheidungen offen (Abschnitt 7).
Rolle: Analyse und Plan. Die Umsetzung erfolgt in einer separaten Sitzung.
Quellen: `~/www/mollo-version` (Version 0.2.0, Spezifikation `spec/format.md`), `~/www/mollo-planer` (`backend/graphql/types.graphql`, `enums.graphql`, `plan.graphql`, `docs/connector-erweiterung-plan.md`).
Ergänzt `docs/planer-datei-ssot.md`. Dort ist Abschnitt 4 („Ort: eigenes Repository“) durch diesen Plan überholt.

> **Hinweis (2026-11-13):** Format und Werkzeuge ziehen von `mollo-version` nach `mollo-planer` (`spec/`, `tools/planer/`, Befehl `mollo-planer`). Es gibt nur noch eine Spur. Maßgeblich sind `docs/planer-abgleich.md` und `docs/umsetzung.md`. Angaben zu „Spur A“ und zum Repository `mollo-version` in diesem Dokument gelten sinngemäß für `tools/planer/`.

---

## 1. Befund

**mollo-version existiert bereits.** Es ist ein Format und Werkzeug für Planung und Versionshistorie in Markdown:

- Eine Datei pro Projekt: `docs/version-<name>.md`.
- Milestone = Version `## vM.m Titel`, ToDo = `### Titel`, Task = `- [x] [art] Titel` mit Commit-Zeile.
- Validator mit Zeilennummern und 13 Regeln, keine Abhängigkeiten. `validate` auf `fixtures/valid.md` ergibt keine Fehler, `invalid.md` ergibt 13 Fehler.
- `json` gibt die gelesene Struktur aus.
- Laravel-Paket `mollo-version/laravel`: Importer schreibt in die Tabelle `app_versions` und lässt Freigaben und Papierkorb unberührt.
- Vue-Vorlage `templates/vue-webawesome/` für eine Versionsseite.

**Es wird schon in einem anderen Projekt genutzt.** `docs/connector-erweiterung-plan.md` beschreibt die Pflege von FCAarau DB (Projekt-ID 2) aus `docs/version-club-db.md`. Dort stehen die Versionen als Milestones, die Abschnitte als ToDos und die Punkte als Aufgaben. Der Connector soll also genau dieses Format einlesen. Das ist der Weg, den das neue Planerformat ebenfalls gehen soll.

**Die Spezifikation sieht Änderungen vor.** Abschnitt 5: Ein neues Format bekommt eine neue Hauptversion (Version 2). Bestehende Dateien bleiben lesbar.

---

## 2. Abgleich der Modelle

| Thema | mollo-version v1 | Mollo-Planer-Modell (App) | Folge |
|---|---|---|---|
| Hierarchie | Milestone → ToDo → Task | Milestone → Todo → Task | passt |
| Titel-Grenze | 160 Zeichen | 160 Zeichen (`PlanRules`, `plan.graphql`) | passt |
| Reihenfolge | Versionsnummer `vM.m` | `position` ab 0 je Milestone, Task `boardPosition` | Versionsnummer ist ein eigenes Feld, nicht die Reihenfolge |
| Milestone-Status | `geplant` / `veröffentlicht`, explizit geschrieben | `ItemStatus` `OPEN` / `DONE`, aus Tasks berechnet | **Konflikt**, siehe 3a |
| Task-Status | binär: `[x]` / `[ ]` | `TaskStatus`: `open`, `in_progress`, `review`, `done` | **Konflikt**, siehe 3b |
| Art des Tasks | `feature`, `bug`, `planning`, `research`, `design`, `blocked` | nicht vorhanden, Labels aus einem Label-Set | fehlt auf einer Seite |
| Commit je Task | Pflicht bei `[x]` (`  Commit <Hash>`) | nicht vorhanden | fehlt in der App |
| Fälligkeit | nicht vorhanden | `dueDate` an Milestone, ToDo, Task | fehlt in mollo-version |
| Zuweisung | nicht vorhanden | `assigneeEmail` am Task | fehlt in mollo-version |
| Labels | nicht vorhanden | `labelNames` (geplant in A4), `labels` am Task | fehlt in mollo-version |
| Icon | nicht vorhanden | `icon` an Milestone, ToDo, Task | fehlt in mollo-version |
| Mitglieder, Sprache | nicht vorhanden | `members` mit Rolle, `locale` in `ProjectPlan` | fehlt in mollo-version |
| Beschreibung | Klartext, eingerückte Zeilen | RichText (Milestone, ToDo, Task), Projekt Klartext bis C2 | Umwandlung beim Import |
| Leere Container | ToDo und Milestone brauchen Inhalt (Regeln 3) | App-Regel offen zu prüfen | abweichend, zu klären |
| Doppelte Titel | im ToDo verboten (Regel 5) | nicht geprüft | Regel nur in mollo-version |

---

## 3. Konflikte, die eine Entscheidung brauchen

**a) Zwei Status-Begriffe für Milestones.** mollo-version führt `Status: geplant` oder `veröffentlicht` als Freigabe. Die App berechnet `DONE`, sobald alle ToDos erledigt sind (`ItemStatusSync`). Beides lässt sich nicht im selben Feld führen. Vorschlag: Das App-Feld bleibt berechnet. Die Freigabe wird als eigenes Feld `release.status` geführt und nie mit dem Planstatus gleichgesetzt. Das passt zu den Freigabe-Regeln in mollo-version (Regel 8).

**b) Task-Status.** `[x]` und `[ ]` reichen nicht für `in_progress` und `review`. Vorschlag: Der Status steht als eigenes Feld am Task (`status: open | in_progress | review | done`). Die Markdown-Schreibweise `[x]` / `[ ]` wird daraus abgeleitet (`done` ↔ `[x]`, sonst `[ ]`). `in_progress` und `review` brauchen dann eine eigene Markierung, etwa `[~]` und `[?]`. Das ist eine Entscheidung.

**c) Commit-Pflicht.** Bei mollo-version ist der Commit Pflicht für erledigte Tasks. Das gilt für die Versionshistorie. Für reine Planung wäre der Commit störend. Vorschlag: Commit bleibt im Feld `release`, nur für Versionen mit Status `veröffentlicht` Pflicht. Die Regel 6 wird dafür eingeschränkt.

**d) Art vs. Label.** Die Art (`feature`, `bug`, …) ist fachlich ein Label. Vorschlag: Art wird als Label aus einem festen Label-Set abgebildet. Die Liste ist dann Teil des Schemas und nicht fest im Validator.

**e) Versionsnummer.** Die Nummer `vM.m` ist im Modell der App nicht vorhanden. Vorschlag: `release.version` als Feld, nicht im Titel. Die Reihenfolge bleibt `position`. Die Prüfung „größer als die vorherige“ (Regel 1) gilt dann für `release.version` innerhalb der Reihenfolge.

---

## 4. Konsequenz für das Endziel

Das Ziel lautet: Das Dateiformat bildet das Schema exakt ab, damit MD, App und MCP dieselbe Struktur bearbeiten, und ein Validator garantiert ein kompatibles Dokument.

**Eine Datei kann nicht gleichzeitig das mollo-version-v1-Format und das App-Schema exakt abbilden.** Die Modelle sind verschieden (Abschnitt 2). Ein Format, das beides exakt kann, ist die Vereinigung beider Modelle: ein Kern und eine Erweiterung.

- **Kern = App-Schema, 1:1.** Felder und Typen wie in `types.graphql` und `plan.graphql`: `name`, `description`, `locale`, `members`, `milestones` (mit `position` als Reihenfolge), `todos`, `tasks` (mit `status`, `dueDate`, `assigneeEmail`, `labelNames`, `icon`). Die Datei enthält keine berechneten Felder (Status von Milestone und ToDo, Fortschritt).
- **Erweiterung `release` = mollo-version.** Nur für Versionen: `version`, `status` (`geplant` / `veröffentlicht`), `commit` je Task, optional `art` als Label. Die App ignoriert die Erweiterung beim Import oder speichert sie in der Versionstabelle (`app_versions`).
- **Ein Validator, zwei Prüfungen.** Der Kern wird gegen das JSON Schema der App-Version geprüft. Die Erweiterung gegen die Regeln aus `spec/format.md`.

**Drei Wege gleichzeitig (MD, App, MCP).** Das ist nur sicher, wenn es einen Live-Speicher gibt. Zwei gleichzeitig bearbeitete Kopien führen zu Konflikten. Vorschlag:

- Die App-Datenbank bleibt der Live-Speicher. MCP und App schreiben dorthin, wie heute.
- Die Datei ist ein versionierter Export (im Repository, per Git). Import und Export laufen über das Werkzeug.
- MD ist eine Ansicht der Datei. Bearbeiten im MD ist nur über die Grammatik möglich, mit IDs, damit Änderungen zugeordnet werden.
- Der Validator garantiert: Die Datei ist gültig für die Formatversion und lässt sich importieren. Er garantiert nicht, dass der App-Stand der Datei entspricht. Das prüft ein Roundtrip-Test: `export(import(datei))` ergibt dieselbe kanonische Datei, und `import(export(db))` ändert die Datenbank nicht.
- Für Konflikte: Feld `revision` pro Datei und stabile IDs. Ein Import mit veralteter `revision` wird abgelehnt.

---

## 5. Empfehlung

**Kein neues Werkzeug `planer-tools`.** Der Validator, die CLI, die Tests, der Laravel-Importer und die Vue-Vorlage existieren bei mollo-version bereits. Ein zweites Werkzeug würde Validator, Parser und Renderer doppelt bauen.

**Stattdessen mollo-version auf Format Version 2 erweitern**, wie es Abschnitt 5 der Spezifikation vorsieht:

1. Format v2 als JSON (kanonisch) mit dem Kern aus Abschnitt 4. Die Markdown-Ansicht wird daraus erzeugt, nicht umgekehrt.
2. Das Format v1 bleibt lesbar. `convert` führt v1-MD nach v2-JSON über, mit Roundtrip-Test.
3. Die Erweiterung `release` bildet die Versionslogik ab, die es heute schon gibt.
4. Der Connector nutzt das v2-JSON. Der bestehende Plan in `connector-erweiterung-plan.md` („Aufgaben lassen sich beim Anlegen als erledigt anlegen“, IDs, Reihenfolge) passt zu diesem Format.
5. Der Laravel-Importer und die Freigabe-Regeln bleiben auf der Versionstabelle. Die Planung in der App bekommt einen eigenen Pfad über `importPlanIntoProject` (A4). Beide Pfade lesen dieselbe Datei.

**Namensgebung:** Die bestehende Konvention `docs/version-<name>.md` bleibt erhalten. Der Wunsch nach `<projekt>.planer.json` ist mit `docs/version-<name>.json` abgedeckt, falls das Format v2 JSON ist. Das ist eine Entscheidung.

---

## 6. Risiken

- **Doppelpflege**, wenn MD, App und MCP parallel schreiben und kein Live-Speicher festgelegt ist (Abschnitt 4).
- **Zwei Modelle für denselben Plan:** `app_versions` (Versionsverlauf) und die Plan-Tabellen der App. Ohne klare Zuordnung entstehen zwei Wahrheiten für dieselben Milestones. Vorschlag: eine Datei, zwei Importer, die jeweils nur ihre Felder schreiben.
- **Spezifikation und Validator können auseinanderlaufen.** Die Spezifikation sagt, dass eine Regel ohne Prüfung ein Fehler im Werkzeug ist. Das gilt auch für v2.
- **Die App-Regel für leere Container ist nicht geprüft.** Die Regeln 3 (mindestens ein ToDo, mindestens ein Task) müssen gegen das Verhalten der App abgeglichen werden, bevor sie gelten.

---

## 7. Offene Entscheidungen

1. **Basis:** mollo-version auf Format v2 erweitern (Empfehlung) oder eigenes Werkzeug?
2. **Live-Speicher:** App-Datenbank (Empfehlung) oder Datei?
3. **Status:** Planstatus berechnet und Freigabe getrennt als `release.status` (Empfehlung)?
4. **Task-Status:** die vier Werte der App übernehmen (Empfehlung), mit neuer Markierung für `in_progress` und `review`?
5. **Art:** als Label aus einem festen Set (Empfehlung) oder als eigenes Feld?
6. **Commit-Pflicht:** nur für veröffentlichte Versionen (Empfehlung)?
7. **Versionsnummer:** eigenes Feld `release.version` (Empfehlung)?
8. **Dateiname:** `docs/version-<name>.json` oder `docs/<projekt>.planer.json`?
