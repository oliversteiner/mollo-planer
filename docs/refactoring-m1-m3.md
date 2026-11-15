# Refactoring vor dem Rest von M1 bis M3

Stand: 2026-11-15, Planung. Geprüft: `mollo-planer` bis `9633da6`.
Rolle: Auftrag für die Umsetzung (Bob). Nur Umbauten, die die nächsten Schritte (S3b, S3c, S3f, S5, S3e, S7) einfacher oder sicherer machen. Verhalten bleibt gleich, alle Tests bleiben grün. Ein Commit je Punkt.

Ersetzt nicht `refactoring-vor-umsetzung.md` (historisch, erledigt).

---

## Übersicht

| Nr. | Umbau | Hilft bei | Wann | Aufwand |
|---|---|---|---|---|
| RF1 | Lebenszyklus von Milestone, ToDo, Task an einer Stelle (Observer) | S3c, S5 | vor S3c | klein |
| RF2 | Ein Dienst `PlanerRevision` als einzige Stelle für die Revision | S3c, S5, S7, W1 | mit S3c | klein |
| RF3 | Labels an Tasks nur über `TaskLabels` schreiben | S3c, S7 | vor S3c | sehr klein |
| RF4 | Ein Weg für Aktualisieren im Frontend (`useProjectMutation`) | S3f, S5, S7 | vor S3f | mittel |
| RF5 | Rechte für Seiten und Menü an einer Stelle | R1 Rest, S5, S7 | im R1 Rest | klein |
| RF6 | Fortschritt über `project_id` statt Joins | S5 | vor S5 | sehr klein |
| RF7 | Alte Enum-Parameter in `Rights` entfernen | Wartung, S5 | im R1 Rest | klein |
| RF8 | `App\Plan` → `App\Planer` jetzt statt in I1 | S3e, S7 | vor S3e | sehr klein |

Reihenfolge: RF7, RF5 (im R1 Rest) → RF1, RF3, RF2 (vor bzw. mit S3c) → RF4 (vor S3f) → RF6 (vor S5) → RF8 (vor S3e).

---

## RF1. Lebenszyklus an einer Stelle

**Heute:** Milestone und ToDo regeln Reihenfolge in `booted()`-Closures im Modell (`creating`, `deleted` → `Ordering`), der Status läuft über `TodoObserver`/`TaskObserver` (`ItemStatusSync`). Task hat einen Observer, Milestone keinen. Zwei Mechanismen für dieselbe Art Aufgabe.

**Problem für S5:** Mit `SoftDeletes` feuert `deleted` beim Löschen in den Papierkorb **und** beim endgültigen Löschen. Die Closure würde die Lücke zweimal schließen, und für `restored` gibt es noch keinen Ort.

**Umbau:**
- `MilestoneObserver` neu; die `booted()`-Closures von `Milestone` und `Todo` ziehen in `MilestoneObserver` bzw. `TodoObserver` (mit `#[ObservedBy]`).
- Je Observer klar getrennt: `creating` (Position), `created`/`updated`/`deleted` (Status). Für S5 sind dann nur `deleted` (nur wenn nicht `isForceDeleting()`), `restored` und `forceDeleted` zu ergänzen.
- Kommentar im `TodoObserver` („Its tasks are removed by the database (cascade), without events“) bleibt bis S5 richtig, danach nicht mehr: S5 passt ihn an.

## RF2. Dienst `PlanerRevision`

**Heute:** Es gibt noch keine Zählung (S3c offen). Schreibwege: Lighthouse-Direktiven `@create`/`@update`, eigene Mutationen, `Ordering`, `KanbanOrdering`, `ItemStatusSync` (schreibt leise), Pivot-Tabellen (`label_task`, `project_user`), Importer, MCP über GraphQL.

**Umbau (Grundlage für S3c):**
- `App\Planer\PlanerRevision` mit `touch(int $projectId)`: merkt sich die Projekte je Transaktion und erhöht beim Commit genau einmal je Projekt (`DB::afterCommit`); außerhalb einer Transaktion sofort.
- Aufgerufen aus den Observern von Project, Milestone, ToDo, Task (RF1, über einen kleinen Trait `TouchesPlanerRevision`), aus `TaskLabels` (RF3) und aus `ProjectMembership` (Mitglieder stehen in der Datei, `project.members`).
- `ItemStatusSync` schreibt Status leise; der abgeleitete Status ändert die Datei nicht für sich, ruft also **nicht** `touch` (die auslösende Änderung hat schon gezählt).
- Kommentare, Archiv, Vorlagen: kein `touch`.

