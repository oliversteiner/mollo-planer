# Abnahme Meilenstein M4 „Datei-Abgleich“ (I1–I5)

Stand: 2026-11-18, geprüft in Chrome durch die Planung an `cc56329` mit frischen Demo-Daten (vorher M3 geprüft). Fixes in `docs/fix-m4.md` (F7). Rolle: Checkliste für Oliver.
Personen: Anna (Admin), Ben (Owner Website-Relaunch), Chiara (Owner Sommerfest 2027), David (Mitglied). Je Punkt: ✅ passt, ❌ Fehler (→ F7.x), ⏸ für Oliver.

**Ergebnis der Prüfung:** 27 ✅, 6 Befunde (F7.1 wichtig, F7.2–F7.6 klein), 5 ⏸ für Oliver. Offen außerdem `next-work/03` (`DevelopmentHistorySeeder`).

---

## 1. Spezifikation und Grenzen (§2.1, §2.2)

- [x] ✅ Export enthält `project.description` als Markdown, Rollen als Schlüssel (`owner`, `manager`, `member`).
- [x] ✅ Neues Projekt aus Datei mit leeren Milestones (`todos: []`) wird angenommen.
- [x] ✅ Grenze: 51. Task in einem ToDo abgelehnt mit „Ein ToDo hat höchstens 50 Tasks.“
- [x] ✅ Beschreibung über 5000 Zeichen und HTML in der Datei werden mit Pfad abgelehnt.
- [ ] ❌ Meldungen der Datei-Prüfung teils englisch („Maximum string length is 5000, found 5001“, „is not a member of the project“). → F7.3
- [ ] ⏸ `php artisan planer:check-limits` auf der laufenden Instanz vor dem Deployment (für Oliver).

## 2. Reihenfolge der Tasks (§2.3)

- [x] ✅ Zeitachse zeigt Tasks im ToDo nach `position` (Reihenfolge der Datei).
- [x] ✅ „Nach oben“ im Task-Menü der Zeitachse wirkt.
- [ ] ❌ Task-Menü der Zeitachse hat nur „Nach oben/unten“, kein „Ändern“/„Löschen“ (vgl. F5.3). → F7.6

## 3. Export (I1)

- [x] ✅ `exportPlan` liefert kanonischen Text (`format`, `revision`, Projekt, Mitglieder, Labels mit `names`, Milestones …).
- [x] ✅ Mitglied (David) darf exportieren; archiviertes Projekt lässt sich exportieren.
- [x] ✅ Gemischt verschachtelte Liste und Fett/Kursiv/Link kommen als kanonisches Markdown heraus.
- [ ] ⏸ Downloads „Als planer.json“ und „Als Markdown“ (Sprache, Übersetzungen in der Legende) öffnen (Dateien auf dem Rechner, für Oliver).
- [ ] ❌ Beschriftung „Sprache der Markdown-Ansicht“ läuft in das Auswahlfeld. → F7.5

## 4. Import (I2)

- [x] ✅ Trockenlauf mit frischem Export: keine Änderungen; echter Import ohne Änderungen: Revision bleibt.
- [x] ✅ Geänderte Datei: Trockenlauf listet Anlegen/Ändern/Löschen mit Pfad; Eintragen ergibt Revision +1, neuer Task mit Formatierung, Reihenfolge der Datei, `idMap` `k-new-1` → echte ID.
- [x] ✅ `full`: weggelassene Elemente als **ein** Vorgang im Papierkorb (ToDo „Gäste einladen“ mit 2 Tasks), wiederherstellbar.
- [x] ✅ Falsche Revision: „Die Datei hat die Revision 1, das Projekt ist bei 5. Exportiere es zuerst neu.“
- [x] ✅ Unbekannte E-Mail, unbekannte Rolle, Selbstentfernung, kein Owner mehr: jeweils abgelehnt mit Pfad und verständlichem Text.
- [x] ✅ Rechte: David (Mitglied) darf nicht importieren; archiviertes Projekt: Import abgelehnt.
- [ ] ❌ **Reihenfolge-Abgleich falsch:** Nach einem Import meldet ein erneuter Trockenlauf mit dem frischen Export „MOVE“ für unveränderte Tasks; ein echter Import zählt dann die Revision hoch, `partial` meldet Verschiebungen von Elementen, die nicht in der Datei stehen. → F7.1
- [ ] ❌ Trockenlauf zeigt für neue Elemente eine echte ID (`k-16`), der Import vergibt dann eine andere (`k-17`). → F7.2

