# Fix F4 aus der Abnahme M1 (Olivers Punkte)

Stand: 2026-11-17, Planung. Code bis `47e0c74`.
Sammelt, was Oliver bei seinen offenen Punkten der Abnahme M1 findet. Wird laufend ergänzt; Bob setzt um, sobald Oliver „F4 abschließen“ sagt oder einen Punkt als dringend markiert.

---

## F4.1 Projektrolle in „Person ändern“ gesucht (entschieden 2026-11-17: nur klarer beschriften)

- Gefunden: Oliver legt die Projektrolle „redaktor - test“ an und sucht sie in `/admin/users` › „Person ändern“ › „Rolle“. Dort stehen nur Systemrollen; Projektrollen werden im Projekt unter „Mitglieder“ vergeben. Technisch korrekt, aber nicht erkennbar.
- Fix (nur Frontend, Texte DE/EN):
  - `components/admin/UserFormModal.vue`: Feld „Rolle“ heißt „Systemrolle“ (EN „System role“), darunter Hinweis: „Gilt in der ganzen App. Projektrollen vergibst du im Projekt unter ‚Mitglieder‘.“
  - `/admin/users` Liste: Badge der Rolle mit Titel/Tooltip „Systemrolle“.
  - `/admin/roles`, Reiter „Projekt“, Ansicht „Rollen“: Hinweis über der Liste: „Projektrollen vergibst du in einem Projekt unter ‚Mitglieder‘.“ Reiter „System“: „Systemrollen vergibst du unter ‚Benutzer‘.“ (mit Link).
  - Projekt › Mitglieder: Spalte bzw. Feld „Projektrolle“ statt „Rolle“.
- Test: Vitest/Snapshot der Texte ist nicht nötig; im Browser DE und EN ansehen.

Nicht gewählt (später möglich): Projekte der Person mit Projektrolle direkt in „Person ändern“ bearbeiten.

---

## F4.2 Chrome füllt das Suchfeld in `/admin/users` weiterhin aus (erledigt 2026-11-17)

- Gefunden (2026-11-17, Bob bei der Browser-Prüfung von F4.1): Nach dem Laden von `/admin/users` steht die E-Mail-Adresse des angemeldeten Kontos im Suchfeld, die Liste ist dadurch leer. Der Fix aus F3.10 (`name="people-filter"`, `autocomplete="off"`, `data-1p-ignore`, `data-lpignore`, inneres Feld `type="search"`) verhindert es nicht; Chrome ignoriert `autocomplete="off"`.
- Vermutung: Chrome hält das Feld (erstes Textfeld der Seite, gespeicherte Anmeldedaten für `localhost:3000`) für ein Benutzernamen-Feld.
- Ursache: Die Layouts halten den Dialog „Passwort bestätigen“ (`PasswordPromptModal`) auf jeder Seite bereit, auch geschlossen mit seinem Feld `current-password`. Chrome bildet aus allen Feldern ohne Formular ein Formular und nimmt das erste Textfeld davor, die Suche, als Benutzernamen.
- Fix: Das Passwortfeld gibt es nur, solange der Dialog offen ist (bis er ausgeblendet ist), und dann in einem eigenen Formular mit verstecktem Feld `autocomplete="username"` (E-Mail der angemeldeten Person). Test `tests/nuxt/password-prompt.test.ts`. Im Browser: Suchfeld bleibt leer, auch bei offenem Dialog.

## F4.3 Seitenleiste: Systemrolle unter der E-Mail-Adresse (erledigt 2026-11-17)

