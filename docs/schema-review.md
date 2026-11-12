# Schema-Review: Optimierungspotenzial

Stand: 2026-11-12. Geprüft: `backend/graphql/*.graphql`, Migrationen, Models, Observer, `ProjectPermissions`, `KanbanOrdering`, `ItemStatusSync`, MCP-Werkzeuge, Frontend-Abfragen (Stand Commit `d736d6a`).
Rahmen: Breaking Changes sind erlaubt, bestehende Daten müssen nicht erhalten bleiben (Entscheidung vom 2026-11-12).
Status: alle Fragen entschieden, bereit für die Umsetzung. Vorher die Refactorings aus `docs/refactoring-vor-umsetzung.md` umsetzen.
Fachliches Konzept zu Assistent, Ideen und KI-Kontext: `docs/assistent-ideen-konzept.md`.
Planer-Dateiformat und User-Story: `docs/planer-datei-ssot.md`, Abschnitt 4.4.

---

## Entscheidungen (2026-11-12)

- **Eigener Task** = von mir angelegt (`created_by`), nicht „mir zugewiesen“.
- **Selbst zuweisen:** Ein Mitglied darf einen selbst angelegten Task sich selbst zuweisen, aber niemand anderem.
- **Rolle `lesend`** darf den Assistenten nicht nutzen und keine Ideen anlegen.
- **Archivieren** von Projekten: Owner und Manager (und Admins).
- **Papierkorb statt Löschen** für Projekte, Milestones, ToDos, Tasks, Kommentare und Ideen. **Ausgenommen:** Benutzer und Label-Sets.
- **Keine Frist:** Elemente bleiben im Papierkorb, bis jemand sie endgültig löscht. Kein automatisches Löschen.
- **Endgültig löschen:** nur Owner und Admins.
- **KI-Kontext lesen:** alle Projektmitglieder (Variante A).
- **User-Story am Task:** neues Feld, siehe A6.

---

## Kurzfazit

Das Schema ist sauber aufgebaut: klare Rechte über zwei Tabellen, berechneter Status, RichText-Whitelist, Policies per `@can`. Die größten Hebel liegen nicht bei den Typen, sondern bei der **Datenstruktur dahinter**:

1. Tasks und ToDos kennen ihr Projekt nur über zwei bzw. eine Zwischenstufe. Das macht Sichtbarkeit, Kanban, Sperren und Rechteprüfung teurer und umständlicher als nötig.
2. Es fehlt `created_by` an Tasks. Ohne das lässt sich die Regel „Mitglieder ändern eigene Tasks“ nicht umsetzen.
3. ToDos haben keine Reihenfolge im Milestone. Für KI und Connector („an die richtige Stelle einordnen“) fehlt das.
4. Die Plan-Typen (`ProjectPlan*`) können nur neue Projekte beschreiben. Für den Assistenten in bestehenden Projekten braucht es Verweise auf vorhandene Milestones und ToDos.
5. Löschen ist heute endgültig (Kaskade in der Datenbank). Neu: Archiv für Projekte, Papierkorb für alles andere.

---

## A. Empfohlene Änderungen (hohe Priorität)

### A1. `project_id` direkt an `todos` und `tasks`

**Heute:** `Task → Todo → Milestone → Project`. Folgen:
- `visibleToViewer` für Tasks läuft über `whereHas('todo.milestone.project')`, also verschachtelte Unterabfragen.
- `Task::scopeInProject` braucht einen Join über `todos` und `milestones`. Er steckt in Kanban, `KanbanOrdering::nextPosition`, `move` und im Fortschritt.
- `Comment.viewerCanEdit/viewerCanDelete` laden `task.todo.milestone.project.members` je Kommentar.
- `owningProject()` lädt die Kette Stufe für Stufe.

**Vorschlag:** Spalte `project_id` (Fremdschlüssel, `cascadeOnDelete`, Index) an `todos` und `tasks`, dazu Index `tasks(project_id, status, board_position)` für das Kanban.
- Der Wert kommt **nie aus der Eingabe**. Ein `creating`/`updating`-Hook im Model leitet ihn aus dem Elternteil ab. `moveTaskToTodo` prüft schon heute „gleiches Projekt“, das bleibt.
- `projectPath()` wird für Todo und Task zu `project`, `scopeInProject` zu `where('project_id', …)`.
- GraphQL: `Task.project: Project!` und `Todo.project: Project!` als Feld ergänzen.

