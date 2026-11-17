# Abnahme Meilenstein M2 „Sicher löschen“ (S5, S6)

Stand: 2026-11-17, vorbereitet nach `docs/auftrag-m2.md` (Code bis `9743900`, M2 noch nicht umgesetzt). Rolle: Checkliste für Oliver, nach Abschluss von M2 durch die Umsetzung. Die Planung gleicht die Liste mit dem fertigen Stand ab, bevor die Abnahme beginnt.
Vorher durch die Umsetzung: alle Prüfungen grün (`php artisan test`, `composer test:mariadb`, `npm test` in `frontend/` und `tools/planer/`, Lint, Typecheck, Build), dann lokal `php artisan migrate:fresh --seed --seeder=DemoSeeder`.

Personen aus den Demo-Daten: Anna (Admin, in keinem Projekt Mitglied), Ben (Owner Website-Relaunch), Chiara (Manager Website-Relaunch, Owner Sommerfest 2027), David (Mitglied in beiden). Für Gast und Lesend vor dem Test eine Person umstellen. Je Punkt: ✅ passt, ❌ mit kurzer Notiz. Test immer in Chrome.

---

## 1. Löschen mit Rückgängig (Oberfläche)

- [ ] Als Ben in Website-Relaunch einen Task löschen: keine Bestätigung, Toast „Gelöscht“ mit „Rückgängig“, etwa 8 Sekunden sichtbar.
- [ ] „Rückgängig“ holt den Task zurück, ohne Neuladen der Seite; er steht am Ende seiner Kanban-Spalte.
- [ ] Den Toast mit der Tastatur erreichen (Tab) und „Rückgängig“ mit Enter auslösen.
- [ ] Dasselbe für ToDo, Milestone und Kommentar (je einmal löschen und rückgängig).
- [ ] Projekt löschen und „endgültig löschen“ fragen weiter nach (Bestätigung mit Namen bzw. Anzahl).
- [ ] Nach dem Löschen schließt sich die Lücke: die Reihenfolge der übrigen Milestones/ToDos/Tasks bleibt lückenlos, auch nach Neuladen.

## 2. Papierkorb im Projekt (`/projects/:id/trash`)

- [ ] Projektmenü zeigt „Papierkorb“ für Ben und Chiara; für David nur, wenn seine Rolle `trash.restore` oder `trash.purge` hat (Standard: prüfen und notieren).
- [ ] Einen Milestone mit ToDos und Tasks löschen: im Papierkorb **ein** Eintrag mit „mit N ToDos, M Tasks“, gelöscht von, wann. Älteste Einträge zuerst.
- [ ] Vorher einen Task desselben Milestones einzeln löschen: er steht als eigener Eintrag und kommt beim Wiederherstellen des Milestones **nicht** mit.
- [ ] Wiederherstellen des Milestones: Milestone, ToDos, Tasks, Kommentare sind zurück, der Milestone steht am Ende der Liste.
- [ ] Einzelnen Task wiederherstellen, dessen ToDo noch im Papierkorb liegt: abgelehnt mit Meldung, die das ToDo nennt.
- [ ] „Endgültig löschen“ eines Eintrags: Bestätigung, danach weg; nicht mehr wiederherstellbar.
- [ ] „Papierkorb leeren“: Bestätigung nennt die Anzahl, danach „Der Papierkorb ist leer.“
- [ ] Kommentar im Papierkorb: Titel ist ein kurzer Auszug (höchstens 80 Zeichen, ohne Formatierung).
- [ ] Papierkorb-Seite in EN, hell/dunkel und in Telefonbreite lesbar und bedienbar.

## 3. Gelöschtes ist überall weg

Einen offenen Task mit Fälligkeit, der David zugewiesen ist, löschen. Dann prüfen:

- [ ] Kanban, Zeitachse, ToDo-Liste: Task fehlt.
- [ ] Davids „Meine Tasks“ und Dashboard: Task fehlt, Zähler stimmen.
- [ ] Fortschritt von ToDo, Milestone und Projekt zählt ihn nicht.
- [ ] Hatte das ToDo nur noch diesen offenen Task, ist es jetzt erledigt; nach Wiederherstellen wieder offen.
- [ ] Planer-Datei bzw. MCP (`get_project`): Task fehlt.
- [ ] Wiederherstellen schickt David **keine** Zuweisungs-Mail (Mailpit, falls verfügbar; sonst ⏸ wie in M1).

## 4. Zweites Fenster und Revision

