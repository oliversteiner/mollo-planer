# Auftrag Meilenstein M4 „Datei-Abgleich“ (I1–I5)

Stand: 2026-11-17, Planung. Geprüft: `mollo-planer` bis `7720d5a` (M2 abgenommen, M3 in Arbeit).
Rolle: Auftrag für die Umsetzung (Bob). Präzisiert `umsetzung.md` I1 bis I5 auf den heutigen Code. Wo sich dieses Dokument und `planer-abgleich.md` (§1a, §2, §5, §7) widersprechen, gilt dieses Dokument.

**Start erst nach der Abnahme von M3.** Dieser Auftrag setzt die Ergebnisse von M3 voraus (Konverter `App\Planer\RichText`, Labels je Projekt mit `key`/`names`, Tabelle `templates`, `TemplateScaffold`). Die Planung gleicht ihn nach der Abnahme M3 mit dem Code ab; Abweichungen von M3 gehen diesem Dokument vor, wenn die Planung sie so einträgt.

---

## 0. Was schon da ist (nicht neu bauen)

- **Spezifikation und Werkzeuge:** `spec/format-v2.md`, `spec/planer-v2.schema.json`, Fixtures in `spec/fixtures/` (gültig, ungültig, `golden/`, `richtext/`), `tools/planer/core`: `planer-canonical.mjs` (`canonicalText`), `validate-planer.mjs` (liest das Schema über `node:fs`), `render-planer.mjs` (`renderPlaner(plan, { lang, legendTranslations })`), `planer-from-md.mjs`, `template.mjs` (`toTemplate`, `templateIssues`), `richtext.mjs`.
- **App:** `App\Planer\PlanerRevision`, `App\Services\Deletion` (Papierkorb mit `deletion_id`), `Ordering`, `KanbanOrdering`, `ItemStatusSync`, `TaskNotifier`, `ReleaseRules`, `ProjectMembership` (`set`, `ensureMember`, Grundregel §7.3), `TaskLabels`, `Rights` (mit Obergrenze für archivierte Projekte), Policies.
- **Aus M3 (vorausgesetzt):** `App\Planer\RichText\MarkdownToTiptap` (`strict`/`lenient`), `TiptapToMarkdown`, Labels mit `key`/`names`, `templates`, `planer:sync-templates`, `App\Planer\TemplateScaffold`, `App\Planer\PlanSchema` (RF8).
- **Wird ersetzt (I3):** MCP-Werkzeuge `create_project_plan`, `add_to_plan`, `update_plan_item`, `move_milestone`, `set_task_status`, `create_project`, `set_project_member`, `get_project`; `ProjectPlanImporter`, `ProjectPlanReader`, `PlanRules`, MCP-Teile von `PlanSchema`, `graphql/operations/*.graphql`, `RunsGraphQL`/`GraphQLRunner::runNamed` (soweit danach ungenutzt).
- **Bleibt bis B5a:** Assistent (`ProjectPlanAssistant`, `draftProjectPlan`, `importProjectPlan`, `plan.graphql`, `ReadsProjectPlanInput`, `PlainTextDocument`).
- **Nicht vorhanden:** `opis/json-schema` (neue Abhängigkeit, siehe §3.3), Spalte für die Reihenfolge der Tasks im ToDo (nur `board_position` für die Kanban-Spalte).

## 1. Entscheidungen (Oliver, 2026-11-17)

1. **Grenzen: Die App folgt der Datei.** Die App erzwingt die Grenzen von `spec/format-v2.md` §2 und §8, damit jedes Projekt exportierbar ist.
2. **Leeres Projekt:** `milestones` darf leer sein (bisher mindestens 1).
3. **Rollen der Mitglieder:** `members[].role` ist der Schlüssel einer Projektrolle (auch eigene Rollen aus R1), nicht mehr nur `owner`/`manager`/`member`/`guest`. Der Import prüft, dass es die Rolle gibt.
4. **Projektbeschreibung:** `project.description` ist Markdown nach §6.1 wie bei Milestone, ToDo, Task (bisher Klartext bis 2000 Zeichen).
5. **Reihenfolge der Tasks im ToDo:** neue Spalte `tasks.position` (Platz im ToDo), getrennt von `board_position` (Platz in der Kanban-Spalte).

