# Abnahme Meilenstein M3 „Vorlagen und Labels“ (S3e, S7)

Stand: 2026-11-18, geprüft in Chrome durch die Planung an `3592f4a` mit frischen Demo-Daten (M3 und M4 umgesetzt). Fixes in `docs/fix-m3.md` (F6). Rolle: Checkliste für Oliver.

**Ergebnis der Prüfung:** 31 ✅, 3 kleine Befunde (F6.1–F6.3), 3 ⏸ für Oliver.
Vorher durch die Umsetzung: alle Prüfungen grün (`php artisan test`, `composer test:mariadb`, `npm test` in `frontend/` und `tools/planer/`, Lint, Typecheck, Build), dann `php artisan migrate:fresh --seed --seeder=DemoSeeder`.

Personen aus den Demo-Daten: Anna (Admin), Ben (Owner Website-Relaunch), Chiara (Manager Website-Relaunch, Owner Sommerfest 2027), David (Mitglied). Je Punkt: ✅ passt, ❌ mit kurzer Notiz, ⏸ für Oliver. Test in Chrome.

---

## 1. Vorlagen-Seite (`/templates`)

- [x] ✅ Menüpunkt „Vorlagen“ für alle Angemeldeten (unter „System“, auch für David). `/label-sets` leitet auf `/templates` um.
- [x] ✅ Liste zeigt die drei mitgelieferten Vorlagen mit „Mitgeliefert“, Labels, Größe des Gerüsts und „x Projekte daraus angelegt“.
- [x] ✅ Mitgelieferte Vorlage: Gerüst als Baum nur zum Ansehen; Menü nur „Duplizieren“ und „Als planer.json herunterladen“.
- [x] ✅ Englische Label-Namen korrekt (Research, Topic idea, Speakers …), Oberfläche EN geprüft, auch Vorlagen-Namen übersetzt.
- [x] ✅ Duplizieren als Anna: Kopie mit Gerüst, „von Anna Keller“, Editor öffnet sich.
- [x] ✅ Editor: Pflichtsprache markiert, Feld je Sprache, Kategorie, Farbe, Reihenfolge, Löschen je Zeile.
- [ ] ❌ Doppelter Name und mehr als 30 Labels werden abgelehnt, aber die Meldung steht am falschen Feld bzw. nennt `input.labels`. → F6.1, F6.3
- [x] ✅ Label umbenennen: Schlüssel bleibt (`research` = „Analyse“), Original unverändert.
- [x] ✅ Eigene Vorlage löschen: Bestätigung, weg, Liste ohne Neuladen aktuell.
- [x] ✅ Rechte: David sieht alle Vorlagen, Anlegen/Duplizieren/Löschen abgelehnt, keine Knöpfe; Ben darf Annas Vorlage nicht löschen.

## 2. Projekt aus Vorlage

- [x] ✅ Neues Projekt: Auswahl „Ohne Vorlage“ oder Vorlage, Vorschau der Labels und „2 Milestones, 2 ToDos, 4 Tasks“.
- [x] ✅ Projekt aus „Softwareentwicklung“: Labels beider Sprachen, Gerüst in Reihenfolge, alle Tasks „Geplant“ mit Labels (Vorlage hat keine Version).
- [x] ✅ Revision des neuen Projekts ist 1.
- [x] ✅ Projektbeschreibung aus der Vorlage übernommen. (Formatierte Beschreibungen im Gerüst: mitgelieferte Vorlagen haben keine; Prüfung mit eigener Datei in M4.)
- [x] ✅ Projekt auf Englisch aus Vorlage ohne EN-Namen: Hinweis erscheint, EN-Name = DE-Name. (Text „Bei 1 Labels“ → F6.2)
- [x] ✅ Mitglieder beim Anlegen zusammen mit Vorlage (David als Mitglied).

## 3. Labels im Projekt

- [x] ✅ Projekteinstellungen › Labels: gleicher Editor, Pflichtsprache = Projektsprache; Änderung nur im Projekt.
- [x] ✅ Label löschen: Bestätigung nennt 1 Task (ohne den Task im Papierkorb). (Text „von 1 Tasks“ → F6.2)
- [x] ✅ Task im Papierkorb hat das gelöschte Label nach Wiederherstellen nicht mehr.
- [x] ✅ Ergänzen: nur fehlender Schlüssel `bug` kam dazu.
- [x] ✅ Ersetzen: Vorlage-Label zuerst, Labels an Tasks bleiben am Ende, unbenutzte weg. (Vorschau in der Oberfläche nicht einzeln geprüft ⏸)
- [x] ✅ Revision +1 je Label-Änderung (3→4, Übernehmen 5→6→7), Vorlagen ändern keine Projekt-Revision.
- [x] ✅ Anzeige nach Sprache der Oberfläche (Kanban EN: Bug, Blocked, Research).
- [x] ✅ Archiviertes Projekt: beide abgelehnt („archiviert und nur lesbar“).
- [x] ✅ David: Projekt-Labels abgelehnt, Labels am eigenen Task setzen geht.

## 4. Rich-Text-Konverter (S3e, technisch)

- [x] Testlauf durch die Umsetzung (laut `umsetzung.md`), nicht von der Planung wiederholt.
- [x] ✅ Gemischt verschachtelte Liste gespeichert, unverändert, Export schreibt `1. Eins` / `   - a`.
- [x] ✅ Mitgelieferte Vorlagen laufen durch `planer:sync-templates` (Seeder ohne Fehler).

## 5. Aufräumen und Rechte

- [x] ✅ Rechte „Eigene Vorlagen verwalten“ / „Alle Vorlagen verwalten“, keine Label-Set-Rechte mehr.
- [x] ✅ `db-schema.ts`: `templates`, `template_id`, `names`; `label_sets` weg.
- [ ] ⏸ MCP `list_templates` (für Oliver; `create_project` gibt es seit M4 I3 nicht mehr).

## 6. Darstellung

- [x] ✅ DE/EN geprüft. ⏸ hell, Tastatur im Editor, Telefonbreite (Fenster ließ sich nicht verkleinern) für Oliver.

---

Ergebnis: _offen_
