# Refactoring vor der Umsetzung des Schema-Reviews

Stand: 2026-11-12, geprüft bis Commit `347a4d5`. Bezieht sich auf `docs/schema-review.md` (Abschnitte A1–A5, B1–B3, C1–C3) und `docs/assistent-ideen-konzept.md`.
Frage: Welche Umbauten im heutigen Code machen die geplanten Änderungen einfacher und sicherer?
Rollen: Dieses Dokument ist Analyse und Plan. Die Umsetzung erfolgt in einer separaten Sitzung.

---

## Stand der Umsetzung

| # | Refactoring | Stand |
|---|---|---|
| R1 | Eine Reihenfolge-Logik | **erledigt** (`e003483`): `App\Services\Ordering` ersetzt `MilestoneOrder`, sperrt das Projekt bei jeder Änderung, `close()` schließt Lücken nach dem Löschen eines Milestones, `KanbanOrdering` ist dünne Schicht darüber. Tests: `MilestoneOrderTest`. SQLite und `composer test:mariadb` grün (553 Tests). Vorab behoben (`b08f8bd`): Rollback der Milestone-Positions-Migration scheiterte auf MariaDB und ließ Testdaten mit 2FA aus zurück. ToDos bleiben bis A3 offen |
| R2 | Plan-Struktur an einer Stelle | **pausiert** bis das JSON-Schema `planer/2` in `mollo-version` (Schritt P1) steht. Dann prüft die PHP-Seite dagegen (`docs/planer-datei-ssot.md`, Abschnitt 7). Bis dahin **teilweise**: `App\Plan\PlanSchema` hält Grenzwerte und die Schemata für den Assistenten und die MCP-Werkzeuge (Ausgabe unverändert, Snapshot-Tests in `PlanSchemaSnapshotTest`). `PlanLimitsTest` prüft die `@rules` in `graphql/plan.graphql` gegen die Konstanten. Offen: Wertobjekte `PlanMilestone`/`PlanTodo`/`PlanTask` und der Umbau des Importers. Offen auch: `status` (Task) und `position` (Milestone) fehlen im Assistenten und in GraphQL. Das zu ändern wäre eine Verhaltensänderung, dafür fehlt die Freigabe |
| R3 | GraphQL-Operationen bündeln und prüfen | **erledigt**: 15 Operationen unter `backend/graphql/operations/`, `GraphQLRunner::runNamed`, Test `OperationFilesTest` prüft jede gegen das Schema. Zählung im Plan war ungenau: im Code 17 Aufrufstellen in 11 Dateien, 15 verschiedene Operationen |
| R4 | Ein Lösch-Pfad | **Backend erledigt**: `App\Services\Deletion` ist die eine Stelle zum Löschen. Die fünf Projekt-Mutationen nutzen den gemeinsamen Resolver `DeleteItem` (Unterklasse je Modell). `deleteUser` bleibt bei `@delete`. Schema-Ausgabe für das Frontend unverändert. Frontend erledigt (`d368475`): `useDelete` ist der eine Einstieg für Projektelemente, weiterhin mit Bestätigung. Label-Sets bleiben bei `useConfirmedDelete` |
| R5 | Kleinere Vorbereitungen im Backend | offen (Plan: keine Vorab-Arbeit nötig, nur `DerivesProject`/`RecordsCreator` beim Schema-Umbau) |
| R6 | Kleinere Vorbereitungen im Frontend | offen (Plan: keine Vorab-Arbeit nötig) |
| R7 | Vollständigkeitstest für Mutationen | **teilweise erledigt**: `MutationCoverageTest` prüft, dass jede Schema-Mutation in `OutsiderAccessTest` oder `MutationTest` steht. Dafür 11 Zeilen ergänzt (die Liste war im Plan als vollständig beschrieben, war sie nicht). Ausnahme: `updateMyAvatar` (Multipart-Upload, eigener Test in `AvatarTest`). Liste „in archivierten Projekten verboten“ erledigt (`f03d853`): jede Schema-Mutation steht auf der Liste oder ist mit Begründung ausgenommen |

Weitere Refactorings seit `d736d6a`, außerhalb dieses Plans:
- `7f4158f`: Trait `ExplainsGraphQLErrors` für die Fehlermeldungen der MCP-Plan-Werkzeuge. Der Importer liest die Mitglieder aus dem bereits geladenen Projektbaum. Passt zu R2 und R3, ändert dort nichts am Plan.
- `ac6b445`: Basisklasse `McpTestCase` für die MCP-Tests. Erleichtert die Tests zu R2 und R3.

---

## Kurzfazit