- [ ] Projekt in zwei Fenstern offen (Ben und Chiara). Ben löscht einen Task: bei Chiara erscheint der Hinweis „inzwischen geändert“ bzw. die Ansicht aktualisiert sich (S3f).
- [ ] Ben macht „Rückgängig“: bei Ben selbst kein Hinweis „inzwischen geändert“, bei Chiara wieder Aktualisierung.
- [ ] Milestone mit Kindern löschen erhöht die Revision genau um 1; Wiederherstellen um 1; endgültig löschen und Archivieren/Zurückholen gar nicht (Revision in den Projekteinstellungen bzw. per `planerRevision` ablesen).

## 5. Archiv

- [ ] Als Ben Website-Relaunch archivieren (Projekteinstellungen, mit Bestätigung).
- [ ] Projekt verschwindet aus Dashboard, Seitenleiste und Davids „Meine Tasks“; steht in der Projektliste im eingeklappten Bereich „Archiv“.
- [ ] Im archivierten Projekt: Hinweisleiste „Archiviert, nur lesbar“; keine Knöpfe zum Anlegen, Ändern, Löschen, Zuweisen, Kommentieren, auch nicht bei eigenen Tasks.
- [ ] Papierkorb im archivierten Projekt: Wiederherstellen geht, endgültig löschen und leeren nicht.
- [ ] Als Anna (`projects.manageAny`) im archivierten Projekt: ebenfalls nur lesen.
- [ ] MCP: Schreibwerkzeug (z. B. `set_task_status`) auf das archivierte Projekt wird mit klarer Meldung abgelehnt; `list_projects` mit `archived` zeigt es.
- [ ] „Zurückholen“: Projekt ist wieder überall sichtbar und bearbeitbar, ohne Neuladen der Seitenleiste.
- [ ] Ohne `project.archive` (David) gibt es weder „Archivieren“ noch „Zurückholen“.

## 6. Gelöschte Projekte (`/trash`) und Grundregel „jedes Projekt hat einen Owner“

- [ ] Als Chiara Sommerfest 2027 löschen (Bestätigung). Es verschwindet aus Liste und Seitenleiste; Mitgliedschaften bleiben (nach Wiederherstellen sichtbar).
- [ ] Menüpunkt „Papierkorb“ (global) erscheint für Chiara; `/trash` zeigt Sommerfest 2027. Für David erscheint er nicht.
- [ ] Chiara stellt Sommerfest 2027 wieder her: alles zurück, Mitglieder unverändert.
- [ ] Gelöschtes Projekt blockiert nichts: Chiara ist dort einzige Owner. Als Anna Chiaras Systemrolle auf „Lesend“ setzen: geht, solange Sommerfest im Papierkorb liegt.
- [ ] Danach stellt Anna Sommerfest wieder her: Anna wird Mitglied mit der Ersteller-Rolle, weil sonst niemand mehr „Projekt löschen“ wirksam hat. (Chiara danach zurück auf ihre Rolle setzen.)
- [ ] Anna sieht in `/trash` alle gelöschten Projekte und kann sie endgültig löschen; Bestätigung mit Namen.

## 7. Rechte

- [ ] Eigene Projektrolle ohne „Wiederherstellen“ (`trash.restore`): Person sieht keinen Papierkorb bzw. keine Knöpfe; direkter Aufruf `/projects/:id/trash` gesperrt.
- [ ] Eigene Projektrolle mit `trash.restore`, ohne `trash.purge`: kann wiederherstellen, aber nicht endgültig löschen oder leeren.
- [ ] Gast und Systemrolle „Lesend“: weder löschen noch wiederherstellen noch endgültig löschen.
- [ ] Kommentar: Autorin und Personen mit „Fremde Kommentare löschen“ können ihn wiederherstellen; andere nicht.
- [ ] Rechte-Übersicht `/admin/roles`: „Archivieren“, „Wiederherstellen“, „Endgültig löschen“ stehen bei den erwarteten Standardrollen.

## 8. S6 Bereinigungen

- [ ] Projektbeschreibung: Editor mit Formatierung (wie bei Milestones) in den Projekteinstellungen; vorhandene Beschreibung der Demo-Projekte steht als Absatz drin.
- [ ] Profil > Benachrichtigungen: Schalter „Mails“ speichern und nach Neuladen gleich.
- [ ] Benutzerverwaltung: eine wartende Registrierung ablehnen, die Liste aktualisiert sich ohne Fehler.

---

Ergebnis: _offen_