**Ergänzung zu S3c:** Mitglieder (`project_user`: hinzufügen, Rolle ändern, entfernen) zählen auch, weil `project.members` in der Datei steht. Das fehlte in der Liste von S3c.

## RF3. Labels an Tasks nur über `TaskLabels`

**Heute:** `SetTaskLabels` und `CreateTask` rufen `$task->labels()->sync()` selbst auf; `TaskLabels` prüft nur (`validated`). Pivot-Änderungen lösen keine Modell-Events aus.

**Umbau:** `TaskLabels::set(Task $task, array $labelIds)` prüft und schreibt; beide Mutationen nutzen nur diese Methode. Damit hat RF2 eine Stelle für die Revision, und S7 (Labels je Projekt, `key`) ändert nur diesen Dienst.

## RF4. Ein Weg für Aktualisieren im Frontend

**Heute:** Mutationen aktualisieren auf drei Arten: `refetchQueries: 'active'`, `projectContentQueries` (heißt nur so, ist auch `'active'`), eigenes `refetch()` (Timeline, Kanban, Benutzer, Profil). Der Projektkopf (`projectHeaderQuery`, mit `viewerRights`) wird nicht gezielt aktualisiert.

**Problem für S3f:** Das Polling vergleicht `planerRevision` mit dem bekannten Stand. Nach einer **eigenen** Änderung muss der bekannte Stand sofort steigen, sonst erscheint der Hinweis „Das Projekt wurde inzwischen geändert“ für die eigene Änderung. Für S5 (Rückgängig, Wiederherstellen) und S7 (Labels) braucht es dasselbe Aktualisieren.

**Umbau:**
- Composable `useProjectMutation(mutation)` (bzw. eine Funktion `refreshProject(projectId)`): nach Erfolg die Inhalte des Projekts **und** den Projektkopf mit `planerRevision` neu laden. Alle Mutationen innerhalb eines Projekts nutzen es (Timeline, Kanban, ToDos, Task-Formular, Task-Detail, Kommentare, Mitglieder, Einstellungen, Projekt-Labels).
- `projectContentQueries` entfällt; Mutationen außerhalb eines Projekts (Benutzer, Rollen, Profil) bleiben, wie sie sind.
- S3f baut das Polling dann in dieselbe Stelle ein: eigener Aufruf setzt den bekannten Stand, fremde Änderung zeigt den Hinweis und ruft dasselbe `refreshProject`.

## RF5. Rechte für Seiten und Menü an einer Stelle

**Heute:** je Recht eine Middleware (`admin.ts` = `USERS_MANAGE`, `roles.ts` = `ROLES_MANAGE`), das Menü in `layouts/default.vue` prüft die Rechte noch einmal von Hand. Seite und Menü können auseinanderlaufen (z. B. `/system/rechte`, `/admin/settings` und `/assistant` hängen an `USERS_MANAGE`, obwohl es `roles.manage`, `settings.manage` bzw. später „alle“ sein soll).

**Umbau (gehört zum offenen R1-Punkt „Menü und Seiten-Middleware nach `viewerRights`“):**
- `definePageMeta({ right: 'ROLES_MANAGE' })` bzw. `projectRight: 'TRASH_RESTORE'` für Projektseiten; die globale Middleware prüft beides. `admin.ts` und `roles.ts` entfallen.
- Das Menü liest die Rechte aus derselben Liste (Route → Recht) statt eigener Bedingungen.
- Dabei richtig zuordnen: `/system/rechte` → `roles.manage`, `/admin/settings` → `settings.manage`, `/system/db` → `users.manage` (unverändert, bis es ein eigenes Recht gibt), `/assistant` bleibt bis B5a.
- S5 (`/projects/:id/trash`, `/trash`) und S7 (`/templates`) tragen dann nur noch ihr Recht ein.

## RF6. Fortschritt über `project_id`