## 2. Vorarbeit (eigene Commits, vor I1)

### 2.1 Spezifikation und `tools/planer` angleichen (§1 Nr. 2 bis 4)

- `spec/format-v2.md` §2.1, §2.2, §8, §10 und `spec/planer-v2.schema.json`:
  - `milestones`: `minItems` 0.
  - `members[].role`: Muster `^[a-z0-9_-]{1,40}$` statt Aufzählung. Muster vorher mit der Erzeugung der Rollen-Schlüssel in `RoleAdministration` abgleichen (Schlüssel aus dem englischen Namen); passt es nicht, gilt das Muster der App, und die Planung wird informiert.
  - `project.description`: Markdown nach §6.1, höchstens 5000 Zeichen (wie `$defs/markdown`).
- Validator, `render` (Projektkopf §7.5: Beschreibung als Markdown-Block) und `md2json` (`planer-from-md.mjs`) nachführen; Fixtures und `golden/` ergänzen: leeres Projekt, eigene Rolle, Projektbeschreibung mit Formatierung.
- Die Tabelle in §2.2 („Werte des GraphQL-Enums `ProjectRole`“) ersetzen durch „Schlüssel einer Projektrolle der App“.
- `tools/planer`-Tests grün; `CHANGELOG` bzw. Hinweis in `format-v2.md` §10 (Breaking Change ist vor dem ersten Einsatz erlaubt).

### 2.2 Grenzen in der App (§1 Nr. 1)

| Grenze | Wo prüfen |
|---|---|
| höchstens 30 Milestones je Projekt | `createMilestone`, Wiederherstellen eines Milestones, Gerüst aus Vorlage, Import |
| höchstens 50 ToDos je Milestone | `createTodo`, `moveTodo` in einen anderen Milestone, Wiederherstellen, Import |
| höchstens 50 Tasks je ToDo | `createTask`, `moveTaskToTodo`, Wiederherstellen, Import |
| höchstens 50 Mitglieder je Projekt | `setProjectMember`, `createProject` mit `members`, `ensureMember` beim Zuweisen, Import |
| höchstens 30 Labels je Projekt | schon in M3 |
| Beschreibung (Projekt, Milestone, ToDo, Task) höchstens 5000 Zeichen **als kanonisches Markdown** | `RichTextRule` mit Parameter `markdown_max:5000`, gemessen über `TiptapToMarkdown`; Kommentare behalten die heutige Grenze (20 000 Zeichen Text) |
| Titel 2–160, Projektname 2–120, User-Story 1–80/160/160 | schon so; im Abgleichtest mit dem Schema prüfen |

- Eine Stelle für die Zahlen: `App\Planer\PlanerLimits` (Konstanten), gelesen von Regeln, Diensten und Import. `SchemaAgreementTest` vergleicht sie mit `spec/planer-v2.schema.json` (`maxItems`, `maxLength`).
- Meldungen DE/EN nennen die Grenze („Ein ToDo hat höchstens 50 Tasks.“). Wiederherstellen aus dem Papierkorb, das eine Grenze überschreiten würde, wird mit dieser Meldung abgelehnt.
- Oberfläche: Knöpfe „anlegen“ bleiben sichtbar; der Server lehnt ab, die Meldung erscheint wie andere Fehler (kein Vorab-Zählen im Frontend nötig).
- **Bestehende Daten:** `php artisan planer:check-limits` listet je Projekt alle Überschreitungen (Anzahl, Beschreibungslänge), ändert nichts, Exit-Code ≠ 0 bei Funden. In `deploy-template.md` vor dem Deployment von M4 aufnehmen. Ein Projekt mit Überschreitungen lässt sich bis zur Bereinigung nicht exportieren (Export meldet die Stellen).