Der Code ist gut vorbereitet: Rechte laufen zentral über `ProjectPermissions::allows`, Policies nutzen alle `ChecksProjectAbilities`, Sichtbarkeit läuft über `VisibleToViewer` mit `projectPath()`, Status nur über `ItemStatusSync`. Das Archiv (A5) und `project_id` (A1) lassen sich dort jeweils an **einer** Stelle einbauen.

Vier Stellen sind dagegen verstreut oder mehrfach vorhanden. Sie sollten **vor** dem Umbau zusammengeführt werden, sonst müssen die Breaking Changes an vielen Stellen von Hand nachgezogen werden:

| # | Refactoring | Hilft bei | Aufwand |
|---|---|---|---|
| R1 | Eine Reihenfolge-Logik statt drei | A3, A5, A4 | mittel (Rest) |
| R2 | Plan-Struktur an einer Stelle | A4, B1, B3 | mittel |
| R3 | GraphQL-Operationen im PHP-Code bündeln und prüfen | A3, A4, C1 | klein |
| R4 | Ein Lösch-Pfad statt sechs `@delete` | A5 | klein |

Dazu kommen kleinere Vorbereitungen (R5–R7). Keines der Refactorings ändert das Verhalten der App. Sie lassen sich mit den bestehenden Tests absichern und einzeln committen.

---

## R1. Eine Reihenfolge-Logik statt drei

**Ausgangslage vor R1 (Stand `c8f3f46`):**

| Ort | Was | Sperre | Lücke nach Löschen |
|---|---|---|---|
| `Services/MilestoneOrder` (Hook in `Milestone::booted`, `MoveMilestone`) | Milestone anlegen, Lücke öffnen, verschieben | **keine** | bleibt |
| `Services/KanbanOrdering` | Task anlegen und verschieben in der Spalte | `lockForUpdate` auf dem Projekt | wird beim nächsten Verschieben geschlossen |

**Offene Probleme:**
- Zwei gleichzeitige `createMilestone` oder `moveMilestone` können doppelte Positionen erzeugen. `MilestoneOrder` sperrt nicht.
- Löschen schließt keine Lücken. Nach einem gelöschten Milestone läuft `position` z. B. 0, 1, 3. Die Zeitachse zeigt eine eigene Nummer (`MilestoneNumber :number="item.number"`), darum fällt es heute nicht auf.
- Für `todos.position` (A3) käme eine dritte Variante dazu, für Papierkorb und Wiederherstellen (A5) überall „Lücke schließen“ und „ans Ende“.

**Vorschlag:** `MilestoneOrder` zu einem allgemeinen Service `App\Services\Ordering` ausbauen. Die Methoden von `MilestoneOrder` sind schon die richtigen Bausteine, sie brauchen nur eine Gruppe statt fest `project_id`:

```php
// Gruppe: welche Zeilen bilden eine Reihenfolge, und welches Elternteil wird gesperrt
$ordering->nextPosition($model);           // heute MilestoneOrder::nextPosition
$ordering->openSlot($model, $position);    // heute MilestoneOrder::openSlot
$ordering->move($model, $position, $newGroup = null);  // heute MilestoneOrder::move
$ordering->close($model);                  // neu: nach Löschen Lücke schließen
```

- Jedes sortierte Model beschreibt seine Gruppe einmal, z. B. über ein Interface `Ordered` mit `orderGroup()` (Spalten und Werte) und `orderLockTarget()` (das Projekt).
- Jede Methode sperrt zuerst das Projekt (`lockForUpdate`), wie heute `KanbanOrdering::lockProject`.
- `KanbanOrdering` bleibt als dünne Schicht: Spaltenwechsel setzt den Status und nummeriert die alte Spalte über `Ordering`.
- A3 (`todos.position`, `moveTodo`) und A5 (Lücke schließen, Wiederherstellen ans Ende) sind danach je ein paar Zeilen.

**Tests:** bestehende Kanban- und MoveMilestone-Tests laufen unverändert. Neu: Lückenschluss nach Löschen, gleichzeitiges Anlegen (MariaDB, `composer test:mariadb`).

---

## R2. Plan-Struktur an einer Stelle

**Heute** ist die Form eines Plans (Milestone → ToDo → Task) **viermal** beschrieben, in zwei Schreibweisen:

| Ort | Schreibweise | Inhalt |
|---|---|---|
| `graphql/plan.graphql` | camelCase | Typen und Inputs |
| `ProjectPlanAssistant::schema()` | camelCase | JSON-Schema für Claude (Structured Output) |
| `CreateProjectPlanTool`, `AddToPlanTool` (`schema()`) | snake_case | MCP-Schema |
| `ProjectPlanImporter` (phpstan-Typen) + `ReadsProjectPlanInput` | snake_case | Eingabe des Importers, Umwandlung aus GraphQL |