**Heute:** `GraphQL/Resolvers/Progress.php` verbindet `tasks` mit `todos` und `milestones` per `join`. Seit A1 haben `tasks` und `todos` eine `project_id`.

**Umbau:** Abfragen über `tasks.project_id` bzw. `todos.project_id`, ohne Joins. Weniger Stellen, die S5 auf „ohne Gelöschtes“ prüfen muss (bei Joins greift die Scope von `SoftDeletes` nur für die Haupttabelle). `ProgressBatchLoader` gleich prüfen.

## RF7. Alte Enum-Parameter in `Rights` entfernen

**Heute:** Seit R1 Stufe 3b Teil 4 sind Rollen Daten (`Role`). `Rights` nimmt an mehreren Stellen noch `Role|ProjectRole|UserRole|null` an (`systemRightsOf`, `projectRoleAllows`, `projectRightsOfRole`, `canChangeMembership`, `isOwnershipRole`, `holdsProject`). Außerhalb von `Rights` nutzen nur `UserInvitation` und `CreateAdmin` die Enums.

**Umbau:** Nur noch `Role` annehmen; `UserInvitation` und `CreateAdmin` holen die Rolle über `Role` (Schlüssel der mitgelieferten Rolle). Die Enums `UserRole`/`ProjectRole` bleiben als Liste der mitgelieferten Schlüssel für Seeder, Factories und Tests. Weniger Fälle, wenn S5 die Archiv-Obergrenze in `Rights` einbaut.

## RF8. Namensraum `App\Planer` jetzt

**Heute:** `App\Plan\PlanSchema` (eine Datei). S3e legt `App\Planer\RichText` an, I1 sollte `App\Plan` verschieben.

**Umbau:** `PlanSchema` nach `App\Planer` verschieben, bevor S3e beginnt. Dann gibt es nie zwei Namensräume nebeneinander. Die Anpassung von I1 in `umsetzung.md` („`App\Plan` verschieben“) ist damit erledigt.

---

## Bewusst nicht umbauen

Diese Teile werden in einem späteren Schritt ersetzt; ein Umbau jetzt wäre verlorene Arbeit:

- `ProjectPlanImporter`, `ProjectPlanReader`, `ProjectPlanAssistant`, `ReadsProjectPlanInput`, `DraftProjectPlan`, `ImportProjectPlan` → I2, I3, B5a.
- MCP-Werkzeuge außer den kleinen Anpassungen aus S5 → I3 (reduziert auf wenige Werkzeuge). Gut: Sie laufen schon über GraphQL (`RunsGraphQL`), Rechte und Archiv greifen dort von selbst.
- Label-Sets (`SaveLabelSet`, `DuplicateLabelSet`, `DeleteLabelSet`, `SetProjectLabelSet`, `BuiltinLabelSets`, `/label-sets`, `components/label-set/`) → S7.
- `DevelopmentHistory` (Seed im v1-Format) → I4.
- Große Komponenten (`Timeline.vue` 661 Zeilen, `PlanPreview.vue`): der Großteil ist Markup, die Logik ist überschaubar. `PlanPreview` wird mit B5a ersetzt. Timeline erst teilen, wenn S5/S7 dort spürbar wachsen.

Schon gut gelöst, nichts zu tun: Löschen läuft über eine Stelle (`DeleteItem` → `Deletion`, `useDelete`), `DerivesProject` und `RecordsCreator` als Traits, Rechte nur über `Rights`.

## Nachricht an Bob

```
Vor dem Rest von M1 bitte docs/refactoring-m1-m3.md lesen und einbauen:
- Im R1-Rest: RF7 (Enum-Parameter aus Rights), RF5 (Rechte für Seiten und Menü an einer Stelle).
- Vor bzw. mit S3c: RF1 (Observer für Milestone/ToDo/Task), RF3 (Labels nur über TaskLabels),
  RF2 (Dienst PlanerRevision; Mitglieder zählen auch).
- Vor S3f: RF4 (useProjectMutation / refreshProject).
- RF6 vor S5 und RF8 vor S3e können warten, gehören zu M2/M3.
Verhalten unverändert, alle Prüfungen grün, ein Commit je Punkt, Stand in umsetzung.md nachführen.
```
