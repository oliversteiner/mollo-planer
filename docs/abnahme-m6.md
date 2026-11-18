# Abnahme Meilenstein M6 „Assistent und Ideen“ (B5a, B6)

Stand: 2026-11-18, geprüft in Chrome durch die Planung an `5a5d55a` mit frischen Demo-Daten und echtem Claude (lokaler Schlüssel, 5 Entwürfe). Fixes in `docs/fix-m6.md` (F9). Rolle: Checkliste für Oliver.
Personen: Anna (Admin, `ideas.viewAny`), Ben (Systemrolle Mitglied, Owner Website-Relaunch), Chiara (Projektleitung, `projects.create`), David (Mitglied, im Projekt Mitglied). Je Punkt: ✅ passt, ❌ Fehler (→ F9.x), ⏸ für Oliver.

**Ergebnis der Prüfung:** 27 ✅, 8 Befunde (F9.1 und F9.2 wichtig: Mitglieder können nicht eintragen, neue Projekte aus Vorlagen scheitern; F9.4 und F9.7 mittel; Rest klein), 6 ⏸.

**Abweichung von Bob (zur Bestätigung):** Das Schema für Claude enthält keine `members` und keine `release` („compiled grammar too large“ der API). Mitglieder eines neuen Projekts leitet der Server aus den Zuweisungen ab; eine Versionsnummer setzt der Assistent nicht. Die Planung hält das für vertretbar.

---

## 1. Rechte

- [x] ✅ Standardbelegung: `assistant.use` und `ideas.create` für Admin, Projektleitung, Mitglied; `ideas.viewAny` nur Admin; `projects.create` nicht für Systemrolle Mitglied.
- [ ] ❌ Ein Mitglied (David: `task.create`, `task.assignSelf`) kommt im Assistenten bis zur Vorschau, „Jetzt eintragen“ endet mit „Dafür fehlt dir die Berechtigung.“: `importPlan` verlangt `planer.import`, auch mit `appendOnly`. → F9.1

## 2. Ideen (B6a)

- [x] ✅ `createIdea` mit Projekt und Entwurf; ungültiger Entwurf abgelehnt mit Pfad („revision: Pflichtfeld fehlt“, siehe F9.6 zum Wort „Datei“).
- [x] ✅ Ben sieht Davids Idee nicht (Liste leer, `idea` null), `authorId` ohne `ideas.viewAny` abgelehnt.
- [x] ✅ Anna (`ideas.viewAny`) sieht sie mit Autor, `viewerCanUpdate: false`, Ändern abgelehnt.
- [x] ✅ Löschen → Papierkorb (`IDEA` in `trash()`), Wiederherstellen.
- [x] ✅ Eine Idee zählt nicht zur Revision des Projekts.
- [x] ✅ `importPlan` mit fremder `ideaId`: „Diese Idee gibt es nicht.“
- [x] ✅ Seite `/ideas`: Filter Offen/Angenommen/Verworfen/Alle, Karten mit Datum, Projekt, Umfang des Entwurfs, „Mit dem Assistenten planen“, „Idee bearbeiten“, „Verwerfen“, „Löschen“; Menüpunkt „Ideen“.
- [ ] ❌ „In Ideen ablegen“ im Assistenten speichert sofort mit dem Projektnamen als Titel („Website-Relaunch“), ohne Dialog. → F9.5
- [ ] ⏸ Einplanen einer Idee (`importPlan(ideaId)` → `adopted`, `todos.idea_id`) im echten Lauf: wegen F9.1 als David nicht möglich; nach F9 nachprüfen.

## 3. KI-Kontext (B6b)

- [x] ✅ Ben setzt Text, `updatedBy`, Revision unverändert; 20 001 Zeichen abgelehnt („… höchstens 20000 Zeichen (jetzt 20001)“).
- [x] ✅ David liest, Ändern abgelehnt; archiviertes Projekt: „Das Projekt ist archiviert und nur lesbar.“
- [x] ✅ Karte in den Projekteinstellungen: Text, Datei wählen (`.txt`/`.md`, 100 KB), Zähler „11 von 20.000 Zeichen“, Schalter „Für externe Werkzeuge freigeben“, „Alles entfernen“.
- [ ] ❌ Der Hinweis „Alle im Projekt sehen diesen Text, und er geht an den KI-Dienst. Keine Zugangsdaten oder vertraulichen Daten.“ fehlt. → F9.8
- [ ] ⏸ Datei hochladen (Kodierung, Grenze) und MCP `get_project_ai_context` mit/ohne Freigabe.

