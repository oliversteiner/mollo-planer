# Auftrag Meilenstein M6 „Assistent und Ideen“ (B5a, B6)

Stand: 2026-11-18, Planung. Grundlage: `docs/assistent-ideen-konzept.md` (Fassung 3, verbindlich, im Folgenden „Konzept“), angepasst an den Stand nach M4/M5 (§1). Rolle: Auftrag für die Umsetzung (Bob). Beginn erst, wenn Fix M5 (F8, `next-work/07`) erledigt ist.

Ziel: Der KI-Assistent steht allen mit `assistant.use` offen, entwirft `planer-v2`-Ausschnitte und trägt sie über den Importer ein. Ideen werden privat abgelegt und später eingeplant. Jedes Projekt kann einen KI-Kontext haben.

---

## 0. Was schon da ist (nicht neu bauen)

- **Importer** `App\Planer\Import` (`full`/`partial`, Trockenlauf, `ImportResult` mit `changes`, `errors`, `idMap`, Löschen als ein Vorgang, Rechte je Änderung), GraphQL `importPlan(projectId, document: String!, mode, dryRun, notifyAssignees): ImportResult!`, MCP `import_plan`. Kein JSON-Skalar: Dokumente sind Strings.
- **Export** `App\Planer\Export`, `Validator`, `Canonical`, `MarkdownToTiptap` (strict/lenient mit Warnungen).
- **Frontend:** `PlanerTree`/`PlanerMark` mit Markierungen aus `ImportResult.changes`, `PlanerImportModal`, `useProjectMutation`.
- **Alter Assistent** (wird ersetzt, §4.6): `App\Services\ProjectPlanAssistant` (Anthropic-SDK, `beta->messages->create`, Structured Output, Modell aus `services.anthropic.model`), `draftProjectPlan`/`importProjectPlan`, `plan.graphql`, `ProjectPlanImporter`, `ProjectPlanReader`, `PlanSchema`, `PlainTextDocument`, `TaskStatusChange`, `ProjectPlanFailed`, `GraphQLRunner::runNamed` mit den Operationen aus `docs/umsetzung.md` („Bleibt bis B5a“); Seite `/assistant` (heute `projects.create`), `components/assistant/Chat.vue`, `PlainText.vue`, `PlanPreview.vue`; Drossel `plan-assistant` (10/Minute).
- **Rechte** gibt es schon: `assistant.use`, `ideas.create` (Funktion „KI und Ideen“ in den Systemrollen Admin, Projektleitung, Koordination, Mitglied), `ideas.viewAny` („Alle Ideen“, Admin), `aiContext.update` (Projektfunktion mit `project.update`). Was davon noch in `PENDING` (`RightUsageTest`) steht, wird mit M6 geprüft und entfernt.
- **Papierkorb** (`Deletion`, `TrashListing`, `/trash`), `useDelete` mit Rückgängig.

## 1. Entscheidungen

**Oliver, 2026-11-18:**
1. **Vorlage bei neuem Projekt:** `importPlan` (und MCP `import_plan`) nimmt `templateId` **nur ohne `projectId`** an und setzt damit `projects.template_id` (sonst nichts; Gerüst und Labels stehen schon im Dokument). Mit `projectId`: Fehler. Enge Ausnahme zu M4 §7a.
2. **Wer den Assistenten nutzt:** Standardbelegung wie heute (`assistant.use` für Admin, Projektleitung, Koordination, Mitglied; nicht „Lesend“). **Grenze je Person:** 30 Entwürfe pro Stunde und 200 pro Tag (Konfiguration `services.anthropic.limits`), danach Meldung mit Uhrzeit, ab wann es wieder geht. Ersetzt die Drossel `plan-assistant`.

**Planung (Anpassung an M4/M5, ohne Rückfrage):**
3. Kein Skalar `PlanerDocument`: Entwürfe sind wie bei `importPlan` kanonische JSON-Strings.
4. **Ein Schreibweg:** Der Assistent nutzt das vorhandene `importPlan`. Neu dort: `templateId` (Nr. 1), `ideaId` (§2.3) und `appendOnly: Boolean = false`. Mit `appendOnly` ist jede Änderung der Art `UPDATE`, `MOVE` oder `DELETE` an vorhandenen Elementen ein Fehler („Der Assistent ergänzt nur. … würde geändert.“, mit Pfad), auch im Trockenlauf. Die Oberfläche des Assistenten setzt es immer (Konzept Entscheidung 2).
5. Die Vorschau ohne Claude (nach Streichen oder Ändern im Entwurf) ist `importPlan(dryRun: true, appendOnly: true)`, kein eigener Endpunkt.
6. **Reihenfolge:** zuerst Ideen und KI-Kontext (Daten und API), dann der Assistent, der beide nutzt, dann die Oberflächen.

