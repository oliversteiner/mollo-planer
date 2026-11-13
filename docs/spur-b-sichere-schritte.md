# Umsetzung planer/2 in mollo-planer: sichere Arbeitsschritte

Stand: 2026-11-13 (U0, T1, T4, S3b, S3c, S3d, S7 ergänzt). Geprüft: `mollo-planer` bis `a98662d`, `mollo-version` 1.0.0 bis `c169118`.
Rolle: Auftrag für die Umsetzungssitzung in `mollo-planer`. Ergänzt `docs/umsetzung-planer-v2.md` (Grundregeln §0, Checkliste §4 und Stopp-Punkte §5 gelten).
Rahmen (Oliver, 2026-11-13): **Breaking Changes sind erlaubt.** Es gibt keine Altlasten, nur Demoprojekt und Testdaten. `migrate:fresh` lokal ist erlaubt.

Diese Schritte hängen an keiner offenen Entscheidung. Alles, was noch offen ist, steht am Ende unter „Nicht in diesem Auftrag“ und wird in der Planung geklärt.

**Seit 2026-11-13 gibt es nur noch eine Spur.** Format und Werkzeuge aus `mollo-version` ziehen nach `mollo-planer` (`docs/planer-abgleich.md` §4). Wo ältere Dokumente „Spur A“ oder `mollo-version` nennen, gilt jetzt `tools/planer/` bzw. `spec/` in diesem Repository.

**Stand:** S1 erledigt (`d368475`, `f03d853`). U0 erledigt (`c2b1577`). T1 erledigt (`072fcbb`). T4 erledigt bis auf `template` und `validate --template`, die auf eine Entscheidung zu `planer-abgleich.md` §3.4 und §3.6 warten. S2 erledigt (Inline-Code in Rich Text). S3: A1 (`d53db03`), A2 (`a99cc89`), A3 Teil 1 Reihenfolge der ToDos erledigt (`aa4b336`, `moveTodo`, Zeitachse). A3 Teil 2 erledigt: `Task.position` heißt `boardPosition` (Spalte `board_position`). Offen in S3: A5-Spalten, A6, Task-Status idea und planned.

---

## U0. Umzug der Werkzeuge aus `mollo-version` (zuerst)

Details: `docs/planer-abgleich.md` §4.4 und §4.7. Quelle: `~/www/mollo-version`, Stand `c169118`.

- **Übernehmen:**
  - `spec/format-v2.md`, `spec/planer-2.schema.json`, `spec/messung-helfer.md` → `spec/`. `spec/format.md` (v1) → `spec/format-v1.md`, nur als Grundlage für `convert`.
  - `src/`, `bin/` → `tools/planer/core/` (Prüfung, kanonisches JSON, `render`, `md2json`, Whitelist, v1-Parser, Konvertierung) und `tools/planer/cli/` (Befehle). Aufteilung: Alles, was Dateien liest oder schreibt oder Argumente auswertet, gehört in `cli/`; reine Funktionen in `core/`.
  - `test/` → `tools/planer/test/`, `fixtures/` → `spec/fixtures/`, `skills/planer/` → `tools/planer/skills/planer/`, `scripts/messung-helfer.mjs` → `tools/planer/scripts/`.
- **Nicht übernehmen:** `Backend/` (Laravel-Paket, `app_versions`), `frontend/`, `demo/`, `templates/vue-webawesome/`, Teile von `src/` und `test/`, die nur der v1-Versionsseite dienen (Auswertung für `app_versions`, Vorlagen-Test der Vue-Seite). Der v1-**Parser** und `convert v1-zu-v2` bleiben.
- Befehl umbenennen: `mollo-planer` (`tools/planer/cli/bin/mollo-planer.mjs`), `package.json` in `tools/planer/` mit `"bin"` und `"test": "node --test test/*.test.mjs"`, keine Abhängigkeiten, Node ≥ 20.
- Texte und Kommentare: `mollo-version` → `mollo-planer` (Werkzeug), Format bleibt `planer/2`.
- `deploy/deploy.sh`: `tools/` und `spec/fixtures/` nicht auf den Server, `spec/planer-2.schema.json` schon (die App liest es).
- CI bzw. `composer test`-Umfeld: `npm test` in `tools/planer/` als eigener Schritt in den Prüfungen vor jedem Commit (in `CLAUDE.md`/`AGENTS.md` ergänzen).
- Commit: `Chore: move planer/2 format and tools from mollo-version (c169118)`. Danach alle Tests grün (`tools/planer`: `npm test`).
- In `~/www/mollo-version`: README oben mit Hinweis „Archiviert. planer/2 wird in mollo-planer weiterentwickelt (spec/, tools/planer/).“ Ein Commit `Docs: archived, moved to mollo-planer`. Sonst nichts ändern.

