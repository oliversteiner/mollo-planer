# Auftrag Meilenstein M2 „Sicher löschen“ (S5, S6)

Stand: 2026-11-17, Planung. Geprüft: `mollo-planer` bis `065a2be` (nach Abnahme M1 mit F1 bis F4).
Rolle: Auftrag für die Umsetzung (Bob). Präzisiert `umsetzung.md` S5 und S6 auf den heutigen Code. Wo sich dieses Dokument und `schema-review.md` A5 widersprechen, gilt dieses Dokument, bei Rechten `rollen-funktionen-rechte.md`.

**M1 ist abgenommen (2026-11-17).** Der Mail-Test aus M1 (Abnahme 4.5) ist auf später verschoben und kein Hindernis.

---

## 0. Was schon da ist (nicht neu bauen)

- Spalten aus S3/A5: `projects.archived_at`; `deleted_at`, `deleted_by`, `deletion_id` an `projects`, `milestones`, `todos`, `tasks`, `comments` (Migration `2026_11_13_140000`), noch ohne Logik.
- `App\Services\Deletion::delete()` ist der einzige Löschweg (über `DeleteItem`), heute endgültig.
- `useDelete` im Frontend ist der einzige Löschweg der Oberfläche, heute mit Bestätigung.
- `ArchivedProjectMutationsTest`: Liste der Mutationen, die in archivierten Projekten verboten sind (aus S1).
- `RightUsageTest`: `project.archive`, `trash.restore`, `trash.purge` stehen als „ausstehend S5“. Nach M2 sind sie geprüft und raus aus `PENDING`.
- `todos.project_id` und `tasks.project_id` (A1): Abfragen auf den Papierkorb gehen direkt über `project_id`.
- `planer_revision` und das Neuladen (S3c, S3f) aus M1.

## 0a. Zuerst: Vorarbeiten und Folgen aus M1

1. **RF6** (`docs/refactoring-m1-m3.md`): `GraphQL/Resolvers/Progress.php` und `ProgressBatchLoader` über `tasks.project_id`/`todos.project_id` statt Joins. Eigener Commit vor S5.
2. **Lebenszyklus (RF1):** Die Observer von Milestone, ToDo, Task (und Project) bekommen mit `SoftDeletes` drei Fälle: `deleted` nur wenn nicht `isForceDeleting()` (Lücke schließen, Status neu, `PlanerRevision::touch`), `restored` (ans Ende, Status neu, `touch`), `forceDeleted` (nichts an Reihenfolge, kein `touch`). Kommentar „tasks are removed by the database (cascade)“ im `TodoObserver` anpassen.
3. **Grundregel §7.3 (F3.5) und Papierkorb:**
   - Projekte im Papierkorb zählen bei `Rights::assertNoProjectLosesHolder` nicht mit (sonst blockiert ein gelöschtes Projekt das Ändern einer Person).
   - Wiederherstellen eines Projekts: Hat es dann keine Person mit „Projekt löschen“ unter den Mitgliedern (z. B. weil die Owner inzwischen gelöscht oder auf „Lesend“ gesetzt wurden), wird die wiederherstellende Person Mitglied mit der Ersteller-Rolle. Test dafür.
   - Endgültig löschen eines Projekts ist von §7.3 nicht betroffen.
4. **Rechte je Element (F4.5):** Zu `Task.viewerCanUpdate`/`viewerCanDelete` usw. kommen keine neuen Felder für den Papierkorb am Task; der Papierkorb hat `TrashItem.viewerCanRestore`/`viewerCanPurge`. Im archivierten Projekt liefern alle `viewerCan*` über die Obergrenze in `Rights` automatisch `false`; Test dafür (Task-Felder im archivierten Projekt).
5. **Aktualisieren (RF4):** Rückgängig und Wiederherstellen laufen über `useProjectMutation`/`refreshProject`; der eigene Vorgang löst keinen Hinweis „inzwischen geändert“ aus (S3f).
6. **Listen mit Rollen (Apollo):** Wie bei Rollen nach jeder Änderung den Cache der betroffenen Abfragen leeren (`cache.evict`), z. B. Projektliste nach Archivieren/Zurückholen und nach Löschen/Wiederherstellen eines Projekts (Seitenleiste).

