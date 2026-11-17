# Abnahme Meilenstein M2 „Sicher löschen“ (S5, S6)

Stand: 2026-11-17, Code bis `764304f`. Geprüft in Chrome durch die Planung (Claude) mit frischen Demo-Daten; Fixes in `docs/fix-m2.md` (F5). Rolle: Checkliste für Oliver.
Personen aus den Demo-Daten: Anna (Admin), Ben (Owner Website-Relaunch), Chiara (Manager Website-Relaunch, Owner Sommerfest 2027), David (Mitglied). Je Punkt: ✅ passt, ❌ Fehler (→ F5.x), ⏸ für Oliver.

**Zwischenergebnis:** 38 ✅, 2 ❌ (F5.1, F5.2), 3 Entscheidungen (F5.3–F5.5), 4 ⏸ für Oliver.

---

## 1. Löschen mit Rückgängig (Oberfläche)

- [x] ✅ Task löschen: keine Bestätigung, Toast „Gelöscht“ mit „Rückgängig“, etwa 8 Sekunden (nach 6 s sichtbar, nach 10 s weg).
- [x] ✅ „Rückgängig“ holt den Task ohne Neuladen zurück, am Ende seiner Kanban-Spalte.
- [ ] ❌ Tastatur: Enter auf „Rückgängig“ funktioniert; aber nach dem Löschen im Task-Detail landet der Fokus auf `body`, nicht auf „Rückgängig“. → F5.1
- [x] ✅ ToDo und Milestone löschen und rückgängig (Milestone-/ToDo-Menü).
- [ ] ❌ Kommentar löschen im offenen Task-Detail: „Rückgängig“ im Toast ist hinter dem modalen Detail nicht klickbar. → F5.1
- [x] ✅ Projekt löschen und „endgültig löschen“ fragen weiter nach (Name bzw. Anzahl).
- [x] ✅ Lücke schließt sich (Kanban-Positionen, Milestone-Positionen lückenlos).
- [x] Entscheidung: „Löschen“ fehlt im Menü der Kanban-Karte (nur im Detail) → ins Menü aufnehmen. → F5.3

## 2. Papierkorb im Projekt (`/projects/:id/trash`)

- [x] ✅ Reiter „Papierkorb“ für Ben (Owner) und Chiara (Manager); für David (Mitglied) kein Reiter, direkter Aufruf leitet um.
- [x] ✅ Milestone mit Kindern löschen: ein Eintrag „Milestone · mit 2 ToDos, 1 Task“, gelöscht von, wann; älteste zuerst.
- [x] ✅ Vorher einzeln gelöschter Task bleibt eigener Eintrag und kommt beim Wiederherstellen des Milestones nicht mit.
- [x] ✅ Wiederherstellen des Milestones: alles zurück; Position am Ende (Backend). Anzeige folgt dem Datum → Entscheidung: nach Position. → F5.4
- [x] ✅ Task wiederherstellen, dessen Milestone im Papierkorb liegt: „Wiederherstellen nicht möglich — Zuerst muss der Milestone „Inhalte migriert“ aus dem Papierkorb zurück.“
- [x] ✅ „Endgültig löschen“ mit Bestätigung, danach weg (Abfrage liefert `null`).
- [x] ✅ „Papierkorb leeren“: Bestätigung „2 Einträge werden endgültig gelöscht …“, danach „Der Papierkorb ist leer.“
- [x] ✅ Kommentar-Eintrag mit Auszug als Titel.
- [x] ✅ Texte EN vollständig (Code geprüft), hell geprüft, schmales Fenster ohne seitliches Scrollen.

## 3. Gelöschtes ist überall weg

- [x] ✅ Kanban, Zeitachse, ToDo-Liste: gelöschte Tasks fehlen.
- [x] ✅ Davids „Meine Tasks“ und Dashboard ohne gelöschten „Mockup Desktop“, Fortschritt „1 von 3“.
- [x] ✅ Fortschritt von ToDo, Milestone und Projekt zählt Gelöschtes nicht.
- [x] ✅ ToDo „Inhaltsinventar erstellen“ wird nach Löschen des letzten offenen Tasks erledigt, nach Wiederherstellen wieder offen.
- [ ] ⏸ MCP `get_project`: das verbundene MCP zeigt auf eine andere Instanz (echte Projekte), nicht auf die Demo-DB. Für Oliver.
- [ ] ⏸ Keine Zuweisungs-Mail beim Wiederherstellen (Mailpit). Für Oliver.

## 4. Zweites Fenster und Revision