- Gewünscht (Oliver, 2026-11-17): In der Seitenleiste unten (Benutzerknopf, `layouts/default.vue`, `UserLine`) unter Name und E-Mail-Adresse auch die Systemrolle der angemeldeten Person anzeigen.
- Fix (nur Frontend): Rollenname über `useRoleName(viewer.role)` (der Viewer liefert `role { key names }` bereits), DE/EN aus dem Backend. Form (eigene Zeile oder Badge wie in `/admin/users`) bei der Umsetzung im Browser festlegen.
- Umgesetzt: eigene Zeile unter der E-Mail-Adresse (`UserLine` mit `detail`), Name aus dem Backend. Name, E-Mail und Rolle werden mit „…“ gekürzt, damit der Pfeil des Menüknopfs nichts mehr überdeckt (vorher lief die E-Mail-Adresse darunter). Test `tests/nuxt/user-line.test.ts`.

## F4.4 Rechte-Übersicht: abgeschnittene Spalten ohne Hinweis (Olivers Befund 1.2, offen)

- Gefunden (Oliver 2026-11-16, Claude 2026-11-17 nach F3.10): Bei einer Fensterbreite um 1036 px ist die letzte Rollen-Spalte rechts abgeschnitten („te…“). Man sieht nicht, dass die Tabelle seitlich scrollt; die Scrollleiste sitzt erst am Ende der langen Tabelle (25 Zeilen). Daher „ganze Rollen-Spalten fehlen“.
- Fix (nur Frontend, `components/role/RightsMatrix.vue`):
  - Spaltenköpfe kompakt: Rollenname höchstens zwei Zeilen, Spalten schmal (Inhalt ist nur ✓/–), damit 6 bis 8 Rollen ohne Scrollen passen.
  - Wenn es trotzdem nicht passt: sichtbarer Hinweis am Rand (Schatten rechts/links, solange weitergescrollt werden kann) und Kopfzeile `position: sticky` beim senkrechten Scrollen; erste Spalte bleibt stehen (wie geplant).
  - Optional: Scrollleiste oben zusätzlich (oder die Tabelle in einem Rahmen mit fester Höhe, damit die untere Scrollleiste sichtbar ist). Lösung im Browser bei 1024, 1280 und Telefonbreite prüfen.

## F4.5 Eigene Tasks lassen sich in der Oberfläche nicht ändern (offen, Fehler)

- Gefunden (Claude 2026-11-17, Abnahme 1.4 und 2.7): Ben mit Projektrolle „redaktor - test“ (Planen, Mitarbeiten) legt in Sommerfest 2027 einen Task an. Danach gibt es für ihn weder im Karten-Menü noch im Task-Detail „Ändern“, und „Zugewiesen an“ ist nur Anzeige. Gleiches gilt für Mitglieder.
- Ursache: Das Frontend fragt nur Projektrechte ab (`can('TASK_UPDATE')`, `can('TASK_ASSIGN')` in `kanban.vue`, `projects/[id]/index.vue`, `task/Detail.vue`). `task.updateOwn` und `task.assignSelf` gelten aber **je Task** (eigener Task = `created_by`), das kann `viewerRights` des Projekts nicht ausdrücken. Das Backend erlaubt es (Policy), die Oberfläche bietet es nicht an.
- Fix:
  - Backend: Felder am Typ `Task`: `viewerCanUpdate: Boolean!`, `viewerCanAssign: Boolean!` (darf andere zuweisen), `viewerCanAssignSelf: Boolean!`, `viewerCanDelete: Boolean!` — aus denselben Policies (`TaskPolicy::update`, `Rights::mayAssignTask`), ohne N+1 (Rechte je Projekt sind im Request zwischengespeichert; `created_by` ist am Task).
  - Frontend: Karten-Menü, Task-Detail und Formular nutzen diese Felder statt `can('TASK_UPDATE')`/`can('TASK_ASSIGN')`. `useAssigneeChoice` nimmt `viewerCanAssign`/`viewerCanAssignSelf` des Tasks (beim Anlegen weiterhin die Projektrechte).
- Tests: Feature-Test der Felder für Owner, Mitglied (eigener und fremder Task), Gast, Lesend; Komponententest: Mitglied sieht „Ändern“ beim eigenen Task, nicht beim fremden.