## 1. Archiv (S5 Teil 1)

1. **Obergrenze in `Rights`**, nicht in den Resolvern: Ist das Projekt archiviert, liefert `Rights::allows(…, $project)` nur Leserechte, dazu `project.archive` (zurückholen) und `trash.restore` (Grundregel §7.7). `Rights::rightsOf` und damit `Project.viewerRights` zeigen dasselbe, die Oberfläche blendet Bearbeiten dadurch von selbst aus. Gilt auch für `projects.manageAny`.
2. Mutationen `archiveProject(id)`, `unarchiveProject(id)` (Recht `project.archive`), Rückgabe `Project`. Feld `Project.archivedAt`.
3. Filter `projects(archived: Boolean = false)`. Archivierte Projekte fehlen in Dashboard, Seitenleiste, `myTasks`, Zuweisungs- und Fälligkeits-Mails (`TaskNotifier`).
4. `ArchivedProjectMutationsTest` prüft jetzt das Verhalten: jede Mutation der Liste wird in einem archivierten Projekt abgelehnt; die Liste deckt sich weiter mit dem Schema.
5. Archivieren und Zurückholen erhöhen `planer_revision` nicht (nicht im Format). Kommentare im archivierten Projekt: abgelehnt.
6. Frontend: Knopf „Archivieren“ / „Zurückholen“ in den Projekteinstellungen (mit Bestätigung beim Archivieren), Bereich „Archiv“ in der Projektliste (eingeklappt), Hinweisleiste „Archiviert, nur lesbar“ im Projekt.
7. MCP: Schreibwerkzeuge lehnen archivierte Projekte mit klarer Meldung ab; `list_projects` mit optionalem `archived`.

## 2. Papierkorb, Backend (S5 Teil 2)

1. `SoftDeletes` an `Project`, `Milestone`, `Todo`, `Task`, `Comment`. Ideen kommen mit B6 (`TrashItemType.IDEA` erst dann).
2. **`Deletion`** wird der Papierkorb:
   - `delete(Model)`: eine neue `deletion_id` (UUID) und `deleted_by` für das Element **und alle Kinder, die noch nicht im Papierkorb liegen** (Milestone → ToDos → Tasks → Kommentare; Projekt → alles). Kinder, die schon vorher einzeln gelöscht wurden, behalten ihre eigene `deletion_id`.
   - `restore(deletionId)`: holt genau diesen Satz zurück. Liegt das Elternteil im Papierkorb, Ablehnung mit Meldung, die das Elternteil nennt.
   - `purge(deletionId)`: endgültig, über die bestehenden Kaskaden der Datenbank (`forceDelete`).
   - `emptyProjectTrash(projectId)`: alle Vorgänge eines Projekts endgültig, Rückgabe Anzahl.
   - Alles in einer Transaktion, `planer_revision` +1 bei Löschen und Wiederherstellen (genau einmal je Vorgang, über den Mechanismus aus S3c). **Endgültig löschen zählt nicht** (die Datei ändert sich dadurch nicht).
