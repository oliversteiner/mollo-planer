# Umsetzung planer/2 in mollo-planer: sichere Arbeitsschritte

Stand: 2026-11-13 (U0, T1, T4, S3b, S3c, S3d, S7 ergänzt). Geprüft: `mollo-planer` bis `a98662d`, `mollo-version` 1.0.0 bis `c169118`.
Rolle: Auftrag für die Umsetzungssitzung in `mollo-planer`. Ergänzt `docs/umsetzung-planer-v2.md` (Grundregeln §0, Checkliste §4 und Stopp-Punkte §5 gelten).
Rahmen (Oliver, 2026-11-13): **Breaking Changes sind erlaubt.** Es gibt keine Altlasten, nur Demoprojekt und Testdaten. `migrate:fresh` lokal ist erlaubt.

Diese Schritte hängen an keiner offenen Entscheidung. Alles, was noch offen ist, steht am Ende unter „Nicht in diesem Auftrag“ und wird in der Planung geklärt.

**Seit 2026-11-13 gibt es nur noch eine Spur.** Format und Werkzeuge aus `mollo-version` ziehen nach `mollo-planer` (`docs/planer-abgleich.md` §4). Wo ältere Dokumente „Spur A“ oder `mollo-version` nennen, gilt jetzt `tools/planer/` bzw. `spec/` in diesem Repository.

**Stand:** S1 erledigt (`d368475`, `f03d853`). U0 erledigt (`c2b1577`). T1 erledigt (`072fcbb`). T4 erledigt bis auf `template` und `validate --template`, die auf eine Entscheidung zu `planer-abgleich.md` §3.4 und §3.6 warten.

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

**Zusätzlich in T4:** Task-Status `idea` und `planned` (`docs/planer-abgleich.md` §8), Markierungen `[i]` (idea) und `[p]` (planned).

## T5. IDs aus der Datenbank (V1, entschieden 2026-11-13)

`docs/planer-abgleich.md` §7 V1. In `spec/` und `tools/planer/`:
- `id` hat die Form `m-<Zahl>`, `t-<Zahl>`, `k-<Zahl>` (Datenbank-ID) oder `m-new-<Zahl>`, `t-new-<Zahl>`, `k-new-<Zahl>` (lokal neu, noch nicht importiert). Eindeutig je Datei.
- Feld `appId` entfällt in Schema, Spezifikation, Validator, kanonischer Form, `render` (Kommentar nur noch `<!-- id: k-88 -->`) und `md2json`.
- Helfer vergeben `-new-`-Nummern (nächste freie je Präfix in der Datei). `convert v1-zu-v2` vergibt nur `-new-`-IDs.
- Regel „nie wiederverwenden“ im Validator entfällt (die Datenbank sorgt dafür).
- Fixtures, Golden Files, `docs/beispiel-planer.md` nachführen.

**S3d entfällt** (ersetzt durch T5).

T1 und T4 vor S3, damit die App gleich gegen das neue Format gebaut wird. T2 (`pull`/`push`/`sync`) und T3 (GitHub Action) folgen nach dem Export und Import in der App (siehe „Nicht in diesem Auftrag“).

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
- Tabelle `templates`: `key` (nur mitgelieferte), `name`, `description`, `content` (JSON, `planer/2`-Vorlage), `created_by`, Zeitstempel. Mitgelieferte Vorlagen aus `BuiltinLabelSets` mit Namen in DE und EN (Übersetzung wie heute über den Schlüssel), unveränderlich.
- `label_sets` entfällt.

**Funktionen (Backend und GraphQL):**
- Vorlagen auflisten, ansehen, anlegen, duplizieren, ändern, löschen. Inhalt: Labels und optional Plan-Gerüst (Milestones, ToDos, Tasks mit Titel, Beschreibung, User-Story, Labels, Reihenfolge, `release.version`). Keine Fälligkeiten, Personen, Commits, Status.
- Projekt aus Vorlage anlegen (`createProject` mit optionalem `templateId`): Labels mit allen Sprachen kopieren, Gerüst anlegen.
- Anzeige der Label-Namen in der Sprache der Oberfläche, sonst der Sprache des Projekts. Editor mit einem Feld je Sprache. Übersetzungen der mitgelieferten Vorlagen aus `lang/` in den Inhalt der Vorlage.
- „Als Vorlage speichern“ aus einem Projekt.
- Labels eines Projekts bearbeiten (`project.update`), Labels aus einer Vorlage übernehmen (ersetzen oder ergänzen; Labels an Tasks bleiben beim Ersetzen erhalten).
- Rechte: `labelSets.manage` → `templates.manage`, `labelSets.manageAny` → `templates.manageAny` (gleiche Rollen). Sichtbarkeit: alle Vorlagen für alle Angemeldeten lesbar, auch Rolle `lesend` (entschieden, `docs/planer-abgleich.md` 3.6 Nr. 4).
- MCP: `list_templates`, `create_project` mit optionalem `template_id`.

**Oberfläche:**
- Seite `/templates` ersetzt `/label-sets` (Liste, Editor für Labels, Ansicht des Gerüsts als Baum, Duplizieren, Löschen).
- Neues Projekt: Auswahl der Vorlage.
- Projekteinstellungen: Labels bearbeiten, „Labels aus Vorlage übernehmen“, „Als Vorlage speichern“.

**Noch nicht in S7** (braucht Problem 4, Prüfung gegen das JSON-Schema): Vorlage aus hochgeladener `.planer.json`, Vorlage als Datei herunterladen. Bis dahin wird `content` in PHP mit den Regeln aus `docs/planer-abgleich.md` 3.4 und 3.6 geprüft, mit Tests.

**Tests:** Projekt aus Vorlage (Labels und Gerüst), Bearbeiten der Projekt-Labels verändert keine anderen Projekte, mitgelieferte Vorlagen unveränderlich, Rechte, `OutsiderAccessTest`/`MutationTest` ergänzen.

---

## Reihenfolge

U0 → T1 → T4 → T5 → S2 → S3 → S3b → S3c → S4 → S5 → S6 → S7. (S1 erledigt.) S3c braucht die Zählung beim Papierkorb erst mit S5. S2 und S6 können auch dazwischen laufen.

## Nicht in diesem Auftrag (wird in der Planung geklärt)

- R2 Rest und PHP-Teil `backend/app/Planer/` (Schema-Prüfung mit `opis/json-schema`, Semantik, kanonisches JSON, Markdown ↔ Rich Text, gemeinsame Fixtures mit `tools/planer`): Ort entschieden (§4), Reihenfolge folgt mit Problem 5.
- T2 `pull`/`push`/`sync`/`--watch` und T3 GitHub Action: nach `export_plan`/`import_plan`.
- Download als Markdown (Sprache wählbar, optional Legende mit Übersetzungen) und als `planer.json` in der App: nach `export_plan` (`docs/planer-abgleich.md` §5.3).
- B5 `importPlanIntoProject` und P6 Import/Export der Datei: `export_plan`/`import_plan` (Semantik entschieden, `docs/planer-abgleich.md` §2), Webhook, Abbildung von `labelNames` auf Labels und von Rollen und Sprache sind offen (`docs/planer-abgleich.md`).
- B5a Assistent nach `docs/assistent-ideen-konzept.md`: Entscheidungen Abschnitt 12 offen.
- B6 Ideen, KI-Kontext, MCP `create_idea`, `list_ideas`, `get_project_ai_context`, `import_plan`.
- C1 Payloads.