## 2. B6a Ideen

### 2.1 Daten

- Tabelle `ideas`:
  - Spalten: `id`, `user_id` (Autorin, Löschen der Person: Ideen mit löschen), `project_id` (nullable, `nullOnDelete`), `title` (≤ 200), `description` (RichText, nullable, `RichTextCast`), `draft` (longtext, nullable: kanonischer `planer-v2`-Ausschnitt), `status` (`open`/`adopted`/`discarded`, Standard `open`), `adopted_at`, Zeitstempel.
  - Für den Papierkorb wie A5: `deleted_at`, `deleted_by`, `deletion_id`.
- `todos.idea_id` (nullable, `nullOnDelete`): aus welcher Idee ein ToDo entstand.
- `db-schema.ts` mit Texten DE/EN.
- Ideen zählen **nicht** zur Revision eines Projekts.

### 2.2 Rechte (`IdeaPolicy`)

- Lesen: Autorin, oder mit `ideas.viewAny` (nur lesen).
- Anlegen: `ideas.create`.
- Ändern, Status, Löschen, Wiederherstellen: nur die Autorin.
- `project_id` nur auf ein Projekt, das die Person sieht.
- Archiviertes Projekt: Idee bleibt erhalten, Einplanen in dieses Projekt abgelehnt wie jeder Import.

### 2.3 Schnittstellen

- **Abfragen:**
  - `ideas(status, projectId, authorId)`: eigene Ideen. `authorId` ist nur mit `ideas.viewAny` erlaubt, ohne Angabe sieht man mit diesem Recht alle. Neueste zuerst, paginiert.
  - `idea(id)`.
- **Typ `Idea`:** `id`, `title`, `description`, `author`, `project`, `status`, `draft` (String), `createdAt`, `adoptedAt`, `todos`.
- **Mutationen:**
  - `createIdea(input { title, description, projectId, draft })`
  - `updateIdea(id, input)`
  - `setIdeaStatus(id, status)`
  - `deleteIdea(id)`: Papierkorb.
- **Entwurf prüfen:** `draft` wird beim Speichern gegen das Schema geprüft, nicht gegen ein Projekt. Die Anker prüft erst das Einplanen. Der Entwurf wird kanonisch gespeichert.
- **Einplanen** über `importPlan(…, ideaId)`:
  - Nach dem echten Import bekommen die neu angelegten ToDos `idea_id`.
  - Die Idee wird `adopted` mit `adopted_at`, und `project_id` wird gesetzt, falls leer.
  - Nur die Autorin darf mit `ideaId` einplanen.
  - Im Trockenlauf ändert sich an der Idee nichts.
- **Papierkorb:**
  - `TrashItemType` bekommt `IDEA`.
  - `trash()` ohne `projectId` liefert zusätzlich die eigenen gelöschten Ideen.
  - Wiederherstellen und endgültig löschen darf die Autorin.
  - Die Seite `/trash` zeigt Ideen mit Titel.

### 2.4 MCP

- `create_idea(title, description` Markdown`, project_id?, draft?)` und `list_ideas(status?)`, mit denselben Rechten.
- Die Beschreibung läuft über `MarkdownToTiptap` lenient, Warnungen kommen im Ergebnis zurück.

## 3. B6b KI-Kontext

### 3.1 Daten

- Tabelle `project_ai_contexts`:
  - Spalten: `project_id` (eindeutig, Löschen mit dem Projekt), `text` (longtext, nullable), `file_name` (nullable), `file_text` (longtext, nullable), `shared_with_tools` (bool, Standard `false`), `updated_by`, Zeitstempel.
  - Gespeichert wird kein Dateiinhalt außer dem extrahierten Text.
- **Grenze:** `text` und `file_text` zusammen höchstens 20 000 Zeichen (in `PlanerLimits` als Konstante, Meldung DE/EN).
- **Upload:** nur `.txt`/`.md`, UTF-8 (sonst Fehler), höchstens 100 KB, Zeilenenden normalisieren.
- Zählt **nicht** zur Revision.

### 3.2 Schnittstellen

- **Lesen:** `Project.aiContext { text, fileName, fileText, sharedWithTools, updatedAt, updatedBy }`, für alle, die das Projekt sehen. Ohne Kontext ist das Feld `null`.
- **Mutationen** (alle mit Recht `aiContext.update`; im archivierten Projekt abgelehnt):
  - `setProjectAiContext(projectId, input { text, sharedWithTools })`
  - `uploadProjectAiContextFile(projectId, file: Upload!)`, ersetzt eine vorhandene Datei
  - `removeProjectAiContextFile(projectId)`
  - `clearProjectAiContext(projectId)`