### 2.3 `tasks.position` (§1 Nr. 5)

- Migration: `tasks.position` (unsigned int), Startwerte je ToDo nach heutiger Anzeige der Zeitachse (Fälligkeit, dann `id`), Index `(todo_id, position)`.
- `TaskObserver`/`Ordering`: anlegen ans Ende des ToDos, Lücke schließen beim Löschen in den Papierkorb, Wiederherstellen ans Ende, `moveTaskToTodo` ans Ende des Ziel-ToDos. Revision wie bisher (+1 je Vorgang).
- Neue Mutation `moveTaskInTodo(id, position)` (Recht wie `moveTodo`, d. h. `task.move`; im archivierten Projekt abgelehnt).
- Zeitachse: Tasks im ToDo nach `position`; im Task-Menü der Zeitachse „Nach oben“/„Nach unten“ wie bei Milestones (F5.4). Kanban bleibt bei `board_position`.
- `db-schema.ts` nachführen; `ArchivedProjectMutationsTest`, `MutationCoverageTest` ergänzen.

## 3. I1 Export

### 3.1 `App\Planer\Export`

`Export::document(Project $project): array` → `planer-v2`-Dokument, `Export::text(Project): string` → kanonischer Text.

| Datei | App |
|---|---|
| `format` | `"planer-v2"` |
| `revision` | `projects.planer_revision` |
| `project.name`, `locale` | `projects.name`, `projects.locale` (klein) |
| `project.description` | `TiptapToMarkdown(projects.description)`, fehlt bei leer |
| `project.members[]` | Mitglieder nach Name, `email`, `role` = Schlüssel der Projektrolle |
| `project.labels[]` | Labels nach `position`: `key`, `names`, `category`, `color` |
| `milestones[]` | nach `position`, `id` = `m-<id>`, `title`, `description` (Markdown), `dueDate`, `release` |
| `todos[]` | nach `position`, `t-<id>`, Felder wie oben |
| `tasks[]` | nach `tasks.position`, `k-<id>`, `status`, `assigneeEmail`, `labels` (Schlüssel in Reihenfolge der Projekt-Labels), `commit`, `userStory`, `dueDate`, `description` |

- Nur nicht gelöschte Elemente (Papierkorb fehlt). Archivierte Projekte lassen sich exportieren (`planer.export` bleibt im Archiv erhalten, M2).
- Kanonisches JSON in PHP wie `canonicalText` in `tools/planer` (Schlüsselreihenfolge, zwei Leerzeichen, `null`/leere `labels` weglassen, Zeilenende). Test: für jede gültige Fixture `canonical(PHP) == canonical(JS)` byte-gleich.
- Der Export prüft sein Ergebnis (§3.3) und wirft bei Verstoß einen Fehler mit Pfad (darf nach §2.2 nur bei Altdaten vorkommen).
- Die Revision ändert sich beim Export nicht.

### 3.2 Schnittstellen

- GraphQL `exportPlan(projectId: ID!): String!` (kanonischer Text), Recht `planer.export`.
- MCP `export_plan(project_id)` → `{ "document": … }` nach dem Vertrag in `planer-abgleich.md` §1a.

### 3.3 Prüfung in PHP

- `opis/json-schema` (neue Abhängigkeit, entschieden in `umsetzung.md` I1) gegen `spec/planer-v2.schema.json`; dazu die semantischen Regeln aus `format-v2.md` §5 (IDs eindeutig, Labels vorhanden, `assigneeEmail` unter den Mitgliedern, Releases/Commits) in `App\Planer\Validator`.
- Gemeinsame Testfälle: alle Dateien in `spec/fixtures/` mit erwartetem Ergebnis; PHP und `tools/planer` melden dieselben Fehlerarten und Pfade (Text darf abweichen). Fehlt eine Erwartungsdatei, ist der Test rot.

