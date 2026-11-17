# Abnahme Meilenstein M3 „Vorlagen und Labels“ (S3e, S7)

Stand: 2026-11-17, vorbereitet nach `docs/auftrag-m3.md` (Code bis `886b301`, M3 noch nicht umgesetzt). Rolle: Checkliste für Oliver. Die Planung gleicht die Liste mit dem fertigen Stand ab und prüft vorab so viel wie möglich selbst in Chrome.
Vorher durch die Umsetzung: alle Prüfungen grün (`php artisan test`, `composer test:mariadb`, `npm test` in `frontend/` und `tools/planer/`, Lint, Typecheck, Build), dann `php artisan migrate:fresh --seed --seeder=DemoSeeder`.

Personen aus den Demo-Daten: Anna (Admin), Ben (Owner Website-Relaunch), Chiara (Manager Website-Relaunch, Owner Sommerfest 2027), David (Mitglied). Je Punkt: ✅ passt, ❌ mit kurzer Notiz, ⏸ für Oliver. Test in Chrome.

---

## 1. Vorlagen-Seite (`/templates`)

- [ ] Menüpunkt „Vorlagen“ für alle Angemeldeten, auch David (Mitglied) und eine Person mit Systemrolle „Lesend“. `/label-sets` leitet auf `/templates` um.
- [ ] Liste zeigt die drei mitgelieferten Vorlagen (Softwareentwicklung, Event, Podcast) mit Kennzeichen „mitgeliefert“, Sprache und Anzahl Labels.
- [ ] Mitgelieferte Vorlage öffnen: Labels mit beiden Sprachen, Kategorie, Farbe; Gerüst als Baum (Milestones › ToDos › Tasks) nur zum Ansehen. Kein „Ändern“, kein „Löschen“, nur „Duplizieren“.
- [ ] Englische Label-Namen sind korrekt geschrieben (z. B. „Research“, „Topic idea“, „Speakers“), Oberfläche auf EN umstellen und prüfen.
- [ ] Als Anna eine mitgelieferte Vorlage duplizieren: Kopie mit Gerüst, als eigene Vorlage änderbar.
- [ ] Eigene Vorlage anlegen (Name, Beschreibung, Sprache, Labels): Pflichtsprache markiert, Hinweis bei fehlender Übersetzung, Reihenfolge änderbar.
- [ ] Doppelter Name je Sprache und mehr als 30 Labels werden abgelehnt (verständliche Meldung).
- [ ] Label umbenennen: Schlüssel bleibt gleich (Projekte aus dieser Vorlage nicht betroffen).
- [ ] Eigene Vorlage löschen: Bestätigung, danach weg (kein Papierkorb). Liste aktualisiert sich ohne Neuladen.
- [ ] Rechte: David (Mitglied) sieht Vorlagen, kann nichts anlegen/ändern; Systemrolle „Lesend“ ebenso. Fremde Vorlage ändern nur mit „Alle Vorlagen“ (Anna).

## 2. Projekt aus Vorlage

- [ ] Neues Projekt: Auswahl „ohne Vorlage“ oder eine Vorlage, Vorschau der Labels und Größe des Gerüsts.
- [ ] Projekt aus „Softwareentwicklung“ anlegen: Labels mit beiden Sprachen, Gerüst in der Reihenfolge der Vorlage (Milestones nach Position), Tasks im Status „Geplant“, Version aus der Vorlage.
- [ ] Revision des neuen Projekts ist 1 (Labels, Mitglieder, Gerüst zählen als eine Änderung).
- [ ] Projektbeschreibung und Beschreibungen im Gerüst mit Formatierung (aus Markdown umgewandelt).
- [ ] Projekt auf Englisch aus deutscher Vorlage: fehlender englischer Name → deutscher Name übernommen, Hinweis erscheint.
- [ ] Mitglieder gleich beim Anlegen (F2) funktionieren weiter zusammen mit der Vorlage.

## 3. Labels im Projekt

- [ ] Projekteinstellungen › Labels: gleicher Editor wie bei Vorlagen; Änderung wirkt nur in diesem Projekt (anderes Projekt und Vorlage unverändert).
- [ ] Label löschen, das an Tasks hängt: Bestätigung nennt die Anzahl betroffener Tasks (ohne Tasks im Papierkorb).
- [ ] Ein Task im Papierkorb mit diesem Label: nach Wiederherstellen hat er das gelöschte Label nicht mehr (so gewollt).
- [ ] „Labels aus Vorlage übernehmen“, Modus Ergänzen: nur fehlende Schlüssel kommen dazu.
- [ ] Modus Ersetzen: Labels der Vorlage gesetzt; gleiche Schlüssel aktualisiert (Tasks behalten sie); Labels an Tasks, die nicht in der Vorlage sind, bleiben am Ende; Vorschau zeigt das vorher.
- [ ] Revision +1 bei jeder Label-Änderung im Projekt; Änderungen an Vorlagen ändern keine Projekt-Revision.
- [ ] Anzeige: Label-Namen in der Sprache der Oberfläche (DE/EN umschalten), sonst Sprache des Projekts — in Kanban, Task-Detail, Label-Filter, Auswahl.
- [ ] Archiviertes Projekt: Labels nicht änderbar, „Labels aus Vorlage übernehmen“ abgelehnt.
- [ ] Mitglied (David) kann Projekt-Labels nicht ändern (`project.update` fehlt), Labels an eigenen Tasks setzen geht weiter.

## 4. Rich-Text-Konverter (S3e, technisch)

- [ ] Testlauf: alle Testfälle in `spec/fixtures/richtext/` in beide Richtungen grün (PHP und `tools/planer`), auch `mixed-list` als gültiges Paar.
- [ ] Gemischt verschachtelte Liste im Editor schreiben (Aufzählung in nummerierter Liste), speichern, neu laden: unverändert. (Export folgt in M4.)
- [ ] `tools/planer validate --template` auf alle drei mitgelieferten Vorlagen ohne Fehler.

## 5. Aufräumen und Rechte

- [ ] Rechte-Übersicht `/admin/roles`: „Vorlagen verwalten“ und „Alle Vorlagen verwalten“ statt Label-Sets; keine Label-Set-Rechte mehr.
- [ ] Datenbank-Doku (`/system/database` bzw. `db-schema.ts`): `templates`, `labels.project_id`, `labels.names`, `projects.template_id` beschrieben; `label_sets` weg.
- [ ] MCP `list_templates` und `create_project` mit `template_id` (⏸ für Oliver gegen die Demo-Instanz).

## 6. Darstellung

- [ ] `/templates`, Vorlagen-Editor, Labels in den Projekteinstellungen, Dialog „Neues Projekt“: DE/EN, hell/dunkel, Tastatur (Editor ohne Maus bedienbar), Telefonbreite ohne seitliches Scrollen.

---

Ergebnis: _offen_
