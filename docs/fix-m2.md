# Fix M2 (F5): Befunde der Abnahme M2

Stand: 2026-11-17, Planung. Geprüft in Chrome an `dev` bis `764304f` mit frischen Demo-Daten. Rolle: Auftrag für die Umsetzung (Bob). Ein Commit je Punkt, `umsetzung.md` „Stand“ nachführen. Ergebnisse der Abnahme: `docs/abnahme-m2.md`.

Entscheidungen von Oliver (2026-11-17): F5.3 ja, F5.4 nach Position, F5.5 ja.

---

## F5.1 „Rückgängig“ im offenen Task-Detail nicht erreichbar (Fehler)

**Befund:** Im Task-Detail (`wa-drawer`, modal) einen Kommentar löschen: Der Toast „Gelöscht“ erscheint, liegt aber hinter dem modalen Dialog. `elementFromPoint` auf „Rückgängig“ liefert den Drawer, ein Klick bewirkt nichts, `focus()` greift nicht (alles außerhalb des modalen Dialogs ist inert). Rückgängig ist für Kommentare damit unbenutzbar, solange das Detail offen ist.
Zusätzlich: Beim Löschen des Tasks im Detail schließt sich der Drawer und der Fokus landet auf `body`, nicht auf „Rückgängig“ (`useDelete` prüft den Fokus direkt nach `nextTick`, bevor der Drawer den Fokus zurückgibt und bevor `wa-button` gerendert ist).

**Umsetzung:**
1. Toasts müssen über einem offenen modalen Dialog bedienbar sein. Vorschlag: `useToast` hängt den Stapel (`wa-toast`) in den obersten offenen modalen Dialog ein (bzw. eine Stapel-Instanz je Dialog, beim Schließen zurück an `body`), sodass er nicht inert ist. Alternative, falls Web Awesome das nicht sauber erlaubt: im Detail für Kommentare ein Inline-Rückgängig an der Stelle des gelöschten Kommentars („Kommentar gelöscht · Rückgängig“, 8 s). Bei Zweifel Rückfrage an die Planung.
2. Fokus: erst setzen, wenn der Knopf gerendert ist (`await customElements.whenDefined('wa-button')` plus `updateComplete` bzw. `requestAnimationFrame`) und nachdem ein Drawer/Dialog sich geschlossen hat (Ereignis `wa-after-hide` abwarten oder Fokus-Rückgabe des Drawers abfangen). Bedingung bleibt: nur wenn der Fokus im gelöschten Element lag bzw. verloren ging.
3. Test (Vitest/happy-dom soweit möglich) und Prüfung im Browser: Kommentar im Detail löschen → „Rückgängig“ per Klick und per Tastatur; Task im Detail löschen → Fokus auf „Rückgängig“, Enter holt zurück.

## F5.2 Dashboard zeigt archivierte Projekte (Fehler)

**Befund:** David, Website-Relaunch archiviert: „Meine offenen Tasks“ und „Anstehende ToDo“ sind richtig gefiltert, die Kacheln unter „Projekte“ zeigen Website-Relaunch weiter (auch nach Neuladen).
**Ursache:** `types.graphql`, `Dashboard.projects: [Project!]! @all(scopes: ["visibleToViewer"])` ohne Filter auf `archived_at` (die anderen beiden Felder nutzen `inActiveProjects()`).
**Umsetzung:** Scope für nicht archivierte Projekte ergänzen (z. B. `scopes: ["visibleToViewer", "notArchived"]` bzw. Resolver in `Dashboard`), Test in `ProjectArchiveTest`: archiviertes Projekt fehlt in `dashboard.projects`.

## F5.3 „Löschen“ im Menü der Kanban-Karte (Entscheidung: ja)

**Befund:** Das „…“-Menü einer Task-Karte hat Ändern, Nach oben/unten, Verschieben, aber kein „Löschen“. Milestones und ToDos haben „Löschen“ im Menü.
**Umsetzung:** Eintrag „Löschen“ (rot, wie bei Milestone/ToDo) ans Ende des Karten-Menüs, sichtbar mit `Task.viewerCanDelete`, über `useDelete` (Toast mit Rückgängig). Auch in der Zeitachse, falls Tasks dort ein Menü haben.

## F5.4 Milestones nach Position statt Datum (Entscheidung: nach Position)