## 5. Oberfläche Import (I5)

- [x] ✅ Projekteinstellungen › „Datei eintragen …“: Datei wählen, Modus „Ganzer Plan“, Vorschau mit Anzahl Änderungen und `PlanerTree`, Hinweis auf den Papierkorb, Bestätigung „Eintragen und löschen?“, Meldung „Die Datei ist eingetragen.“
- [x] ✅ Markierung „Neu“ am neuen Task.
- [ ] ❌ Geänderter Task ohne Markierung „Geändert“; gelöschtes Element nur als „ToDo t-7“ ohne Titel; Vorschau scrollt seitlich. → F7.4
- [ ] ⏸ „Neues Projekt › Aus Datei“ in der Oberfläche (per API geprüft: neues Projekt, Revision 1, Anna als Owner).

## 6. Vorlagen aus Dateien (I5)

- [x] ✅ „Als Vorlage speichern“: Vorlage ohne Mitglieder, Fälligkeiten, Zuweisungen; alle Tasks „geplant“, Version auf „geplant“.
- [x] ✅ Vorlage aus hochgeladener Datei; eine volle Projektdatei wird dabei in eine Vorlage umgewandelt (statt abgelehnt).
- [x] ✅ Projekt aus Datei/Vorlage mit Markdown-Beschreibungen: Formatierung kommt an (offener Punkt aus M3).
- [ ] ⏸ Download einer Vorlage als Datei (für Oliver).

## 7. MCP (I3) und Demo-Daten (I4)

- [x] ✅ Demo-Daten aus Planer-Dateien: `migrate:fresh --seed` läuft, Projekte mit Revision 1, Labels, Releases.
- [ ] ⏸ MCP nur noch `list_projects`, `list_users`, `list_templates`, `export_plan`, `import_plan` (für Oliver gegen die Demo-Instanz); `docs/claude-connector.md`.
- [x] ✅ `DevelopmentHistorySeeder` auf planer-v2 (`next-work/03`, `f19cc17`).

## Nachprüfung F7 (2026-11-18, Planung, Chrome, frische Demo-Daten, `dev` bis `f1b024f`)

- [x] ✅ F7.1: Datei mit umgestellten Milestones/Tasks eingetragen, frischer Export danach ohne Änderungen; Revision bleibt; Teil-Import verschiebt nichts.
- [x] ✅ F7.2: Trockenlauf zeigt `k-new-1`, `idMap` leer; erst der Import vergibt `k-12`.
- [x] ✅ F7.3: Meldungen deutsch („Höchstens 5000 Zeichen (gefunden: 5001).“, „… ist kein Mitglied des Projekts.“, „Unbekanntes Feld.“).
- [x] ✅ F7.4: Vorschau mit „Verschoben“, „Geändert: Titel, Reihenfolge“, gelöschtes Element als „ToDo »Texte überarbeiten«“; Zeilen brechen um.
- [x] ✅ F7.5: Beschriftung „Sprache“, Hinweis darunter, keine Überlappung.
- [x] ✅ F7.6: Task-Menü der Zeitachse mit „Ändern“, „Nach unten“, „Löschen“.
- [x] ✅ `DevelopmentHistorySeeder` liest `docs/entwicklung.planer.json` (planer-v2) über `Import` (`f19cc17`); `migrate:fresh --seed` lief.
- [ ] ⏸ Bleibt für Oliver: Downloads, „Neues Projekt › Aus Datei“ in der Oberfläche, Vorlage als Datei, MCP-Werkzeuge, `planer:check-limits`.

---

Ergebnis: _bereit zur Abnahme_ (Planung: alle Befunde behoben; offen nur die ⏸-Punkte für Oliver)