## 4. `importPlan`-Erweiterungen (B5a Teil 1)

- [x] ✅ `appendOnly`: Umbenennen eines Ankers → Fehler `append_only` mit Pfad; ohne `appendOnly` geht es als UPDATE durch; neuer Task zwischen Ankern nur CREATE.
- [x] ✅ `templateId` mit `projectId` abgelehnt („Eine Vorlage gibt es nur für ein neues Projekt.“).
- [x] ✅ `templateId` ohne `projectId` (ohne `appendOnly`): Projekt mit Vorlage „Event“, Revision 1, Chiara Owner, Labels der Vorlage, `projectCount` 1 → 2.
- [ ] ❌ `templateId` mit `appendOnly`: „Der Assistent ergänzt nur. ‚project.labels‘ würde geändert.“ Bei einem neuen Projekt gibt es nichts zu schützen. → F9.2

## 5. Assistent (B5a, echter Claude)

- [x] ✅ Menüpunkt „KI-Assistent“, `/assistant?project=1` zeigt „Ergänzt wird: Website-Relaunch“, leerer Zustand mit Erklärung.
- [x] ✅ Ben, „Kontaktformular absichern … Chiara bis Ende Dezember“: Entwurf in rund 20 s, neues ToDo mit 3 Tasks (`planned`, Fälligkeiten bis 30.12., Chiara zugewiesen, Label „Feature“), Antwort in zwei bis vier Sätzen.
- [ ] ❌ Dabei den Milestone („Go-live“) selbst gewählt, ohne Auswahl, obwohl die Anfrage keinen nannte (Konzept §5: „nie still wählen“). → F9.4
- [x] ✅ Streichen eines Tasks in der Vorschau: Zähler aktualisiert, neuer Trockenlauf.
- [x] ✅ „Jetzt eintragen“: Meldung, Zeitachse zeigt das ToDo unter Go-live mit 2 Tasks.
- [x] ✅ David, „Impressum prüfen, Ben soll das machen“: Rückfrage mit Knöpfen (ToDo „Texte überarbeiten“ empfohlen, neues ToDo, anderer Milestone, „Als Idee festhalten“); fremde Zuweisung wegen `task.assignSelf` geleert und erklärt.
- [x] ✅ Chiara, neues Projekt „Tag der offenen Tür“: Vorschlag Vorlage „Event“ (empfohlen), „Ohne Vorlage“, „Als Idee behalten“; Entwurf mit 2 Milestones, 6 ToDos, 17 Tasks auf dem Gerüst der Vorlage, Datum aus der Anfrage abgeleitet.
- [ ] ❌ Diese Vorschau meldet den Fehler aus F9.2, „Jetzt eintragen“ gesperrt: ein neues Projekt aus einer Vorlage lässt sich über den Assistenten nicht anlegen. → F9.2
- [x] ✅ KI-Kontext wirkt: die Antworten folgen der „Sie-Form“ aus dem Kontext von Website-Relaunch.
- [ ] ❌ Kein Einstieg „Mit dem Assistenten ergänzen“ im Projekt (nur über das Menü bzw. die Adresse). → F9.3
- [ ] ❌ Der Chat-Zustand liegt in `sessionStorage` und überlebt Abmelden und Anmelden einer anderen Person im selben Tab (Chiara sah „Ergänzt wird: Website-Relaunch“ aus Davids Sitzung; mit laufendem Chat wären Nachrichten und Entwurf sichtbar). → F9.7
- [ ] ❌ Texte: „1 ToDos“, „1 Tasks“ (Vorschau, Ideen-Karten), „Eingetragen.“ ohne Umfang, gewählter Knopf zeigt die interne ID („… (t-4)“), Fehler `append_only` nennt die ID statt des Titels. → F9.6
- [ ] ⏸ Grenze 30/Stunde und 200/Tag mit Meldung (durch Bobs Tests abgedeckt; von Hand zu teuer).
- [ ] ⏸ MCP `create_idea`, `list_ideas`, `list_users` nach Konzept §8, `import_plan` mit `template_id`/`idea_id`/`append_only` (für Oliver mit dem Connector gegen die lokale Instanz).
- [ ] ⏸ Hell, Englisch, Tastatur in den Knöpfen, Telefonbreite.

---

Ergebnis: _offen_ (nach F9 Nachprüfung durch die Planung)