Dazu kommen die Grenzwerte (Titel 160, Beschreibung 5000, höchstens 50 ToDos …), die in `plan.graphql` und in `ProjectPlanAssistant::clean()` getrennt stehen.

A4 bringt neue Felder (`id`, `position`, `status`, `labelNames`) und B1 die Verknüpfung mit einer Idee. Ohne Bündelung wären das vier Änderungen pro Feld, und die Schemas laufen auseinander. Das ist schon passiert: `status` je Task gibt es im MCP-Schema, aber nicht im Assistenten und nicht in GraphQL.

**Vorschlag:**
- Wertobjekte `App\Plan\PlanMilestone`, `PlanTodo`, `PlanTask` (readonly, mit `fromArray()` für beide Schreibweisen und `toArray()`).
- Eine Klasse `App\Plan\PlanSchema` erzeugt daraus das JSON-Schema für Claude und das MCP-Schema (`JsonSchema`-Builder von `laravel/mcp`). Die Grenzwerte stehen als Konstanten dort.
- `ProjectPlanImporter` nimmt nur noch Wertobjekte an. `ReadsProjectPlanInput` und `ProjectPlanAssistant::clean()` bauen Wertobjekte.
- `plan.graphql` bleibt die GraphQL-Sicht. Ein Test vergleicht die Felder der GraphQL-Inputs mit `PlanSchema`, damit beide nicht auseinanderlaufen.

**Gewinn:** A4 ist danach im Wesentlichen „Felder in den Wertobjekten ergänzen“. Assistent und Connector verhalten sich gleich.

---

## R3. GraphQL-Operationen im PHP-Code bündeln und prüfen

**Heute** stehen **13 GraphQL-Operationen als Strings** in 8 PHP-Dateien (Importer, `TaskStatusChange`, sechs MCP-Werkzeuge). Das ist gewollt (gleiche Rechte wie ein Klick in der App) und soll so bleiben. Aber: Für diese Strings gibt es keine Codegen-Prüfung wie im Frontend. Eine Umbenennung wie `Task.position → boardPosition` (A3) oder ein neuer Rückgabetyp (C1) bricht sie still. Erst ein Test, der genau diesen Pfad nutzt, würde es merken.

**Vorschlag:**
- Alle Operationen nach `backend/graphql/operations/*.graphql` verschieben (eine Datei je Operation, benannt: `mutation CreateTodo(...)`).
- Ein kleiner Loader (`GraphQLRunner::runNamed('CreateTodo', $vars)`) liest sie.
- Ein Test validiert jede Datei gegen das Schema (`GraphQL\Validator\DocumentValidator` mit dem Lighthouse-Schema). Damit fällt jeder Breaking Change sofort im Test auf, für alle Operationen auf einmal.

Aufwand klein, Nutzen bei jedem weiteren Schritt.

---

## R4. Ein Lösch-Pfad statt sechs `@delete`

**Heute** löschen `deleteProject`, `deleteMilestone`, `deleteTodo`, `deleteTask`, `deleteComment` und `deleteUser` per Lighthouse-Direktive `@delete`. Die Kinder verschwinden über `cascadeOnDelete` in der Datenbank, die Observer rechnen den Status nach.

Für den Papierkorb (A5) reicht `SoftDeletes` allein nicht: Die Kinder müssen dieselbe `deletion_id` bekommen, `deleted_by` gesetzt werden, die Reihenfolge geschlossen werden (R1), und das Frontend braucht die `deletionId` zurück.

**Vorschlag, schon jetzt ohne Verhaltensänderung:**
- Service `App\Services\Deletion` mit `delete(Model $model, User $by)`. Er löscht heute noch endgültig, in einer Transaktion, und schließt über `Ordering::close` die Lücke (behebt den Lückenfehler aus R1 gleich mit).
- Die fünf Projekt-Mutationen nutzen einen gemeinsamen Resolver `DeleteItem`, der `Deletion` aufruft. `deleteUser` bleibt bei `@delete` (Benutzer sind vom Papierkorb ausgenommen).
- A5 ändert danach nur `Deletion` (weich löschen, Kinder markieren) und ergänzt `restore()` und `purge()`.

**Frontend:** `useConfirmedDelete` wird an 6 Stellen genutzt. Mit dem Papierkorb entfällt die Bestätigung bei Milestones, ToDos, Tasks und Kommentaren zugunsten eines Toasts mit „Rückgängig“. Vorschlag: jetzt schon ein `useDelete` schreiben, das intern noch bestätigt. Beim Umbau ändert sich dann nur dieses Composable.

---

## R5. Kleinere Vorbereitungen im Backend