**Gewinn:** einfachere und schnellere Abfragen, weniger Code in `KanbanOrdering`, `Kanban`, `VisibleToViewer`, `CommentViewerCan`. Auch der Papierkorb (A5) wird damit deutlich einfacher. Ein Test stellt sicher, dass `project_id` immer zum Elternteil passt.

### A2. `created_by` an Tasks (und ToDos, Milestones)

- Spalte `created_by` (nullable, `nullOnDelete`) an `tasks`, `todos`, `milestones`. Gesetzt aus dem Login, nie aus der Eingabe (wie beim Kommentar-Autor).
- GraphQL: `Task.createdBy: User`, gleich bei Todo und Milestone.
- Rechte, neue Projektfähigkeiten in `ProjectPermissions`:
  - `task.updateOwn` (Owner, Manager, Mitglied): Ändern, wenn `created_by` = Person.
  - `task.assignSelf` (Owner, Manager, Mitglied): eigenen Task sich selbst zuweisen oder die Zuweisung an sich selbst aufheben.
- Die Policies erlauben `update` bei `task.update` **oder** (`task.updateOwn` und eigener Task), `assign` bei `task.assign` **oder** (`task.assignSelf`, eigener Task und Ziel = Person selbst bzw. leer). Die Regeln bleiben in der Rechte-Tabelle.
- `task.create` bekommt zusätzlich die Rolle Mitglied.
- Beim Anlegen (`createTask`, Importer) gilt dieselbe Prüfung für `assigneeId`.

### A3. Reihenfolge der ToDos im Milestone

**Heute:** `Milestone.todos` hat keine Sortierung, die Zeitachse sortiert im Frontend nach Fälligkeit und dann ID.

- `todos.position` (pro Milestone, ab 0), Index `(milestone_id, position)`, Relation sortiert nach `position, id`.
- Mutation `moveTodo(id, milestoneId, position)`: ersetzt zugleich das Umhängen eines ToDos in einen anderen Milestone. Der Observer behandelt `milestone_id`-Wechsel schon heute.
- `CreateTodoInput.position` optional, ohne Angabe ans Ende (wie bei Milestones).
- **Breaking:** `Task.position` (Kanban-Spalte) wird `boardPosition`. Damit ist `position` überall „Reihenfolge im Elternteil“.

### A4. Plan-Typen für bestehende Projekte öffnen

Ein gemeinsamer Entwurf für Assistent und Connector:
- `ProjectPlanMilestone.id: ID` und `ProjectPlanTodo.id: ID`: gesetzt = bestehendes Element, Inhalt wird angehängt; leer = neu.
- `ProjectPlanTask.status: TaskStatus` (Standard OPEN), wie im Connector.
- `ProjectPlanMilestone.position: Int` und `ProjectPlanTodo.position: Int` (nach A3).
- Neue Mutation `importPlanIntoProject(projectId, plan, ideaId?)`: alles oder nichts, prüft jede Stufe mit den Projektrechten, nutzt `ProjectPlanImporter::addPlan/addToMilestone/addToTodo`, die es schon gibt.
- `draftProjectPlan` bekommt optional `projectId`. Dann kennt Claude die bestehenden Milestones, ToDos, Mitglieder, das Label-Set und den KI-Kontext.
- `ProjectPlanTask.labelNames: [String!]` mit Namen aus dem Label-Set des Projekts (der Importer übersetzt sie in IDs).

**Gewinn:** Assistent und Connector nutzen denselben Importer. Zusätzliche MCP-Werkzeuge zum Anlegen von ToDos und Tasks braucht es nicht, `add_to_plan` kann das schon.

### A5. Archiv für Projekte, Papierkorb für alles andere

#### Archiv (Projekte)

- Spalte `projects.archived_at` (nullable).
- Ein archiviertes Projekt ist **nur lesbar**: keine Änderungen, keine Kommentare, keine Mails. Zentral geprüft in `ProjectPermissions::allows` (archiviert → nur Lesen).
- Es verschwindet aus Dashboard, Seitenleiste, `myTasks` und Projektliste. Filter `projects(archived: Boolean = false)`.
- Mutationen `archiveProject(id)` und `unarchiveProject(id)`. Neue Projektfähigkeit `project.archive`: Owner und Manager (Admins immer).
- MCP: `list_projects` zeigt archivierte Projekte nur auf Wunsch, Schreibwerkzeuge lehnen sie ab.