## T1. Revision nur durch die App, `changed`, Werte klein und englisch (früher A-S1)

`docs/planer-abgleich.md` §1a und §5.1. In `spec/` und `tools/planer/`: `revision` erhöht nur die App, Helfer setzen `"changed": true`. Alle Werte klein und englisch: `locale` `de`/`en`, `release.status` `planned`/`released`, Commit `none`. `render`/`md2json` übersetzen die festen Wörter. `convert` bildet v1 darauf ab. Schema, Spezifikation, Tests und Golden Files nachführen.

## T4. Labels in der Datei, Vorlagen (früher A-S4)

`docs/planer-abgleich.md` §3.3, §3.6, §3.7. In `spec/` und `tools/planer/`: `project.labels` mit mehrsprachigen `names` (§5.2), Task-Feld `labels` (statt `labelNames`), Verweisprüfung, Legende in `render`/`md2json`, `render --lang de|en` und `--legend-translations` (§5.3), `validate --template`, Befehl `template`, `convert` erzeugt Labels der Vorlage „Softwareentwicklung“ in `de` und `en`. Fixtures und `docs/beispiel-planer.md` nachführen.

**T4 Rest (jetzt entschieden durch V3):** `template <datei>` entfernt die App-Bezüge nach `docs/planer-abgleich.md` §3.4 und §3.6 (keine `revision`, `changed`, Mitglieder, Zuweisungen, Fälligkeiten, Commits, `release.status`; Task-Status `planned`; IDs werden `-new-`). `validate --template` prüft genau diese Regeln. Die Dateien in `spec/templates/` müssen `validate --template` bestehen.

**Zusätzlich in T4:** Task-Status `idea` und `planned` (`docs/planer-abgleich.md` §8), Markierungen `[i]` (idea) und `[p]` (planned).

## T5. IDs aus der Datenbank (V1, entschieden 2026-11-13)

`docs/planer-abgleich.md` §7 V1. In `spec/` und `tools/planer/`:
- `id` hat die Form `m-<Zahl>`, `t-<Zahl>`, `k-<Zahl>` (Datenbank-ID) oder `m-new-<Zahl>`, `t-new-<Zahl>`, `k-new-<Zahl>` (lokal neu, noch nicht importiert). Eindeutig je Datei.
- Feld `appId` entfällt in Schema, Spezifikation, Validator, kanonischer Form, `render` (Kommentar nur noch `<!-- id: k-88 -->`) und `md2json`.
- Helfer vergeben `-new-`-Nummern (nächste freie je Präfix in der Datei). `convert v1-zu-v2` vergibt nur `-new-`-IDs.
- Regel „nie wiederverwenden“ im Validator entfällt (die Datenbank sorgt dafür).
- Fixtures, Golden Files, `docs/beispiel-planer.md` nachführen.

**S3d entfällt** (ersetzt durch T5).

T1 und T4 vor S3, damit die App gleich gegen das neue Format gebaut wird. T2 (`pull`/`push`/`sync`) und T3 (GitHub Action) folgen nach dem Export und Import in der App (I1, I2).

## S1. `useDelete` und Liste für archivierte Projekte (B1)

- `useDelete` als Composable im Frontend. Vorerst mit Bestätigung, also gleiches Verhalten wie `useConfirmedDelete`. Alle 5 Verwendungen umstellen, `useConfirmedDelete` danach entfernen.
- Backend: Liste „Mutationen, die in archivierten Projekten verboten sind“ als Konstante, mit Test gegen das Schema (wie `MutationCoverageTest`). Noch ohne Archiv-Logik.
- Ein Commit je Teil.