- [x] ✅ Zwei Tabs: Löschen in A → B zeigt „Das Projekt wurde inzwischen geändert“ und aktualisiert.
- [x] ✅ Wiederherstellen in A → A ohne Hinweis, B aktualisiert nach höchstens 30 s.
- [x] ✅ Revision: Löschen +1, Wiederherstellen +1, Milestone mit Kindern genau +1, endgültig löschen 0, Archivieren/Zurückholen 0 (per `planerRevision` abgelesen, 1 → 19 nachgerechnet).

## 5. Archiv

- [x] ✅ Archivieren in den Einstellungen mit Bestätigung (als Anna mit `projects.manageAny`).
- [ ] ❌ Projekt fehlt in Seitenleiste, „Meine Tasks“, offenen Tasks und anstehenden ToDos und steht in der Projektliste unter „Archiv (1)“ — aber die Projekt-Kacheln im Dashboard zeigen es weiter. → F5.2
- [x] ✅ Im archivierten Projekt: Hinweisleiste, Task-Detail nur lesbar (kein Ändern, Löschen, Kommentarfeld); `viewerCan*` alle false; Rechte nur Archivieren, Wiederherstellen, Export.
- [x] ✅ Papierkorb im archivierten Projekt: nur „Wiederherstellen“ (funktioniert), kein „Endgültig löschen“/„Leeren“; Mutationen abgelehnt mit „Das Projekt ist archiviert und nur lesbar. Hole es zuerst zurück.“
- [x] ✅ Auch Anna (Admin) im archivierten Projekt nur lesen.
- [ ] ⏸ MCP-Schreibwerkzeug auf archiviertes Projekt (siehe 3). Für Oliver.
- [x] ✅ „Zurückholen“ (ohne Rückfrage): sofort wieder in der Seitenleiste und bearbeitbar.
- [x] ✅ David (Mitglied) hat im archivierten Projekt nur `PLANER_EXPORT`, kein „Archivieren“/„Zurückholen“.

## 6. Gelöschte Projekte (`/trash`) und Grundregel

- [x] ✅ Chiara löscht Sommerfest 2027 (Bestätigung): weg aus Liste und Seitenleiste; Menüpunkt „Papierkorb“ erscheint.
- [x] ✅ `/trash` zeigt „Projekt · mit 3 Milestones, 2 ToDos, 3 Tasks“; David sieht keinen Menüpunkt.
- [x] ✅ Chiara (einzige Owner) lässt sich als Anna auf „Lesend“ setzen, solange Sommerfest im Papierkorb liegt.
- [x] ✅ Anna stellt Sommerfest wieder her: Anna wird Owner (Ersteller-Rolle), Chiara und David bleiben Mitglieder; Seitenleiste ohne Neuladen.
- [x] ✅ Anna sieht `/trash` (`projects.manageAny`).

## 7. Rechte

- [x] ✅ Standard: Owner Archivieren/Wiederherstellen/Endgültig; Manager Archivieren/Wiederherstellen; Mitglied und Gast nichts.
- [x] ✅ Manager (Chiara): kann wiederherstellen, „Endgültig löschen“ und „Leeren“ fehlen in der Oberfläche und werden abgelehnt.
- [x] ✅ Gast (David) und Systemrolle „Lesend“ (Chiara): Löschen, Wiederherstellen, Endgültig löschen abgelehnt, keine Rechte, leerer Papierkorb.
- [x] ✅ Kommentar: Autor (David) löscht und holt zurück; fremden Kommentar darf er nicht löschen. Ben (Moderation) löscht fremde.
- [x] Entscheidung: Ablehnungen kommen als englisches „This action is unauthorized.“ → übersetzen. → F5.5
- [x] Eigene Projektrolle mit/ohne `trash.restore`/`trash.purge`: nicht in der Oberfläche geprüft, abgedeckt durch `TrashGraphQLTest` (eigene Rollen).

## 8. S6 Bereinigungen

- [x] ✅ Projektbeschreibung mit Editor (Formatierung); Fett gespeichert als Rich Text; Kopf zeigt Klartext auf einer Zeile.
- [x] ✅ Profil > E-Mail-Benachrichtigungen: aus/an gespeichert (`notifyByEmail`), nach Neuladen gleich.
- [ ] ⏸ Registrierung ablehnen: keine wartende Registrierung in den Demo-Daten. Für Oliver (neues Konto registrieren, als Anna ablehnen).

## Nebenbefund

Das Backend (`localhost:8000`) fiel während der Abnahme zweimal ohne Eintrag im Laravel-Log aus; Ursache offen (Terminal-Ausgabe prüfen).

---

Ergebnis: _offen_ (nach F5 Nachprüfung durch die Planung, dann ⏸-Punkte durch Oliver)