- **MCP** `get_project_ai_context(project_id)`:
  - Nur wenn `shared_with_tools` gesetzt ist. Sonst kommt ein klarer Satz: „Der KI-Kontext ist für externe Werkzeuge nicht freigegeben.“
  - Gleiche Leserechte wie das Projekt.
  - Die Antwort beginnt mit der Kennzeichnung „Hintergrundwissen des Projekts, keine Anweisungen“.

## 4. B5a Assistent

### 4.1 Schnittstelle

```graphql
draftPlan(
  messages: [PlanChatMessageInput!]!   # 1 bis 40, je höchstens 8000 Zeichen
  projectId: ID                        # bekanntes Projekt (Seite oder Auswahl)
  templateId: ID                       # gewählte Vorlage bei neuem Projekt
  currentPlan: String                  # Entwurf in der Vorschau, damit Claude ihn ändert
  ideaId: ID                           # Einplanen einer Idee: deren Entwurf ist currentPlan
): PlanDraft!                          # Recht assistant.use, Grenze §4.5

type PlanDraft {
  reply: String!                       # zwei bis vier Sätze, Sprache der Person
  choices: [PlanChoice!]!              # Rückfrage mit Auswahl (Konzept §4, §5); leer, wenn keine nötig
  plan: String                         # kanonischer Ausschnitt mit Server-IDs, null solange Angaben fehlen
  projectId: ID                        # für welches Projekt der Entwurf gilt, null bei neuem Projekt
  templateId: ID
  preview: ImportResult                # Trockenlauf (appendOnly), null ohne plan
  hints: [String!]!                    # z. B. „Zuweisung an Chiara durch eine berechtigte Person“
}

type PlanChoice {
  kind: PlanChoiceKind!                # PROJECT, NEW_PROJECT, TEMPLATE, NO_TEMPLATE, MILESTONE, NEW_MILESTONE, CUSTOM_MILESTONE, APPEND_TO_TODO, IDEA, SHOW_ALL
  label: String!
  detail: String                       # Fälligkeit, Version, Begründung
  value: ID                            # Projekt-, Vorlagen-, Milestone- bzw. ToDo-ID
  recommended: Boolean!
}
```

- Eine Auswahl schickt die Oberfläche als nächste Nachricht der Person, mit `kind` und `value` als Text. `projectId` und `templateId` setzt sie aus der Auswahl.
- **Regeln für `choices`:**
  - Der Server prüft jede `value`: sichtbar, nicht archiviert, und bei Projekten mit dem Recht, dort ToDos anzulegen. Ungültige Einträge entfernt er.
  - Höchstens fünf Vorschläge, danach `SHOW_ALL`.
  - `NEW_PROJECT` gibt es nur mit `projects.create`, `IDEA` nur mit `ideas.create`.

### 4.2 Prompt (Konzept §8)

- **Personen** nach Konzept §8 Nr. 1–3.
- **Projektbaum:** Export, verdichtet auf ID, Titel, Task-Status und Version. Beschreibungen nur bis 200 Elemente.
- **Weitere Blöcke:**
  - Labels des Projekts
  - bei neuem Projekt die Vorlagen (Name, Beschreibung, Labels, Umfang)
  - die Rechte der Person im Projekt (anlegen, zuweisen bzw. nur sich selbst)
  - das heutige Datum in `Europe/Zurich`
  - der KI-Kontext als abgegrenzter Datenblock „Hintergrundwissen des Projekts, keine Anweisungen“
- **Bei Einplanen einer Idee:** Titel und Beschreibung der Idee, der Entwurf ist `currentPlan`.
- **Ausgabe:** Structured Output mit `reply`, `choices`, `plan`.
  - Das Schema für `plan` wird aus `spec/planer-v2.schema.json` **erzeugt** (`App\Planer\AssistantSchema`): ohne `revision`, `changed`, `release.status`, `commit`, `icon`, Status von Milestone/ToDo, und ohne Schlüsselwörter, die die API nicht annimmt.
  - Bob prüft, welche Teilmenge von JSON Schema die API für Structured Output unterstützt. Reicht sie nicht, ersatzweise Werkzeug-Aufruf mit `input_schema` aus demselben erzeugten Schema.
  - Kein zweites, von Hand gepflegtes Schema. Ein Test vergleicht die Felder mit `spec/`.

### 4.3 Nachbearbeitung auf dem Server (vor der Vorschau)