## S2. Inline-Code in Rich Text

Entschieden in Spur A (`spec/format-v2.md` §10 Nr. 6): Beschreibungen dürfen Inline-Code enthalten (Mark `code`).

- `backend/app/Support/RichTextDocument.php`: Mark `code` in `MARKS`. Kein Code-Block.
- `frontend/app/utils/rich-text.ts`: gleiche Whitelist.
- TipTap: Mark `Code` im Editor aktivieren, Knopf in der Werkzeugleiste (Icon „code“, Duotone Light), Tastenkürzel wie TipTap-Standard. Texte in `de.json` und `en.json`.
- Tests: `RichTextDocumentTest` und Frontend-Test mit `code`, und weiterhin Ablehnung von `codeBlock`.
- Ein Test, der die Whitelist von Backend und Frontend vergleicht, falls noch keiner besteht.

## S3. Datenstruktur (Schema-Review Schritt 1, ohne Release/Commit/Revision)

Eine Migration bzw. Umbau der bestehenden Migrationen, danach `migrate:fresh --seed --seeder=DemoSeeder` lokal.

- **A1** `project_id` an `todos` und `tasks` (Fremdschlüssel, `cascadeOnDelete`, Index `tasks(project_id, status, board_position)`). Trait `DerivesProject` setzt den Wert im Model-Hook aus dem Elternteil, nie aus der Eingabe. `projectPath()` von Todo und Task wird `project`, `scopeInProject` ein einfaches `where`. GraphQL `Todo.project`, `Task.project`.
- **A2** `created_by` an `milestones`, `todos`, `tasks` (nullable, `nullOnDelete`). Trait `RecordsCreator` setzt den Wert aus dem Login. GraphQL `createdBy: User`.
- **A3** `todos.position` je Milestone über `Ordering` (R1), Relation sortiert, `CreateTodoInput.position` optional, Mutation `moveTodo(id, milestoneId, position)`. Umbenennung `Task.position` → `boardPosition` (Spalte `board_position`). Frontend: Zeitachse sortiert nicht mehr selbst (`Timeline.vue`), Kanban nutzt `boardPosition`.
- **A5 (nur Spalten)** `projects.archived_at`; `deleted_at`, `deleted_by`, `deletion_id` an `projects`, `milestones`, `todos`, `tasks`, `comments`. Noch ohne Logik, `SoftDeletes` noch nicht aktivieren.
- **A6** `tasks.user_story` (JSON, nullable) mit `role` (max. 80), `goal`, `benefit` (je max. 160), Klartext, alle drei Pflicht wenn gesetzt. GraphQL-Typ `UserStory`, Feld `Task.userStory`, Eingabe in `createTask` und `updateTask`. Task-Detail zeigt den Satz „Als … möchte ich …, damit …“ über der Beschreibung, Kanban-Karte nicht. Formular im Task-Detail mit drei Feldern.
- **Task-Status `idea` und `planned`** (`docs/planer-abgleich.md` §8): `TaskStatus` in PHP, GraphQL und Frontend, `ItemStatusSync` und Fortschritt ohne `idea`, Kanban mit sechs Spalten und Schalter für `idea`/`planned`, „offene Tasks“ ohne `idea`, Zuweisungs-Mail erst ab `planned`.
- Seeder: Demoprojekte mit Positionen der ToDos, einigen User-Stories, `created_by`.
- `composer schema:frontend`, `npm run codegen`, alle Prüfungen.
- Commits: je Punkt (A1, A2, A3, A5-Spalten, A6) einer.

## S3b. Release, Commit und Revision (Schema-Review A7, entschieden 2026-11-13)

Details in `docs/planer-abgleich.md`, Abschnitt 1. Eigene Migration nach S3.