## 4. I2 Import mit Trockenlauf

### 4.1 `App\Planer\Import`

`Import::run(?Project $project, array $document, Mode $mode, bool $dryRun, bool $notifyAssignees, User $actor): ImportResult`

- **Modi** nach `planer-abgleich.md` §2.1: `full` (fehlende Elemente kommen als **ein** Löschvorgang in den Papierkorb, über `Deletion` mit einer gemeinsamen `deletion_id`), `partial` (ergänzen und ändern, nie löschen; fehlende Felder bleiben).
- **Revision:** `full` verlangt `document.revision == planer_revision` (Fehler nennt beide Zahlen); `partial` prüft sie nicht. Ein Import mit Änderungen ergibt genau +1 (über `PlanerRevision`, eine Transaktion), ein Import ohne Änderungen und ein Trockenlauf 0.
- **Rechte:** jede einzelne Änderung über die Policies/`Rights` der App (anlegen, ändern, verschieben, zuweisen, löschen, Mitglieder, Labels, Release). Fehlt ein Recht, bricht der Import ab und nennt alle betroffenen Pfade. Archivierte Projekte: Abbruch (wie alle Schreibwege).
- **Wege:** direkt über die Dienste (`Ordering`, `KanbanOrdering`, `Deletion`, `ItemStatusSync`, `TaskLabels`, `ProjectMembership`, `ReleaseRules`), nicht über GraphQL-Strings. Statusänderungen setzen Kanban-Positionen wie `moveTask`.
- **IDs:** `x-<id>` muss zu diesem Projekt gehören und darf nicht im Papierkorb liegen (sonst Abbruch mit Pfad); `x-new-<n>` wird angelegt. Ergebnis enthält die Zuordnung `-new-` → echte ID.
- **Reihenfolge:** Array-Reihenfolge ergibt `position` (Milestone, ToDo, Task), auch über Elternteile hinweg.
- **Mitglieder:**
  - Personen werden über `email` gefunden (Groß-/Kleinschreibung egal). Unbekannte oder nicht freigegebene Personen: Abbruch mit Pfad (der Import legt keine Konten an).
  - `role` muss eine Projektrolle der App sein (§1 Nr. 3); Rollen mit „Projekt löschen“ nur mit `owner.manage` (wie F2/F3.9).
  - `full`: Mitglieder, die im Dokument fehlen, werden entfernt (ihre Tasks unzugewiesen). Grundregel §7.3 gilt für das Ergebnis: Ohne Person mit „Projekt löschen“ bricht der Import ab. Die importierende Person entfernt sich nicht selbst, wenn sie dadurch den Zugriff verliert (Abbruch mit Hinweis).
- **Labels:** `project.labels` wird wie `updateProjectLabels` (M3) übernommen (`key` bleibt, Namen/Farbe/Kategorie aktualisiert); im Modus `full` werden fehlende Labels gelöscht, im Modus `partial` nicht. Task-Labels über `TaskLabels::set`.
- **Beschreibungen:** `MarkdownToTiptap` Modus `strict`; Warnungen gibt es dort nicht, Fehler brechen ab.
- **Grenzen:** §2.2 gilt für das Ergebnis (Schema prüft schon die Datei).
- **Freigabe-Regeln** (`ReleaseRules`) für das Ergebnis, nicht Schritt für Schritt.
- **Mails:** nur mit `notifyAssignees: true`, sonst keine.
- **Ungeändert:** `icon`, Kommentare, `created_by`, Zeitstempel; neue Elemente bekommen `created_by` = importierende Person.

### 4.2 Trockenlauf und Ergebnis