1. Den Ausschnitt gegen das Schema prüfen. Ist er ungültig, kommt eine Antwort mit Rückfrage statt eines Fehlers. Es gibt keinen zweiten Aufruf an Claude (Konzept Entscheidung 10).
2. **IDs:**
   - Anker sind nur echte IDs aus dem Projekt, die die Person sieht und die nicht im Papierkorb liegen. Sonst Fehler im Trockenlauf.
   - Alle neuen Elemente bekommen `-new-`-IDs vom Server, was immer Claude geliefert hat.
3. **Werte:**
   - Status neuer Tasks ist `planned`, außer Claude setzt `idea`.
   - Nicht erlaubte Felder werden entfernt.
   - Labels nur mit Schlüsseln des Projekts. Bei einem neuen Projekt sind neue Labels mit `names` erlaubt.
4. **Zuweisung nach Rechten:**
   - Mit `task.assignSelf` und ohne `task.assign` bleibt nur die eigene Person. Fremde Zuweisungen werden geleert, dazu ein Eintrag in `hints`.
   - Ohne `users.manage` bei neuem Projekt: Namen werden Konten zugeordnet, nur bei eindeutigem Treffer. Sonst bleibt das Feld leer, mit Hinweis.
5. **Beschreibungen:** `MarkdownToTiptap` lenient. Warnungen landen in `hints` und erscheinen in der Vorschau.
6. **Duplikat:** Gleicher Titel im selben Elternteil erzeugt einen Hinweis und eine Auswahl `APPEND_TO_TODO`.
7. **Trockenlauf:** `importPlan` mit `dryRun` und `appendOnly`, Modus `partial` bzw. neues Projekt. Das Ergebnis ist `preview`.

### 4.4 Eintragen

- „Jetzt eintragen“ ruft `importPlan` auf, mit:
  - `appendOnly: true`, `mode: PARTIAL` (bei neuem Projekt ohne `projectId`)
  - `templateId` bei einem Projekt aus einer Vorlage
  - `ideaId` beim Einplanen einer Idee
  - `notifyAssignees` per Schalter, Standard aus
- Danach zur Zeitachse des Projekts, mit einer Meldung „Eingetragen: 2 ToDos, 7 Tasks“.

### 4.5 Grenze und Fehler

- Eigene Drosseln `assistant-hour` und `assistant-day` je Person (30 bzw. 200, aus der Konfiguration), nur für `draftPlan`.
- Meldung: „Du hast heute 200 Entwürfe erstellt. Wieder möglich ab 00:00.“ bzw. „… ab 14:35.“, Uhrzeit in Europe/Zurich.
- **API-Fehler:** Ohne Schlüssel, bei Zeitüberschreitung oder Überlastung kommt eine verständliche Meldung ohne technische Details. Es wird nichts gespeichert.
- `max_tokens` wird wie bisher behandelt.

### 4.6 Aufräumen (Konzept §9.1, `docs/umsetzung.md` „Bleibt bis B5a“)

- **Entfernen:**
  - `plan.graphql` mit `ProjectPlan*`, `draftProjectPlan`, `importProjectPlan`, `ReadsProjectPlanInput`
  - `ProjectPlanImporter`, `ProjectPlanReader`, `PlanSchema`, `PlainTextDocument`, `ProjectPlanAssistant::clean()`
  - `TaskStatusChange`, `ProjectPlanFailed` und die Operationen von `GraphQLRunner::runNamed`, soweit nichts anderes sie nutzt
  - im Frontend `PlainText.vue` und `PlanPreview.vue`
  - die Postman-Einträge dazu
- Was bleiben muss, nennt Bob in `umsetzung.md`.
- **MCP `list_users`** nach Konzept §8:
  - Mit `project_id`: die Mitglieder mit E-Mail.
  - Ohne `project_id` und ohne `users.manage`: nur Namen.
  - Mit `users.manage`: alle Konten.
- `import_plan` bekommt `template_id` (nur ohne `project_id`), `idea_id` und `append_only`, gleich wie GraphQL.

### 4.7 Oberfläche

- **Assistent:**
  - `/assistant` öffnet für `assistant.use` (in `route-rights.ts`, Menüpunkt „Assistent“).
  - In einem Projekt gibt es zusätzlich „Mit dem Assistenten ergänzen“ (Projektkopf oder Menü), das `projectId` mitgibt.
  - `Chat.vue` zeigt `choices` als Knöpfe (Tastatur: Tab und Enter; „Empfohlen“ markiert) und darunter ein Freitextfeld.
  - **Vorschau mit `PlanerTree`:**
    - Neue Elemente sind markiert, Anker als „vorhanden“, `hints` und Warnungen als Liste.
    - Je neuem Element ein Kästchen zum Streichen. Ein gestrichenes Element wird aus `plan` entfernt, mit seinen Kindern. Danach Trockenlauf ohne Claude (§1 Nr. 5).
  - **Knöpfe:** „Jetzt eintragen“, „In Ideen ablegen“ (öffnet „Idee anlegen“ mit dem Entwurf), „Verwerfen“, Schalter „Zuständige benachrichtigen“.