- `milestones.release_version` (`M.m`) und `milestones.release_status` (`planned`, `released`), beide gesetzt oder beide leer. Oberfläche zeigt „geplant“/„veröffentlicht“ bzw. „planned“/„released“.
- `tasks.commit`: Hash (7 bis 40 Kleinbuchstaben und Ziffern) oder `none` (Oberfläche: „ohne“).
- `projects.planer_revision` (Standard 0). Gezählt wird in S3c.
- GraphQL: `Release`, `ReleaseStatus`, `Milestone.release`, `Task.commit`, `Project.planerRevision`, Eingaben in Milestone- und Task-Mutationen.
- Regeln wie `mollo-version` `spec/format-v2.md` §5.3, mit Tests: veröffentlichte Version nur mit `done`-Tasks, `done`-Task in veröffentlichter Version braucht Commit oder `none`, `none` nur bei `done`.
- Oberfläche: Version und Freigabe am Milestone (Zeitstrahl, Formular), Commit im Task-Detail.
- Seeder: Demoprojekt mit einer veröffentlichten und einer geplanten Version.

## S3c. Zählung der Planer-Revision (entschieden 2026-11-13)

Details in `docs/planer-abgleich.md`, Abschnitt 1a.

- `projects.planer_revision` +1 bei jeder Änderung an Projekt, Milestone, ToDo oder Task: Felder, Status, Reihenfolge, Zuweisung, Labels, Release, Commit, User-Story, Papierkorb und Wiederherstellen (sobald S5 steht). Kommentare zählen nicht.
- Eine Mutation (oder ein Import) ergibt genau +1, auch wenn sie mehrere Zeilen ändert. Vorschlag: Zähler pro Request bzw. Transaktion merken und am Ende einmal erhöhen (z. B. über einen `afterCommit`-Callback je Projekt).
- Lesen über `Project.planerRevision`.
- Tests: jede Art von Änderung erhöht um genau 1, Kommentar nicht, Lesen nicht.

## S3d. Datei-IDs (entfällt, ersetzt durch T5)

Details in `docs/planer-abgleich.md`, Abschnitt 2.3.

- Spalte `file_id` (String, nullable) an `milestones`, `todos`, `tasks`, eindeutig je Projekt.
- `projects.planer_next_ids` (JSON, Zähler je Präfix `m`, `t`, `k`), nie zurücksetzen.
- Service zum Vergeben der nächsten ID je Präfix, mit Sperre auf dem Projekt (wie `Ordering`). Wird erst vom Export benutzt, hier nur mit Tests: eindeutig, steigend, keine Wiederverwendung nach Löschen.
- Kein GraphQL-Feld nötig (nur für Import und Export). Optional `fileId` zum Lesen für die Fehlersuche.

## S3e. Rich-Text-Konverter Markdown ↔ TipTap-JSON (entschieden 2026-11-13)

Details: `docs/planer-abgleich.md` §9. Nur PHP, keine neue Abhängigkeit.

- `App\Planer\RichText\MarkdownToTiptap` auf Basis von `league/commonmark` (AST, kein HTML), `App\Planer\RichText\TiptapToMarkdown` als eigener Serialisierer in kanonischer Form (§9.3).
- Umfang: §9.4 verlustfrei, §9.5 vereinheitlicht, §9.6 als Klartext mit Warnungsliste (Rückgabe `{ doc, warnings }`).
- Ergebnis von `MarkdownToTiptap` immer gültig nach `RichTextRule` (gleiche Whitelist, inkl. Mark `code` aus S2).
- Testfälle `spec/fixtures/richtext/*.md` und `*.json`: beide Richtungen, Roundtrip byte-gleich für §9.4, Warnungen für §9.6.
- `PlainTextDocument` wird erst beim Umbau des Assistenten ersetzt (B5a), hier nicht anfassen.

## T6. Kanonisches Markdown in `tools/planer` (entschieden 2026-11-13)

Details: `docs/planer-abgleich.md` §9.3 und §9.7. Kanonische Markdown-Form in `spec/format-v2.md`. `validate`: Konstrukte aus §9.6 sind Fehler (wie bisher), Schreibweisen aus §9.5 ein Hinweis. `json` schreibt Beschreibungen kanonisch. Testfälle aus `spec/fixtures/richtext/` gegen die Whitelist und die kanonische Form.

## S4. Rechte (Schema-Review Schritt 2)

In `ProjectPermissions` und `SystemPermissions`, mit Tests in `ProjectPermissionsTableTest`, `SystemPermissionsTableTest`, `PolicyTest`:

