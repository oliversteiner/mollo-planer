# Fix F3 aus der Abnahme M1

Stand: 2026-11-16, Planung. Code bis `9e8f503`.
Grundlage: Abnahme M1 (Oliver und Claude in Chrome, 2026-11-16), Ergebnisse in `docs/abnahme-m1.md`.
Oliver war beim Schreiben nicht erreichbar. **Die Punkte unter „Entscheidungen“ sind Vorschläge der Planung**; sie gelten, wenn Oliver nicht widerspricht. Bob setzt sie so um.

Reihenfolge: F3.1 bis F3.4 (Fehler) → F3.5 bis F3.7 (Entscheidungen) → F3.8 bis F3.10 (Oberfläche). Ein Commit je Punkt, Tests je Punkt, Stand in `umsetzung.md`.

---

## Fehler

### F3.1 Mitglied kann keinen Task ohne Zuständige anlegen

- Gefunden: Chiara (Projektrolle Mitglied) in „Abnahmetest Claude“, Task ohne „Zugewiesen an“ → „Dafür fehlt dir die Berechtigung.“. Mit sich selbst als Zuständige geht es.
- Ursache: `GraphQL/Mutations/CreateTask.php` ruft `Rights::mayAssignTask($viewer, $project, null, null)` auch bei leerer Zuweisung auf. Wer nur `task.assignSelf` hat, bekommt `false`.
- Fix: Die Prüfung nur, wenn `assigneeId` gesetzt ist. Für das Anlegen selbst gilt `task.create`.
- Prüfen: Gleiches Muster in `UpdateTask`/`AssignTask`, falls dort eine leere Zuweisung ohne Änderung ebenfalls geprüft wird (Zuweisung aufheben bei eigenem Task muss mit `assignSelf` gehen, bei fremdem nicht).
- Test: Mitglied legt Task ohne Zuständige an (ok), mit sich selbst (ok), mit anderer Person (abgelehnt).

### F3.2 Ersteller-Rolle: Auswahl zeigt alle Projektrollen

- Gefunden: `/admin/settings` › „Projekte anlegen“ bietet Owner, Manager, Mitglied, Gast und eigene Rollen an.
- Fix: Nur Projektrollen anbieten, deren Rechte `PROJECT_DELETE` enthalten (aus `Role.rights`). Der Server lehnt andere schon ab (`setProjectCreatorRole`), Test dafür ergänzen, falls er fehlt.

### F3.3 Erstellerin wird nicht Mitglied, wenn sie Admin ist

- Gefunden: Anna (Admin) legt „Abnahmetest Claude“ mit Chiara an. Anna ist danach **nicht** Mitglied, das Projekt hat keine Person mit „Projekt löschen“.
- Ursache: `CreateProject` hängt die Erstellerin nur an, wenn sie **kein** `projects.manageAny` hat.
- Fix (siehe Entscheidung F3.5): Die Erstellerin wird **immer** Mitglied mit der Ersteller-Rolle, auch als Admin.
- Dialog „Neues Projekt“ (Plan F2 §2.2, noch offen): Die Erstellerin steht fest oben mit der Ersteller-Rolle (nicht änderbar, nicht entfernbar); in der Personenauswahl erscheint sie nicht.
- Test: Admin legt Projekt an → Mitglied mit Ersteller-Rolle; Projekt mit `members` → Erstellerin nicht doppelt.

### F3.4 Grundregel §7.3 lässt sich über die Systemrolle umgehen

- Gefunden: Anna setzt Ben auf Systemrolle „Lesend“, obwohl Ben in Website-Relaunch und testprojekt die einzige Person mit „Projekt löschen“ ist. Gespeichert; danach hat in beiden Projekten niemand außer Admins „Projekt löschen“ (die Obergrenze „Lesend“ nimmt es ihm).
- Ursache: `RoleAdministration` vergleicht `Rights::projectIdsWithHolder()` vor und nach jeder Änderung an Rollen und Funktionen. `updateUser` (Systemrolle einer Person) tut das nicht. Außerdem erlaubt `Rights::canLeaveProjectWithoutHolder()` jeder Person mit `projects.manageAny` einen Zustand ohne Owner.
- Fix (siehe Entscheidung F3.5):
  - `updateUser` und `deleteUser`: gleiche Vorher/Nachher-Prüfung wie in `RoleAdministration` (am besten dieselbe Funktion, z. B. `Rights::assertNoProjectLosesHolder(callable $change)`), Ablehnung mit Meldung, die die betroffenen Projekte nennt („Ben ist in Website-Relaunch und testprojekt die einzige Person mit ‚Projekt löschen‘. Vergib dort zuerst die Rolle an jemand anderen.“).
  - `canLeaveProjectWithoutHolder` entfällt; die Grundregel gilt auch für Admins.
- Test: je Weg (Projektrolle ändern, Mitglied entfernen, Systemrolle mit Obergrenze ändern, Person löschen, Rolle/Funktion ändern) und auch als Admin.