3. **Reihenfolge und Status:** `Ordering::close` und `KanbanOrdering` beim Löschen (Lücke schließen), beim Wiederherstellen ans Ende von Liste bzw. Spalte. `ItemStatusSync` rechnet nach Löschen und Wiederherstellen neu; gelöschte Tasks zählen nirgends.
4. **Alle Abfragen ohne Gelöschtes:** Die globale Scope von `SoftDeletes` reicht für Eloquent. Zusätzlich jede Stelle mit `DB::table`, `join`, `withCount`/Aggregaten und Unterabfragen prüfen (u. a. `Kanban`, `VisibleToViewer`, `CommentViewerCan`, Dashboard, `myTasks`, Fortschritt, `ProjectPlanReader`, MCP). Test: ein gelöschter Task taucht in keiner Abfrage auf.
5. Keine Mails beim Wiederherstellen. Mitgliedschaften bleiben beim Projekt-Papierkorb erhalten. Labels (`label_task`) bleiben am gelöschten Task.
6. **Rechte** (`rollen-funktionen-rechte.md` hat Vorrang vor `schema-review.md`):
   - Löschen wie heute (`milestone.delete`, `todo.delete`, `task.delete`, `project.delete`; Kommentare: Autor oder `comment.moderate`).
   - Wiederherstellen: `trash.restore`; Kommentare: Autor oder `comment.moderate`; gelöschtes Projekt: wer im Projekt `project.delete` hat, oder `projects.manageAny`.
   - Endgültig löschen und „Papierkorb leeren“: `trash.purge`; gelöschtes Projekt endgültig: `trash.purge` im Projekt oder `projects.manageAny`.
   - Die Rechte eines gelöschten Projekts liest `Rights` aus der Mitgliedschaft wie sonst (das Projekt muss dafür mit `withTrashed` geladen werden).

## 3. Papierkorb, GraphQL

```graphql
enum TrashItemType { PROJECT MILESTONE TODO TASK COMMENT }

type TrashItem {
  deletionId: ID!
  type: TrashItemType!
  id: ID!
  title: String!            # Kommentare: Auszug, höchstens 80 Zeichen Klartext
  project: Project          # auch wenn gelöscht
  deletedBy: User
  deletedAt: DateTime!
  childCount: Int!          # weitere Elemente im selben Vorgang
  viewerCanRestore: Boolean!
  viewerCanPurge: Boolean!
}

trash(projectId: ID): [TrashItem!]!      # ohne projectId: gelöschte Projekte, die die Person wiederherstellen darf
restoreFromTrash(deletionId: ID!): TrashItem!
purgeFromTrash(deletionId: ID!): Boolean!
emptyProjectTrash(projectId: ID!): Int!
archiveProject(id: ID!): Project!
unarchiveProject(id: ID!): Project!
```

- Je Vorgang **ein** `TrashItem` (das oberste Element), sortiert nach `deletedAt` aufsteigend (älteste zuerst, ohne Frist wächst die Liste).
- `delete*` (Projekt, Milestone, ToDo, Task, Kommentar) bleiben, geben das Element zurück, neues Feld `deletionId: ID` an diesen Typen (sonst `null`).
- `MutationCoverageTest` und `ArchivedProjectMutationsTest` um die neuen Mutationen ergänzen (`restoreFromTrash` ist im archivierten Projekt erlaubt, die anderen nicht).

## 4. Papierkorb, Frontend

1. `useDelete`: keine Bestätigung mehr, stattdessen Toast „Gelöscht“ mit Knopf „Rückgängig“ (ruft `restoreFromTrash` mit der `deletionId`), etwa 8 Sekunden, per Tastatur erreichbar. Gilt für Milestone, ToDo, Task, Kommentar. `useConfirmedDelete` bleibt nur für Projekt löschen und endgültig löschen.
2. Seite `/projects/:id/trash`: Liste der Vorgänge (Typ, Titel, „mit 3 ToDos, 12 Tasks“, gelöscht von, wann), Knöpfe „Wiederherstellen“ und „Endgültig löschen“ je nach `viewerCan*`, „Papierkorb leeren“ mit Bestätigung, die die Anzahl nennt. Ist das Elternteil gelöscht, zeigt der Fehler, welches Element zuerst zurück muss.
3. Seite `/trash`: gelöschte Projekte. Menüpunkt nur, wenn die Abfrage etwas liefert oder die Person `projects.manageAny` hat.
4. Eintrag „Papierkorb“ im Projektmenü, sichtbar mit `trash.restore` oder `trash.purge`.
5. Nach Wiederherstellen erscheint das Element ohne Neuladen der Seite (Cache aktualisieren); im zweiten Fenster über `planerRevision` (S3f).
6. Texte DE/EN, hell/dunkel, Telefonbreite, leerer Zustand („Der Papierkorb ist leer.“).
7. `frontend/app/utils/db-schema.ts`: Spalten `archived_at`, `deleted_at`, `deleted_by`, `deletion_id` beschreiben.