- `task.create` zusätzlich für Mitglied.
- `task.updateOwn` (Owner, Manager, Mitglied): Ändern eigener Tasks (`created_by` = Person).
- `task.assignSelf` (Owner, Manager, Mitglied): eigenen Task sich selbst zuweisen oder diese Zuweisung aufheben. Gilt auch beim Anlegen (`createTask`).
- `project.archive` (Owner, Manager).
- `trash.purge` (Owner).
- Systemfähigkeiten `assistant.use` (alle außer `lesend`), `ideas.create` (alle außer `lesend`), `ideas.viewAny` (Admin).
- **Wichtig:** `assistant.use` wird nur angelegt. `draftProjectPlan`, `importProjectPlan` und die Seite `/assistant` bleiben vorerst bei `projects.create`. Grund: Der Prompt des Assistenten enthält heute alle Konten mit E-Mail. Die Öffnung kommt mit dem Assistenten-Umbau (B5a), nicht vorher.
- Frontend: Knöpfe nach `viewerAbilities` (Task bearbeiten bei eigenen Tasks, Zuweisen an sich selbst).
- `OutsiderAccessTest` und `MutationTest` wo nötig ergänzen.

## S5. Archiv und Papierkorb (Schema-Review Schritt 3, A5)

Vollständig nach `docs/schema-review.md` A5:

- Archiv: zentral in `ProjectPermissions::allows`, Ausnahmen `project.archive` und Wiederherstellen. `archiveProject`, `unarchiveProject`, Filter `projects(archived:)`. Ausblenden in Dashboard, Seitenleiste, `myTasks`. Test mit der Liste aus S1.
- Papierkorb: `SoftDeletes` aktivieren, `App\Services\Deletion` weich löschen mit `deletion_id` für alle Kinder, `restore()`, `purge()`, `emptyProjectTrash`. `ItemStatusSync` und `Ordering::close` bei Löschen und Wiederherstellen. Keine Frist, kein Scheduler.
- GraphQL `TrashItem`, `trash(projectId)`, `restoreFromTrash`, `purgeFromTrash`, `emptyProjectTrash`. `delete*` geben `deletionId` zurück.
- Frontend: `useDelete` ohne Bestätigung, mit Toast „Rückgängig“ (Milestone, ToDo, Task, Kommentar). Projekt löschen behält die Bestätigung. Seiten `/projects/:id/trash` und `/trash`, Bereich „Archiv“ in der Projektliste.
- MCP: Schreibwerkzeuge lehnen archivierte Projekte ab, `list_projects` mit optionalem Filter. Weiterhin kein Löschen über MCP.
- Ideen sind noch nicht gebaut. `TrashItemType.IDEA` erst mit B6.

## S6. Kleinere Schema-Bereinigungen (Schema-Review C2, C3)

- C2: `Project.description` als RichText (Migration, Input, Frontend-Editor). `ProjectPlanInput.description` bleibt Klartext bis zum Assistenten-Umbau.
- C3: `updateMyNotifications(notifyByEmail:)`, einheitliche Rückgabe von `rejectUser`/`deleteUser`, `MemberCandidate` nach `types.graphql`, Kopfkommentar in `schema.graphql` auf Verweis zu `permissionMatrix` kürzen.

## S7. Vorlagen und Projekt-Labels (entschieden 2026-11-13)

Details in `docs/planer-abgleich.md`, Abschnitt 3. Ersetzt das heutige Label-Set-Modell. Breaking Change, `migrate:fresh` lokal.