**Befund:** Die Zeitachse (`components/project/Timeline.vue`, `byDueDate`) und die Nummer M1, M2 … (`MilestoneNumber`) ordnen Milestones nach Fälligkeit. Ein wiederhergestellter Milestone steht dadurch nicht „am Ende“, wie die Papierkorb-Seite sagt; die gespeicherte `position` (Backend sortiert schon danach) hat in der Oberfläche keine Wirkung.
**Umsetzung:**
1. Zeitachse und Nummerierung folgen der Reihenfolge aus dem Backend (`position`), nicht dem Datum. Tasks innerhalb eines ToDos bleiben wie heute.
2. Milestone-Menü bekommt „Nach oben“ / „Nach unten“ über die bestehende Mutation `moveMilestone` (über `useProjectMutation`), wie bei den Kanban-Karten.
3. Kommentar in `MilestoneNumber.vue` anpassen. Prüfen, dass Planer-Export und Import (`ProjectPlanReader`) die Reihenfolge nach `position` schreiben (sollte schon so sein).
4. Demo-Seeder: Positionen so, dass die Demo-Projekte unverändert aussehen (heute zufällig gleich wie nach Datum).

## F5.5 Ablehnung „This action is unauthorized.“ übersetzen (Entscheidung: ja)

**Befund:** Fehlende Rechte (z. B. `purgeFromTrash`/`emptyProjectTrash` als Manager, `deleteTask` als Gast) liefern das englische Standard-`AuthorizationException`-Text von Laravel/Lighthouse.
**Umsetzung:** Standardtext über `lang/{de,en}` übersetzen (z. B. „Dafür fehlt dir das Recht.“ / „You are not allowed to do this.“), Sprache wie bei den übrigen Meldungen. Spezifische Meldungen (archiviert, Elternteil im Papierkorb, Grundregeln) bleiben. Test: eine abgelehnte Mutation liefert den deutschen Satz.

## F5.6 Wiederhergestellter Kommentar zeigt „bearbeitet“ (Fehler, Nachprüfung 2026-11-17)

**Befund:** Kommentar löschen und wiederherstellen (Rückgängig oder Papierkorb): danach steht „bearbeitet“ daneben, obwohl der Text unverändert ist. `Comments.vue` zeigt „bearbeitet“, wenn `updatedAt !== createdAt`; Löschen (`deleted_at`, `deletion_id`) und Wiederherstellen setzen `updated_at` neu.
**Umsetzung:** Eigene Spalte `comments.edited_at` (nullable), gesetzt nur von `updateComment` bei geändertem Text; GraphQL `Comment.editedAt: DateTime`; `Comments.vue` zeigt „bearbeitet“ nur bei `editedAt`. Migration: `edited_at = updated_at` wo `updated_at > created_at` und `deleted_at is null` (bestehende Daten; durch Papierkorb verfälschte Einzelfälle sind hinnehmbar). Seeder: die zwei Demo-Kommentare wie bisher als bearbeitet markieren, wenn gewollt. `db-schema.ts` ergänzen. Test: löschen und wiederherstellen ändert `editedAt` nicht; `updateComment` setzt es.

---

## Nachprüfung 2026-11-17 (Code bis `304991f`, frische Demo-Daten)

F5.1 ✅ (Toast über offenem Detail klickbar, Fokus auf „Rückgängig“ nach Löschen im Detail und aus dem Karten-Menü, Enter holt zurück), F5.2 ✅, F5.3 ✅, F5.4 ✅ (Reihenfolge nach Position, „Nach oben“ wirkt, Nummern folgen), F5.5 ✅ („Dafür fehlt dir die Berechtigung.“), Zusatz Papierkorb-Cache (`5b1f1d2`) ✅ (Task und Kommentar mit derselben Id erscheinen getrennt). Neu: F5.6.

F5.6 nachgeprüft an `0bdea9b` ✅: Löschen und Wiederherstellen lassen `editedAt` unverändert; `updateComment` setzt es nur bei geändertem Text. F5 damit abgeschlossen.

---

## Ablauf

F5.2 → F5.5 → F5.3 → F5.1 → F5.4 (erledigt), dann F5.6. Danach alle Prüfungen, im Browser ansehen (F5.1 mit Maus und Tastatur), dann anhalten für die Nachprüfung.