## 5. S6 Bereinigungen

- **C2:** `Project.description` als `RichText` (Migration auf JSON, Input, Editor in den Projekteinstellungen wie bei Milestones). Vorhandener Klartext wird in der Migration zu einem Absatz. `ProjectPlanInput.description` bleibt Klartext bis I3/B5a.
- **C3:**
  - `updateMyNotifications(notifyByEmail: Boolean!)` statt `emailEnabled` (Frontend anpassen).
  - `rejectUser` gibt wie `deleteUser` den `User` zurück (vor dem Löschen gelesen).
  - `MemberCandidate` von `inputs.graphql` nach `types.graphql`.
  - Kopfkommentar in `schema.graphql`: erledigt in R1 Stufe 3b, nur prüfen.

## 6. Tests (mindestens)

- Milestone löschen: Milestone, ToDos, Tasks, Kommentare tragen dieselbe `deletion_id`; ein vorher einzeln gelöschter Task behält seine eigene und kommt beim Wiederherstellen des Milestones **nicht** mit.
- Wiederherstellen in gelöschtes Elternteil wird abgelehnt.
- Reihenfolge: Lücke schließt sich, Wiederherstellen ans Ende (Milestone, ToDo, Kanban).
- `ItemStatusSync`: ToDo mit einem gelöschten offenen Task wird erledigt, nach Wiederherstellen wieder offen.
- `planer_revision`: Löschen +1, Wiederherstellen +1, endgültig löschen 0, Archivieren 0, Milestone mit Kindern löschen genau +1.
- Rechte: je Recht ein Test mit Standardrolle und einer eigenen Rolle (Muster `RightsGroundRulesTest`); Gast und Lesend können nichts davon.
- Archiv: Liste der verbotenen Mutationen, `projects(archived:)`, `myTasks`, keine Mails.
- Keine Mails beim Wiederherstellen.
- Gelöschtes taucht in keiner Abfrage auf (Dashboard, `myTasks`, Kanban, Zeitachse, Fortschritt, MCP).
- `RightUsageTest`: `PENDING` ist danach leer bis auf Rechte späterer Schritte.

## 7. Ablauf und Abschluss

1. Archiv Backend → Papierkorb Backend → GraphQL → Frontend → S6. Ein Commit je Teil, `umsetzung.md` „Stand“ nachführen.
2. Stopp und Rückfrage an die Planung, falls: eine Abfrage sich nicht ohne größeren Umbau auf „ohne Gelöschtes“ bringen lässt; die Obergrenze für archivierte Projekte nicht sauber in `Rights` passt; Web Awesome keinen zugänglichen Toast mit Knopf hergibt.
3. Am Ende: alle Prüfungen grün, `migrate:fresh --seed --seeder=DemoSeeder`, im Browser ansehen, dann anhalten für die Abnahme M2 (Checkliste schreibt die Planung).

## 8. Vorschläge der Planung (gelten, wenn Oliver nicht widerspricht)

- Endgültig löschen erhöht `planer_revision` nicht; Archivieren und Zurückholen auch nicht.
- Undo-Toast etwa 8 Sekunden; danach bleibt der Weg über die Papierkorb-Seite.
- `/trash` zeigt nur gelöschte Projekte, die die Person wiederherstellen darf.