`ImportResult`: `changes` (je Eintrag `kind`: `create`/`update`/`move`/`delete`, `path` im Dokument, `id`, geänderte Felder), `errors` (Pfad, Art, Text), `idMap`, `document` (Export nach dem Import, beim Trockenlauf `null`). Dieselbe Liste dient Vorschau, Fehlermeldungen von `push` und später dem Assistenten.

### 4.3 Schnittstellen

- GraphQL `importPlan(projectId: ID, document: String!, mode: ImportMode = FULL, dryRun: Boolean = false, notifyAssignees: Boolean = false, templateId: ID): ImportResult!`. Ohne `projectId`: neues Projekt (Recht `projects.create`, die importierende Person wird Mitglied mit der Ersteller-Rolle, wie F3.3; `revision` der Datei wird nicht geprüft).
- MCP `import_plan(project_id?, plan, mode, dry_run, notify_assignees, template_id?)` nach `planer-abgleich.md` §1a.
- **Projekt aus Vorlage auf den Importer umstellen:** `createProject` mit `templateId` nutzt `Import` (`full` in ein neues Projekt). `TemplateScaffold` aus M3 entfällt. Verhalten aus M3 bleibt (Labels, Gerüst, Status `planned`, Revision 1).

### 4.4 Tests (mindestens)

- Roundtrip: `export(import(datei)) == datei` für alle gültigen Fixtures (bis auf `-new-`-IDs und `revision`); `import(export(db))` ändert nichts und erhöht die Revision nicht (ein Import ohne Änderungen ergibt 0, nicht +1).
- `full` löscht Fehlendes als ein Vorgang; Wiederherstellen holt alles zurück.
- `partial` löscht nie.
- Rechte je Art der Änderung mit Standardrolle und eigener Rolle; Gast, Lesend, Archiv.
- Revision falsch → Abbruch mit beiden Zahlen; Trockenlauf schreibt nichts.
- Mitglieder: unbekannte E-Mail, unbekannte Rolle, letzter Holder, Selbstentfernung.
- Grenzen überschritten → Abbruch mit Pfad.
- `OutsiderAccessTest`, `MutationCoverageTest`, `ArchivedProjectMutationsTest` ergänzen; `RightUsageTest`: `planer.export`, `planer.import` aus `PENDING`.

## 5. I3 Aufräumen: ein Schreibweg

- Entfernen: MCP `create_project_plan`, `add_to_plan`, `update_plan_item`, `move_milestone`, `set_task_status`, `create_project`, `set_project_member`, **`get_project`** (ersetzt durch `export_plan`); `ProjectPlanImporter`, `ProjectPlanReader`, `PlanRules`, MCP-Teile von `PlanSchema`, `graphql/operations/*.graphql`, `RunsGraphQL`, `ResolvesPeople`, `ExplainsGraphQLErrors` und `GraphQLRunner::runNamed`, soweit danach ungenutzt.
- MCP danach: `list_projects`, `export_plan`, `import_plan`, `list_templates`, `list_users`. Archivierte Projekte bleiben gesperrt.
- `docs/claude-connector.md` neu schreiben (Werkzeuge, Beispiele für `export_plan` und `import_plan` mit Trockenlauf).
- **Breaking Change am Connector:** Der Connector auf der laufenden Instanz verliert die alten Werkzeuge. In `deploy-template.md` und im Commit vermerken.
- Assistent bleibt bis B5a unverändert (auch wenn er `ProjectPlanImporter` nutzt: dann bleibt dieser bis B5a, und die Planung wird informiert).

## 6. I4 Demo-Daten als Dateien