**Datenmodell:**
- `labels.project_id` statt `labels.label_set_id`: Jedes Projekt hat eigene Labels (Schlüssel `key`, `names` als JSON Sprache → Name, Kategorie, Farbe, Position), Schlüssel und Name je Sprache eindeutig je Projekt, höchstens 30. Name in der Sprache des Projekts Pflicht (`docs/planer-abgleich.md` §5.2).
- `projects.label_set_id` entfällt. Optional `projects.template_id` (nullable, `nullOnDelete`) als Herkunft.
- Tabelle `templates`: `key` (nur mitgelieferte), `name`, `description`, `content` (JSON, `planer/2`-Vorlage), `created_by`, Zeitstempel.
- **Mitgelieferte Vorlagen als Dateien** (V3): `spec/templates/software-development.planer.json`, `event.planer.json`, `podcast.planer.json` mit `names` in `de` und `en`. Ein Befehl `php artisan planer:sync-templates` (auch im Seeder und nach dem Deployment) schreibt sie in `templates`, unveränderlich. `BuiltinLabelSets.php` und die Label-Übersetzungen in `lang/` entfallen. Die Dateien liegen in `spec/templates/` und gehen mit aufs Deployment.
- `label_sets` entfällt.

**Funktionen (Backend und GraphQL):**
- Vorlagen auflisten, ansehen, anlegen, duplizieren, ändern, löschen. Inhalt: Labels und optional Plan-Gerüst (Milestones, ToDos, Tasks mit Titel, Beschreibung, User-Story, Labels, Reihenfolge, `release.version`, Task-Status `planned`). Keine Fälligkeiten, Personen, Commits. **Der Editor bearbeitet nur Name, Beschreibung und Labels** (V3). Das Gerüst wird angezeigt, nicht bearbeitet.
- Projekt aus Vorlage anlegen (`createProject` mit optionalem `templateId`): Labels mit allen Sprachen kopieren, Gerüst anlegen. Solange der Importer (I2) fehlt, legt ein kleiner Dienst das Gerüst an. Mit I2 wird das durch `import` (`full`, leeres Projekt) ersetzt.
- Anzeige der Label-Namen in der Sprache der Oberfläche, sonst der Sprache des Projekts. Editor mit einem Feld je Sprache. Übersetzungen der mitgelieferten Vorlagen aus `lang/` in den Inhalt der Vorlage.
- Labels eines Projekts bearbeiten (`project.update`), Labels aus einer Vorlage übernehmen (ersetzen oder ergänzen; Labels an Tasks bleiben beim Ersetzen erhalten).
- Rechte: `labelSets.manage` → `templates.manage`, `labelSets.manageAny` → `templates.manageAny` (gleiche Rollen). Sichtbarkeit: alle Vorlagen für alle Angemeldeten lesbar, auch Rolle `lesend` (entschieden, `docs/planer-abgleich.md` 3.6 Nr. 4).
- MCP: `list_templates`, `create_project` mit optionalem `template_id`.

**Oberfläche:**
- Seite `/templates` ersetzt `/label-sets` (Liste, Editor für Labels, Ansicht des Gerüsts als Baum, Duplizieren, Löschen).
- Neues Projekt: Auswahl der Vorlage.
- Projekteinstellungen: Labels bearbeiten, „Labels aus Vorlage übernehmen“, „Als Vorlage speichern“.

**Noch nicht in S7:** „Als Vorlage speichern“ aus einem Projekt, Upload und Download einer `.planer.json`. Diese kommen mit Export und Import (I1, I2, I5). Bis dahin wird `content` in PHP mit den Regeln aus `docs/planer-abgleich.md` 3.4 und 3.6 geprüft, mit Tests.

**Tests:** Projekt aus Vorlage (Labels und Gerüst), Bearbeiten der Projekt-Labels verändert keine anderen Projekte, mitgelieferte Vorlagen unveränderlich, Rechte, `OutsiderAccessTest`/`MutationTest` ergänzen.

## I1. Export (V2, nach S7)

Details: `docs/planer-abgleich.md` §1a, §2, §5, §7 V1 und V2, §9.
- `App\Planer\Export`: Projekt → kanonisches `planer/2` (IDs `m-<id>` …, Werte nach §5.1, Labels mit `names`, Beschreibungen über `TiptapToMarkdown`, Reihenfolge, Release, Commit, User-Story, `revision`).
- PHP: kanonisches JSON wie `tools/planer` (Test: byte-gleich für alle gültigen Fixtures).
- PHP-Prüfung: Schema (`opis/json-schema` gegen `spec/planer-2.schema.json`) und Semantik, gegen dieselben Fixtures mit erwarteten Fehlern wie `tools/planer`.
- MCP `export_plan(project_id)`.