#### Papierkorb

**Gilt für:** Projekte, Milestones, ToDos, Tasks, Kommentare, Ideen.
**Ausgenommen (entschieden):** Benutzer und Label-Sets. Dort bleibt das heutige Löschen.

**Technik:**
- Laravel `SoftDeletes`: Spalte `deleted_at` an `projects`, `milestones`, `todos`, `tasks`, `comments`, `ideas`.
- Zusätzlich `deleted_by` (wer) und `deletion_id` (UUID). Wird ein Milestone gelöscht, bekommen Milestone, seine ToDos, deren Tasks und Kommentare **dieselbe** `deletion_id`. Wiederherstellen holt genau diesen Satz zurück, nicht Elemente, die schon vorher einzeln im Papierkorb lagen.
- Status und Fortschritt: `ItemStatusSync` rechnet nach Löschen und Wiederherstellen neu (Observer auf `deleted` und `restored`). Gelöschte Tasks zählen nicht.
- Kanban: Beim Löschen rücken die übrigen Tasks nach. Beim Wiederherstellen kommt der Task ans Ende seiner Spalte. Gleiches für `position` bei Milestones und ToDos.
- Wiederherstellen in ein gelöschtes Elternteil geht nicht. Dann zuerst das Elternteil wiederherstellen (die Oberfläche bietet das an).
- Mitgliedschaften (`project_user`) bleiben beim Projekt-Papierkorb erhalten.
- Wird eine Person aus dem Projekt entfernt, bleiben ihre gelöschten Tasks unzugewiesen, wie bei aktiven Tasks.
- Mails: Beim Wiederherstellen werden keine Mails verschickt.
- **Keine Frist:** Nichts wird automatisch gelöscht. Endgültig löschen geht nur manuell, einzeln oder „Papierkorb leeren“ pro Projekt. Kein Scheduler-Job nötig.
- Hinweis: Ohne Frist wächst der Papierkorb. Die Seite zeigt deshalb das Löschdatum und sortiert die ältesten zuerst sichtbar an.
- Avatare und Dateien: betroffen ist nur der KI-Kontext, und dort speichern wir keine Datei (B2).

**GraphQL (Entwurf):**

```graphql
"Ein Element im Papierkorb, gleich welcher Art"
type TrashItem {
  deletionId: ID!
  type: TrashItemType!     # PROJECT, MILESTONE, TODO, TASK, COMMENT, IDEA
  id: ID!
  title: String!           # bei Kommentaren ein Auszug
  project: Project         # null bei Ideen ohne Projekt
  deletedBy: User
  deletedAt: DateTime!
  "Wie viele Elemente mit diesem Löschvorgang zusammenhängen (z. B. 4 ToDos, 12 Tasks)"
  childCount: Int!
  viewerCanRestore: Boolean!
  viewerCanPurge: Boolean!
}

# Query
trash(projectId: ID): [TrashItem!]!     # ohne projectId: gelöschte Projekte und eigene Ideen

# Mutation
restoreFromTrash(deletionId: ID!): TrashItem!
purgeFromTrash(deletionId: ID!): Boolean!
emptyProjectTrash(projectId: ID!): Int!   # Anzahl endgültig gelöschter Vorgänge
```

Die bestehenden `delete*`-Mutationen bleiben vom Namen her, legen aber in den Papierkorb. Rückgabe: das gelöschte Element mit `deletionId`, damit das Frontend direkt „Rückgängig“ anbieten kann (Toast mit Knopf).

**Rechte (in die Rechte-Tabellen):**
- Wiederherstellen: wer das Element löschen durfte (gleiche Fähigkeit, z. B. `task.delete`). Ein gelöschtes Projekt stellen Owner und Admins wieder her.
- Endgültig löschen: neue Projektfähigkeit `trash.purge`, nur Owner (Admins immer). Gilt auch für gelöschte Projekte und „Papierkorb leeren“.
- Kommentare: wiederherstellen durch Autor oder `comment.moderate`, wie beim Löschen.
- Ideen: Autor und Admins, auch endgültig löschen.
- MCP: weiterhin **kein** Löschen und kein Papierkorb über den Connector.

**Oberfläche:**
- Pro Projekt `/projects/:id/trash` (in den Projekteinstellungen verlinkt).
- Global `/trash` für gelöschte Projekte und eigene Ideen.
- Ein archiviertes Projekt hat einen eigenen Bereich „Archiv“ in der Projektliste.