- **`owningProject()` (18 Aufrufe):** bleibt als Schnittstelle. Mit A1 wird es bei Todo und Task zu `$this->project`. Vorher nichts zu tun, nur nicht an neuen Stellen die Kette `todo.milestone.project` direkt verwenden.
- **`@with(relation: "task.todo.milestone.project.members")`** an `Comment.viewerCanEdit/Delete` nach A1 auf `task.project.members` kürzen. Kein Vorab-Refactoring nötig.
- **Archiv-Prüfung:** Alle Projektfähigkeiten sind Schreibrechte. Das Archiv lässt sich deshalb als eine Zeile in `ProjectPermissions::allows` umsetzen („archiviert → nichts erlaubt“). Kein Vorab-Refactoring nötig. Ausnahme bewusst festlegen: `project.archive` (zum Zurückholen) und Wiederherstellen aus dem Papierkorb.
- **Eingabe-Ableitung:** `project_id` (A1) und `created_by` (A2) im Model-Hook setzen, nicht in den Resolvern. Dafür ein Trait `DerivesProject` (setzt `project_id` aus dem Elternteil) und `RecordsCreator` (setzt `created_by` aus `Auth::user()`). So greifen sie auch bei `@create`, im Importer und in den Factories.

## R6. Kleinere Vorbereitungen im Frontend

- **Refetch-Strategie:** zentral über `projectContentQueries = 'active'` in `graphql/project.ts`, 17 Verwendungen. Für C1 später nur dort umstellen. Kein Vorab-Refactoring nötig.
- **Kanban:** `kanban.vue` liest `task.position` an fünf Stellen. Nach dem Rename meldet der Typecheck die Stellen von selbst (Codegen). Kein Vorab-Refactoring nötig.
- **Zeitachse:** sortiert ToDos im Frontend nach Fälligkeit (`Timeline.vue`, Zeile 64). Mit A3 entfällt die Sortierung, die Reihenfolge kommt vom Server. Bis dahin unverändert lassen.

## R7. Tests

- `OutsiderAccessTest` und `MutationTest` enthalten Listen aller Mutationen. Vorschlag: zusätzlich ein Test, der aus dem Schema alle Mutationen liest und prüft, dass jede in diesen Listen steht. Dann fehlt bei den vielen neuen Mutationen (Archiv, Papierkorb, Ideen, KI-Kontext) keine.
- Für das Archiv eine Liste „Mutationen, die in archivierten Projekten verboten sind“, ebenfalls gegen das Schema geprüft.

---

## Offene Schritte (ab `347a4d5`)

Erledigt sind R1, R3, R4 (Backend) und R7 (bis auf die Ausnahme oben). Offen:

1. **R2 (Rest)** erst nach P1 und P2 in `mollo-version` (JSON-Schema `planer/2`, Validator). Dann Wertobjekte bauen und den Importer umstellen. Vorher nicht anfassen, sonst entsteht eine zweite Vorlage.
2. **R4 (Frontend)** `useDelete` als Composable, zunächst mit Bestätigung. Unabhängig vom Rest, kann jederzeit passieren.
3. **R7 (Rest)** Liste „Mutationen in archivierten Projekten verboten“, zusammen mit A5.
4. **R5** `DerivesProject` und `RecordsCreator`, zusammen mit dem Migrationsschritt A1 und A2.
5. Danach die Umsetzung laut Schema-Review, Reihenfolge „Reihenfolge der Umsetzung“ in `docs/schema-review.md`, beginnend mit der Migration (A1, A2, A3, A5, A6).

Jeder Schritt ist ein eigener Commit auf `dev` mit grünen Tests (SQLite und `composer test:mariadb`). R2 kann direkt vor A4 erfolgen, falls die Datenstruktur zuerst kommen soll.

## Gefundene Fehler nebenbei

- **Milestone-Reihenfolge ohne Sperre:** Gleichzeitiges Anlegen oder Verschieben konnte doppelte Positionen erzeugen. Behoben mit R1 (`e003483`), das Projekt wird jetzt gesperrt.
- **Lücken nach dem Löschen:** `milestones.position` wurde nach `deleteMilestone` nicht neu nummeriert. Harmlos für die Anzeige, aber `moveMilestone(position: n)` landet an einer unerwarteten Stelle, wenn Clients mit `position` statt mit dem Index rechnen (z. B. der Connector). Behoben mit R1 und R4 (`e003483`, `8f20f44`): `Ordering::close()` schließt die Lücke.
- **Uneinheitliche Plan-Felder:** Der Connector kann Tasks als erledigt anlegen, der Assistent nicht. Wird mit R2 behoben.
- **Connector-Plan:** `docs/connector-erweiterung-plan.md` ist eingecheckt, die Funktion ist mit `9d11daa` gebaut. Ob `docs/claude-connector.md` den aktuellen Stand abbildet, ist nicht geprüft.