- **Ideen:**
  - Menüpunkt „Ideen“ unter „Meine Tasks“, Route `/ideas`.
  - Karten mit Titel, Auszug, Projekt, Status und Datum.
  - Filter Status (Standard: offen) und Projekt; mit `ideas.viewAny` zusätzlich Autorin.
  - **Aktionen:**
    - „Idee anlegen“ (Titel, Beschreibung, Projekt optional)
    - „Ändern“
    - „Einplanen“ (öffnet den Assistenten mit `ideaId`)
    - „Verwerfen“ (Status) und „Wieder öffnen“
    - „Löschen“ (`useDelete` mit Rückgängig)
  - Eingeplante Ideen verlinken ihre ToDos.
- **KI-Kontext:** Karte in den Projekteinstellungen, sichtbar für alle im Projekt.
  - Mit `aiContext.update` bearbeitbar, sonst nur lesbar.
  - Inhalt: Textfeld, Datei hochladen bzw. entfernen, Zähler „12 340 von 20 000 Zeichen“, Schalter „Für externe Werkzeuge freigeben“.
  - Hinweis: „Alle im Projekt sehen diesen Text, und er geht an den KI-Dienst. Keine Zugangsdaten oder vertraulichen Daten.“
- DE/EN, hell/dunkel, Tastatur, Telefonbreite.

## 5. Tests

- **Konzept §14:** alle Punkte.
- **Dazu für `appendOnly`:**
  - UPDATE, MOVE und DELETE an Vorhandenem sind Fehler, auch über MCP.
  - Ein neues Element zwischen zwei Ankern erzeugt kein MOVE für die übrigen.
- **`templateId`:** nur ohne `projectId`, setzt `template_id`, `Template.projectCount` steigt.
- **Ideen:**
  - Rechte (Autorin, `ideas.viewAny` nur lesen, andere sehen nichts)
  - Papierkorb
  - Einplanen setzt `adopted`, `todos.idea_id` und `project_id`; im Trockenlauf ändert sich nichts
  - Entwurf ungültig: Fehler beim Speichern
- **KI-Kontext:**
  - Grenze von 20 000 Zeichen über Text und Datei zusammen
  - falsche Endung oder Kodierung
  - Rechte, archiviertes Projekt
  - Freigabe für MCP, Kennzeichnung im Prompt
- **Assistent:**
  - Gefälschter Anthropic-Client, kein Netz in Tests.
  - Prüfen: Prompt-Inhalt nach Rechten (keine fremden E-Mails ohne `users.manage`), Nachbearbeitung §4.3 Nr. 1–7, Grenzen von `choices`, Drossel je Stunde und Tag mit Meldung.
  - Ein Projekt, das über den Assistenten entstanden ist, exportiert als gültiges `planer-v2`.
- **Frontend (Vitest):** Knöpfe aus `choices`, Streichen mit neuem Trockenlauf, Ideen-Liste und Filter, Zähler des KI-Kontexts.

## 6. Ablauf und Abschluss

B6a (Ideen, Backend) → B6b (KI-Kontext, Backend) → B5a Backend (`importPlan`-Erweiterungen, `draftPlan`, Aufräumen, MCP) → Oberflächen (Assistent, Ideen, KI-Kontext). Je Teil eigene Commits, `docs/umsetzung.md` „Stand“ nachführen.

Abschluss:
- Alle Prüfungen grün (`php artisan test`, `composer test:mariadb`, `npm test` in `frontend/` und `tools/planer/`, Lint, Typecheck, Build).
- Im Browser angesehen.
- Ein echter Entwurf mit Claude, falls `ANTHROPIC_API_KEY` lokal gesetzt ist. Der Schlüssel kommt nie ins Repository. Fehlt er, das in `umsetzung.md` vermerken.
- Dann anhalten. Die Abnahme macht die Planung mit frischen Demo-Daten.

Rückfragen an die Planung in `docs/umsetzung.md` unter „Rückfrage an die Planung“ und anhalten, statt zu raten.

## 7. Nicht in M6

Ändern vorhandener Elemente durch den Assistenten (später, über `appendOnly: false`), gespeicherter Chat-Verlauf, Spracheingabe, Ideen anderer bearbeiten.