## I2. Import mit Trockenlauf (V2)

- `App\Planer\Import` mit `full` und `partial` nach §2.1 und §2.2, `partial` = ergänzen und ändern, nie löschen (§7 V2). Ruft die Dienste der App direkt auf, prüft jede Änderung über Policies, eine Transaktion, `planer_revision` +1.
- Neues Projekt ohne `project_id` (mit `projects.create`), aus Vorlage mit `template_id`.
- `dry_run`: Liste der geplanten Änderungen (anlegen, ändern, verschieben, löschen, je mit Pfad) und Fehler, ohne zu schreiben.
- Beschreibungen über `MarkdownToTiptap` (S3e), Warnungen in der Antwort.
- MCP `import_plan(project_id?, plan, mode, dry_run, notify_assignees, template_id?)`.
- Projekt aus Vorlage (S7) auf den Importer umstellen.
- Roundtrip-Tests: `export(import(datei)) = datei` (bis auf `-new-`-IDs), `import(export(db))` ändert nichts.

## I3. Aufräumen: ein Schreibweg (V2)

- Entfernen: MCP `create_project_plan`, `add_to_plan`, `update_plan_item`, `move_milestone`, `set_task_status`, `create_project`, `set_project_member`; `ProjectPlanImporter`, `ProjectPlanReader`, `PlanRules`, MCP-Teile von `PlanSchema`, nicht mehr genutzte Dateien in `graphql/operations/` (und `GraphQLRunner::runNamed`, falls ungenutzt).
- MCP danach: `list_projects`, `export_plan`, `import_plan`, `list_templates`, `list_users` (eingeschränkt, siehe Assistent).
- `docs/claude-connector.md` neu schreiben. Assistent (`draftProjectPlan`, `importProjectPlan`, `plan.graphql`) bleibt bis B5a unverändert.

## I4. Demo-Daten als Dateien (V3)

- `database/seeders/demo/website-relaunch.planer.json`, `sommerfest-2027.planer.json`. `DemoSeeder` legt Personen an und importiert die Dateien. Test: Seeder läuft, Export ergibt die Datei (bis auf IDs).

## I5. Baum-Ansicht, Downloads, Vorlagen aus Dateien (V3, V4, §5.3)

- Baustein `PlanerTree` (V4): zeigt ein `planer/2`-Dokument, optional mit Markierungen aus dem Trockenlauf. Typen aus `spec/planer-2.schema.json`.
- Projekteinstellungen: Download „Als planer.json“ und „Als Markdown“ (Sprache wählbar, Schalter „Übersetzungen in der Legende“, über `tools/planer/core` im Frontend).
- Datei-Import mit Vorschau (`dry_run` in `PlanerTree`), dann Eintragen.
- Vorlagen: „Als Vorlage speichern“ (Export + `template`), Upload einer `.planer.json` als Vorlage, Download einer Vorlage, Ansicht des Gerüsts mit `PlanerTree`.

## T2, T3. `pull`/`push`/`sync` und GitHub Action (nach I2)

Wie `docs/planer-abgleich.md` §1a (A-S2, A-S3), in `tools/planer/`. Tests gegen den echten MCP-Server der App (lokal), nicht gegen eine Nachbildung.

---

## Reihenfolge

U0 → T1 → T4 (Rest) → T5 → T6 → S2 → S3 → S3b → S3c → S3e → S4 → S5 → S6 → S7 → I1 → I2 → I3 → I4 → I5 → T2 → T3. (S1 erledigt.) S3c braucht die Zählung beim Papierkorb erst mit S5. S2 und S6 können auch dazwischen laufen.

## Nicht in diesem Auftrag (wird in der Planung geklärt)

- Webhook für GitHub Actions (`docs/planer-abgleich.md` §1a): nach T3.
- B5a Assistent nach `docs/assistent-ideen-konzept.md`: Entscheidungen Abschnitt 12 offen.
- B6 Ideen (mit Entwurf als `planer/2`-Ausschnitt, V5), KI-Kontext, MCP `create_idea`, `list_ideas`, `get_project_ai_context`.
- C1 Payloads (eventuell ersetzt durch Neuladen nach `planerRevision`, §7 V7).