## Entscheidungen (Vorschlag der Planung)

### F3.5 Jedes Projekt hat immer eine Person mit „Projekt löschen“ unter seinen Mitgliedern

- Grund: Ein Projekt ohne Owner kann nur noch ein Admin verwalten; das fällt niemandem auf, bis jemand ein Recht braucht. Die Ausnahme in §7.3 („Personen mit `projects.manageAny`“) heißt künftig: Admins dürfen in jedem Projekt handeln, **nicht**: Admins dürfen ein Projekt ohne Owner hinterlassen.
- Folge: F3.3, F3.4. `docs/rollen-funktionen-rechte.md` §7.3 nachführen (macht die Planung, siehe unten).

### F3.6 Ideen in „Meine Tasks“

- Plan (Abnahme 4.4): „Meine Tasks“ und Dashboard zeigen unter „offen“ keine Ideen, geplante Tasks aber schon.
- Heute: Dashboard richtig. „Meine Tasks“ listet Ideen mit Status „Idee“.
- Vorschlag: „Meine Tasks“ zeigt Ideen **nicht**, mit einem Schalter „Ideen anzeigen“ (Zustand im Browser gespeichert, wie beim Kanban). Geplante Tasks bleiben sichtbar.

### F3.7 Ideen in einer Version, die veröffentlicht werden soll

- Heute: Eine Idee im Milestone verhindert „Veröffentlicht“ (sie ist nicht erledigt), zählt beim Fortschritt aber nicht. Das ist folgerichtig nach `spec/format-v2.md` §5.3 (nur erledigte Tasks).
- Vorschlag: **so lassen**. Die Meldung sagt es aber deutlich (F3.8).

## Oberfläche

### F3.8 Meldungen beim Veröffentlichen nennen die Tasks

- `ReleaseRules::checkMilestone`: statt der allgemeinen Meldung die Titel der betroffenen Tasks (höchstens 5, dann „und 3 weitere“), getrennt nach Grund:
  - „Noch nicht erledigt: Mockup Desktop, Hosting bestellen. Erledige sie oder verschiebe sie in einen anderen Milestone.“ (Ideen und Geplantes mit Status in Klammern, z. B. „Task Idee (Idee)“)
  - „Ohne Commit: Farben festlegen. Trage einen Commit oder „ohne“ ein.“
- Texte DE/EN in `lang/*/release.php`. Tests für beide Fälle und die Kürzung.

### F3.9 Auswahlen bieten nur an, was erlaubt ist

- Task „Zugewiesen an“: Wer nur `task.assignSelf` hat, sieht nur sich selbst (und „niemand“). Heute werden alle Mitglieder angeboten, Speichern endet in „Dafür fehlt dir die Berechtigung.“
- Mitglieder › Rolle: Rollen mit „Projekt löschen“ nur anbieten, wenn die Person `owner.manage` hat (heute bekommt Chiara als Manager „Owner“ angeboten und dann eine Ablehnung).
- Grundlage: `Project.viewerRights` und `Role.rights`, keine neue Abfrage.

### F3.10 Kleinere Befunde

- **Rechte-Übersicht, Spalten:** Oliver sah fehlende Rollen-Spalten, Claude bei breitem Fenster alle. Prüfen bei 1024 und 1280 px Breite: Die Tabelle muss sichtbar seitlich scrollen (Schatten oder Scrollleiste am Rand), erste Spalte bleibt stehen. Falls bei schmalem Fenster Spalten ohne Scrollmöglichkeit abgeschnitten werden: beheben.
- **Hinweis unter der System-Übersicht:** nennt „Administration“ und „lesend“; Rollennamen aus dem Backend nehmen (`useRoleName`), nicht fest im Text.
- **Leere Datumsfelder** („Fällig“ in Milestone-, ToDo- und Task-Formular): Beschriftung und Platzhalter des Browsers liegen übereinander. Beschriftung immer oberhalb (wie bei gefüllten Feldern) oder Platzhalter ausblenden.
- **Suchfeld `/admin/users`:** Chrome füllt es mit einer gespeicherten E-Mail-Adresse, die Liste ist dann leer. `autocomplete="off"` und ein Name, der nicht nach E-Mail aussieht.

## Nicht in F3

- Englische Label-Namen klein („design“, „blocked“): kommt mit S7 (`docs/auftrag-m3.md` §3.2).
- Die Punkte, die Oliver noch selbst prüft (Neuladen in zwei Fenstern, Mail, Tastatur, Telefonbreite, eigene Rolle „Redaktion“, Funktionen, „Standard wiederherstellen“, letzte Person mit Benutzerverwaltung, fremde Kommentare). Was dort auffällt, kommt als F4.

## Abschluss

Alle Prüfungen grün, `migrate:fresh --seed --seeder=DemoSeeder`, im Browser ansehen, Stand nachführen, dann melden. Danach prüft Oliver seine offenen Punkte und die Fixes; erst dann M2.