- `database/seeders/demo/website-relaunch.planer.json`, `sommerfest-2027.planer.json` (Inhalt wie heute im `DemoSeeder`, mit Release 1.1 geplant bzw. 1.0 veröffentlicht, User-Storys, Labels aus den Vorlagen).
- `DemoSeeder` legt Personen an (wie heute) und importiert die Dateien (`full`, neues Projekt, als Anna bzw. die jeweilige Owner-Person). Die Daten, die nicht im Format stehen (Kommentare, Zeitstempel für „bearbeitet“, Icons), setzt der Seeder danach wie heute.
- Test: Seeder läuft; Export ergibt die Datei (bis auf IDs und Revision).
- `DevelopmentHistorySeeder` (v1-Format): auf eine `planer-v2`-Datei umstellen (über `tools/planer convert` einmalig erzeugt und eingecheckt) oder entfernen, wenn niemand ihn braucht. Rückfrage an die Planung, falls unklar.

## 7. I5 Baum-Ansicht, Downloads, Vorlagen aus Dateien

- **`PlanerTree`** (Komponente): zeigt ein `planer-v2`-Dokument (Milestones › ToDos › Tasks mit Status, Fälligkeit, Zuständig, Labels, User-Story, Release), optional mit Markierungen aus `ImportResult.changes` (neu, geändert, verschoben, gelöscht; Farbe **und** Text/Icon, nicht nur Farbe). Typen aus dem Schema erzeugt oder von Hand nach dem Schema, mit Test gegen eine Fixture.
- **Downloads** in den Projekteinstellungen (Recht `planer.export`): „Als planer.json“ (Text aus `exportPlan`) und „Als Markdown“ (Sprache wählbar, Schalter „Übersetzungen in der Legende“). Markdown im Frontend mit `renderPlaner` aus `tools/planer/core` (Nuxt-Alias auf `tools/planer/core`; nur Module ohne `node:`-Importe; `validate-planer.mjs` nicht im Frontend). Dateiname `<projekt-slug>.planer.json` bzw. `.md`.
- **Datei-Import** in den Projekteinstellungen (Recht `planer.import`): Datei wählen → Trockenlauf → Vorschau in `PlanerTree` mit Liste der Änderungen und Fehler → „Eintragen“ (mit Bestätigung, die Löschungen nennt) → Neuladen über `refreshProject`. Modus wählbar (`full` Standard, `partial`).
- **Neues Projekt aus Datei:** im Dialog „Neues Projekt“ zusätzlich „Aus Datei“ (Trockenlauf, Vorschau, Anlegen).
- **Vorlagen:**
  - „Als Vorlage speichern“ in den Projekteinstellungen: Export plus `toTemplate`-Logik in PHP (`App\Planer\Template::fromDocument`, gleiche Regeln wie `template.mjs`, gemeinsamer Test).
  - Upload einer `.planer.json` als Vorlage (`templateIssues`-Regeln in PHP), Download einer Vorlage, Gerüst in `/templates` mit `PlanerTree` (ersetzt die Baum-Ansicht aus M3).
  - Recht wie in M3 (`templates.manage`).
- Texte DE/EN, hell/dunkel, Tastatur, Telefonbreite.

## 8. Ablauf und Abschluss

1. §2.1 Spezifikation → §2.2 Grenzen (mit `planer:check-limits`) → §2.3 `tasks.position` → I1 → I2 → I3 → I4 → I5. Ein Commit je Teil, `umsetzung.md` „Stand“ nachführen.
2. Stopp und Rückfrage an die Planung, falls:
   - PHP und `tools/planer` in einem Testfall unterschiedliche kanonische Texte oder Fehler liefern und die Ursache in der Spezifikation liegt;
   - der Import einen Fall trifft, den §4.1 nicht regelt (z. B. Task in ein ToDo verschoben, das im selben Import gelöscht wird);
   - `planer:check-limits` auf echten Daten Überschreitungen findet, die sich nicht von Hand bereinigen lassen;
   - der Assistent ohne `ProjectPlanImporter` nicht weiterläuft.
3. Am Ende: alle Prüfungen grün (auch `tools/planer`), `migrate:fresh --seed --seeder=DemoSeeder`, im Browser ansehen (hell/dunkel, DE/EN, Tastatur, Telefonbreite), dann anhalten für die Abnahme M4.