### A6. User-Story am Task (neu, entschieden am 2026-11-12)

- Spalte `tasks.user_story` (JSON, nullable). Objekt mit `role`, `goal`, `benefit`. Alle drei Felder sind Pflicht, wenn das Objekt gesetzt ist. Grenzen: `role` 80 Zeichen, `goal` und `benefit` je 160 Zeichen, jeweils Klartext ohne Formatierung.
- GraphQL: neuer Typ `UserStory { role: String!, goal: String!, benefit: String! }`, Feld `Task.userStory: UserStory`, Eingabe in `ProjectPlanTaskInput.userStory` und in den Task-Eingaben von `createTask` und `updateTask`.
- Rechte: wie das Bearbeiten des Tasks (`task.update`, bei eigenen Tasks `task.updateOwn`).
- Anzeige: Die Kanban-Karte zeigt die Story nicht. Das Task-Detail zeigt sie über der Beschreibung.
- MCP und Assistent: Das Feld wird in das Plan-Schema aufgenommen. R2 (`PlanSchema`) übernimmt es dann mit.
- Migration: neue Spalte, bestehende Tasks bleiben `null`. Breaking Changes sind laut Rahmen erlaubt.

---

## B. Neue Teile (aus dem Ideen-Konzept), Schema-Entwurf

### B1. Ideen

```graphql
enum IdeaStatus { OPEN, ADOPTED, DISCARDED }

type Idea {
  id: ID!
  title: String!
  description: RichText
  author: User            # null, wenn gelöscht
  project: Project        # optional, nur wenn sichtbar
  status: IdeaStatus!
  "ToDos, die aus der Idee entstanden sind"
  todos: [Todo!]!
  createdAt: DateTime!
  updatedAt: DateTime!
}

input CreateIdeaInput { title: String!, description: RichText, projectId: ID }
input UpdateIdeaInput { title: String, description: RichText, projectId: ID, status: IdeaStatus }

# Query
ideas(status: IdeaStatus = OPEN): [Idea!]!   # Scope: eigene, Admins alle
idea(id: ID!): Idea

# Mutation
createIdea(input: CreateIdeaInput!): Idea!
updateIdea(id: ID!, input: UpdateIdeaInput!): Idea!
deleteIdea(id: ID!): Idea!                    # in den Papierkorb, Autor oder Admin
```

Datenbank: Tabelle `ideas` (`user_id` nullOnDelete, `project_id` nullOnDelete, `status`, `title`, `description` JSON, `deleted_at`, `deleted_by`, `deletion_id`). Verknüpfung zu ToDos über `todos.idea_id` (nullable, nullOnDelete). Wird eine Idee über `importPlanIntoProject(…, ideaId)` eingetragen, setzt der Importer `idea_id` und den Status `ADOPTED`.

Rechte: neue Systemfähigkeiten `ideas.create` (alle Rollen außer `lesend`) und `ideas.viewAny` (Admin). Eigener Scope `visibleIdeas`: eigene Ideen, Admins alle. Eine Idee mit Projekt bleibt trotzdem nur für Autor und Admins sichtbar.

MCP: `create_idea` und `list_ideas` (siehe `docs/assistent-ideen-konzept.md`, Abschnitt 8).

### B2. KI-Kontext pro Projekt

Eigene Tabelle `project_ai_contexts` (`project_id` unique, cascadeOnDelete). Gespeichert werden nur Text, Dateiname und extrahierter Dateitext, keine Datei.

```graphql
type ProjectAiContext {
  text: String
  "Name der hochgeladenen Datei, null ohne Datei"
  fileName: String
  "Extrahierter Inhalt der Datei"
  fileText: String
  shareWithExternalTools: Boolean!
  updatedAt: DateTime!
}

# an Project, lesbar für alle, die das Projekt sehen
aiContext: ProjectAiContext

# Mutation
updateProjectAiContext(projectId: ID!, text: String, shareWithExternalTools: Boolean): ProjectAiContext!
uploadProjectAiContextFile(projectId: ID!, file: Upload!): ProjectAiContext!   # nur .txt, .md
deleteProjectAiContextFile(projectId: ID!): ProjectAiContext!
```

- Lesen: alle Projektmitglieder (entschieden, Variante A). Der Assistent nutzt den Kontext bei allen, die das Projekt sehen.
- Ändern: `project.update` (Owner, Manager).
- Höchstens 20 000 Zeichen für Text und Dateitext zusammen.
- Die Oberfläche weist darauf hin: Den Inhalt sehen alle Mitglieder, und er geht an den KI-Dienst. Vertrauliches gehört nicht hinein.
- MCP: `get_project_ai_context(project_id)` liefert nur bei eingeschalteter Freigabe.

### B3. Rechte für den Assistenten

- Neue Systemfähigkeit `assistant.use`: alle Rollen außer `lesend`.
- `draftProjectPlan` prüft `assistant.use`, `importProjectPlan` weiter `projects.create`, `importPlanIntoProject` die Projektrechte je Stufe.
- Seite `/assistant` und Menüpunkt „Ideen“ blenden nach `viewerSystemAbilities` ein.

---

## C. Mittlere Priorität

### C1. Mutationen liefern die betroffenen Elternteile

Heute lädt das Frontend nach jeder Änderung alle aktiven Abfragen neu, weil Status und Fortschritt von ToDo und Milestone sich mitändern. Vorschlag: `moveTask`, `createTask`, `deleteTask`, `moveTaskToTodo`, `restoreFromTrash` geben einen Payload zurück:

```graphql
type TaskChangePayload { task: Task, todo: Todo!, milestone: Milestone! }
```

Apollo aktualisiert dann Status und Fortschritt aus dem Cache. Breaking für die Rückgabetypen.

### C2. `Project.description` als RichText

Milestone, ToDo, Task und Kommentar sind RichText, nur das Projekt ist Klartext. Vereinheitlichen spart eine Sonderbehandlung in Frontend und Importer.

### C3. Kleinere Unstimmigkeiten

- `updateMyNotifications(emailEnabled)` setzt das Feld `notifyByEmail`. Argument gleich benennen.
- `rejectUser` gibt `Boolean!` zurück, `deleteUser` den `User`. Einheitlich machen.
- `MemberCandidate` steht in `inputs.graphql`, gehört nach `types.graphql`.
- Kopfkommentar in `schema.graphql`: Rechte-Tabelle und „Projekte anlegen nur Admins“ sind veraltet. Den Kommentar auf einen Verweis zu `permissionMatrix` kürzen.

---

## D. Niedrige Priorität

- **Pagination:** `users`, `myTasks`, `todos`, `comments`, `trash` liefern alles. Für die erwartete Größe reicht das. Bei Wachstum `@paginate` (Cursor). Beim Papierkorb ohne Frist früher prüfen.
- **Gespeicherter Status von ToDo und Milestone:** bleibt sinnvoll (Filter, Dashboard). Kein Handlungsbedarf.
- **`project_user.id`:** zusammengesetzter Primärschlüssel wäre schlanker, bringt aber kaum etwas.

---

## E. Dokumente, die nicht mehr zum Code passen

- Die Kontext-Datei für Claude Desktop (im Claude-Projekt) sagt „keine Selbstregistrierung“. Im Schema gibt es aber `approveUser`, `rejectUser`, `User.approved`, `User.emailVerified`.
- Die MCP-Werkzeugliste dort ist veraltet: `set_task_status`, `add_to_plan`, `update_plan_item`, `move_milestone` fehlen.
- `docs/rollen-plan.md` und der Kopfkommentar in `schema.graphql`: Rechte nach dem Funktionsbau nachführen.

---

## Reihenfolge der Umsetzung

0. Refactorings laut `docs/refactoring-vor-umsetzung.md`.
1. Datenstruktur in einer Migration, frisch aufgesetzt (`migrate:fresh`), Seeder anpassen: A1 `project_id`, A2 `created_by`, A3 `todos.position` und `boardPosition`, A5 `archived_at`, `deleted_at`/`deleted_by`/`deletion_id`, A6 `tasks.user_story`.
2. Rechte: `task.create` für Mitglieder, `task.updateOwn`, `task.assignSelf`, `project.archive`, `trash.purge`, `assistant.use`, `ideas.create`, `ideas.viewAny` in die beiden Rechte-Tabellen.
3. Archiv und Papierkorb (Backend, dann Frontend).
4. A4 gemeinsamer Plan-Entwurf und `importPlanIntoProject`.
5. B1 Ideen, B2 KI-Kontext, MCP-Werkzeuge `create_idea`, `list_ideas`, `get_project_ai_context`.
6. C1 Payloads, danach C2 und C3.
7. Dokumentation nachführen (E), Rechte-Prüfung nach dem Funktionsbau.
