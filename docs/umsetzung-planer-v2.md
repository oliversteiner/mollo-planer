# Umsetzungsanleitung: Planer-Datei `planer/2`

Stand: 2026-11-12. Für die Umsetzungssitzung(en), die nach dieser Planung arbeiten.
Grundlage: `docs/planer-datei-ssot.md` (Entscheidungen §2, Format §4, Grammatik §4.5, Phasen §7), `docs/planer-vs-mollo-version.md` (§7), `docs/schema-review.md` (A1–A6, Reihenfolge), `docs/refactoring-vor-umsetzung.md` (Offene Schritte), `docs/beispiel-planer.md` (Testfall).

---

## 0. Grundregeln

- **Entscheidungen nicht neu verhandeln.** Was in §2 der SSoT und in §2 des Schema-Reviews steht, ist beschlossen. Abweichungen nur nach Rückfrage bei Oliver.
- **Zwei Repositories, zwei Spuren.** Spur A (`~/www/mollo-version`) ist das Werkzeug. Spur B (`~/www/mollo-planer`) ist die App. Spur A kann vorab und ohne Spur B laufen. Spur B braucht Spur A ab P2 (R2 und P6).
- **Ein Schritt, ein Commit, grüne Tests.** Commit-Nachrichten im bisherigen Stil (`Refactor:`, `Feat:`, `Fix:`, `Docs:`). Jeder Schritt endet mit einer Zeile Stand im betroffenen Plan-Dokument.
- **Refactorings ändern kein Verhalten.** R-Schritte müssen mit den bestehenden Tests laufen, bevor Schema-Schritte beginnen.
- **Keine Kurzschlüsse über die Schemas.** Die Form eines Plans steht in genau einer Quelle (`planer/2`-JSON-Schema, danach `PlanSchema` in PHP). Keine zweite Beschreibung anlegen.

---

## 1. Vorbedingungen (vor dem ersten Code)

Offene Punkte aus SSoT §9, die in P1 festgeschrieben werden:

1. **Rollen in `members`:** Werte aus dem GraphQL-Enum `ProjectRole` der App übernehmen. Die Fundstelle in `mollo-planer` ist vorher zu prüfen (nicht in den bisherigen Dokumenten belegt).
2. **Ort des JSON-Schemas:** `spec/planer-2.schema.json` in `mollo-version` (Vorschlag, bestätigt).
3. **Grenzen `userStory`:** `role` höchstens 80, `goal` und `benefit` höchstens 160 Zeichen, Klartext.
4. **Grammatikregeln §4.5** als Tests gegen `spec/format-v2.md` festschreiben.
5. **Leere Container:** Die App lässt leere ToDos und Tasks zu, `planer/2` folgt der App. Die Regel aus v1 gilt nur für v1.

Stopp-Punkt: Ist einer dieser Punkte nicht eindeutig, nachfragen. Nicht raten.

---

## 2. Spur A: `mollo-version` (Format und Werkzeug)

Bestand: Node-CLI ohne Abhängigkeiten, `bin/mollo-version.mjs`, Befehle `validate` und `json`, Spezifikation v1 in `spec/format.md`, Fixtures `fixtures/valid.md` (keine Fehler) und `fixtures/invalid.md` (13 Fehler), Tests über `npm test`, Laravel-Paket `laravel/`.

### P1: Spezifikation v2
- **Dateien:** `spec/format-v2.md`, `spec/planer-2.schema.json`.
- **Inhalt:** Felder aus SSoT §4.2, Regeln §4.3, User-Story §4.4a, Formatierung §4.4b (Whitelist), Grammatik §4.5, Release-Regeln, Commit-Regel.
- **Fertig, wenn:** Das Schema ein gültiges JSON-Schema ist, `$schema` in der Beispieldatei zeigt, und das Beispiel aus SSoT §4.1 gegen das Schema läuft.
- **Commit:** `Docs: planer/2 specification and JSON schema`.

### P2: Validator v2
- **Dateien:** Erweiterung von `bin/` und `src/` (vorhandene Struktur übernehmen), neue Fixtures `fixtures/planer-valid.planer.json` und `fixtures/planer-invalid.planer.json`, Tests.
- **Prüfungen:** Schema, Semantik (eindeutige `id`, Verweise auf Mitglieder und Labels, Release-Regeln, Grenzen, Commit-Regel), Kanonik als Hinweis.
- **Dateierkennung:** `validate` findet `*.planer.json` zusätzlich zu `version-*.md` (SSoT §8).
- **Fertig, wenn:** Gültige Fixture ohne Fehler, jede ungültige Fixture mit der erwarteten Fehlerzahl und Pfadangabe. `npm test` grün.
- **Commit:** `Feat: validator for planer/2`.

### P3: Ausgabe
- **Befehle:** `json` für v2 (kanonisch, Schlüsselreihenfolge fest), `render` nach Markdown (Grammatik §4.5, IDs als Kommentar).
- **Fertig, wenn:** Golden-File-Tests für Beispiel und Fixtures. Das Beispiel aus `docs/beispiel-planer.md` ist als Testfall erfasst, soweit er die Grammatik abdeckt.
- **Commit:** `Feat: render and canonical json for planer/2`.

### P4: Helfer für Agenten
- **Befehle:** `add milestone|todo|task`, `move <id> --to <ziel>`, `status <task-id> open|in_progress|review|done`, `list --compact`, `show <id>`, `revision`.
- **Regeln:** Jede Änderung erhöht `revision`. IDs werden vergeben und nie wiederverwendet. Ausgabe kurz, mit neuer ID.
- **Zusätzlich:** `SKILL.md` für Agenten.
- **Messung:** Tokenbedarf vorher und nachher an einer Beispieldatei protokollieren (SSoT §3 und §8). Ergebnis in die Planung zurückschreiben, nicht vorher behaupten.
- **Commit:** `Feat: helper commands for planer/2`.

### P5: Konvertierung
- **Befehle:** `convert v1-zu-v2 <version-*.md>` und `convert md2json <datei.md>`.
- **Fertig, wenn:** Roundtrip-Tests für beide. `render` → `convert md2json` → `json` ergibt dieselbe kanonische Datei. Die bestehende Versionsdatei der FCAarau DB (Projekt-ID 2, siehe `docs/connector-erweiterung-plan.md`) wird nur gelesen, nicht verändert.
- **Commit:** `Feat: convert v1 to v2 and md2json`.

### Schluss Spur A
- `mollo-version` bekommt einen Versionssprung (0.2.0 → 0.3.0 oder 1.0.0, Entscheidung bei P1 festhalten). `CHANGELOG.md` und `README.md` nachführen.

---

## 3. Spur B: `mollo-planer` (App)

Reihenfolge laut `docs/refactoring-vor-umsetzung.md` (Offene Schritte) und `docs/schema-review.md` („Reihenfolge der Umsetzung“). Vor jedem Schritt die Tests laufen lassen, damit man weiß, was vorher grün war.

### B1: R7 (Rest) und R4 (Frontend)
- `useDelete` als Composable. Zunächst mit Bestätigung, also ohne Verhaltensänderung.
- Liste „Mutationen in archivierten Projekten verboten“ anlegen, noch ohne Archiv-Logik, mit Test gegen das Schema.
- **Commits:** getrennt je Schritt.

### B2: R2 (Rest), erst nach P2 in Spur A
- Wertobjekte `App\Plan\PlanMilestone`, `PlanTodo`, `PlanTask` mit `fromArray()` und `toArray()`.
- `PlanSchema` prüft gegen das JSON-Schema `planer/2` (aus `mollo-version`). Die PHP-Seite übernimmt die Grenzwerte aus dem Schema, nicht umgekehrt.
- `ProjectPlanImporter` nimmt nur Wertobjekte an. `ReadsProjectPlanInput` und `ProjectPlanAssistant::clean()` bauen Wertobjekte.
- Test: Felder von `plan.graphql` gegen das Schema.
- **Verhaltensänderung:** `status` (Task) und `position` (Milestone) fehlen heute im Assistenten und in GraphQL. Das ist eine Verhaltensänderung und braucht Freigabe. Vorher Oliver fragen.

### B3: R5 und Datenstruktur (Schema-Review Schritt 1)
- Migration, frisch aufgesetzt (`migrate:fresh`), Seeder anpassen.
- Umfang: A1 `project_id` an `todos` und `tasks`, A2 `created_by`, A3 `todos.position` und `boardPosition`, A5 `archived_at`, `deleted_at`, `deleted_by`, `deletion_id`, A6 `tasks.user_story` (JSON).
- Trait `DerivesProject` und `RecordsCreator` im Model-Hook.
- **Stopp-Punkt:** `migrate:fresh` löscht Daten. Vor jeder Ausführung außerhalb der Testdatenbank bestätigen lassen. Ob es Produktionsdaten gibt und wie sie übernommen werden, ist in den Dokumenten nicht geklärt.

### B4: Rechte und Archiv/Papierkorb (Schema-Review Schritte 2 und 3)
- Rechte in beide Rechte-Tabellen: `task.create`, `task.updateOwn`, `task.assignSelf`, `project.archive`, `trash.purge`, `assistant.use`, `ideas.create`, `ideas.viewAny`.
- Archiv als eine Zeile in `ProjectPermissions::allows` („archiviert → nichts erlaubt“), mit den Ausnahmen `project.archive` und Wiederherstellen.
- Papierkorb über `App\Services\Deletion` (weich löschen, Kinder markieren, `restore()`, `purge()`). Endgültig löschen nur Owner und Admins.
- Frontend: Toast mit „Rückgängig“ über `useDelete`.

### B5: Plan-Import (Schema-Review Schritt 4, A4)
- `importPlanIntoProject` für Plan-Entwürfe mit `appId`-Erkennung: vorhandenes Element wird aktualisiert, nicht dupliziert.
- Laravel-Importer aus `mollo-version/laravel` und dieser Importer schreiben nur ihre Felder.

### B6: Ideen, KI-Kontext, MCP (Schema-Review Schritt 5, B1–B2)
- MCP-Werkzeuge `create_idea`, `list_ideas`, `get_project_ai_context`.

### B7: Payloads und Rest (Schema-Review Schritt 6, C1–C3)
- Nach Bedarf, Reihenfolge laut Schema-Review.

### P6: Anbindung Planer (SSoT §7)
- Laravel-Importer für `planer/2` über `importPlanIntoProject`, Export, Roundtrip-Tests:
  - `export(import(datei))` ergibt dieselbe kanonische Datei.
  - `import(export(db))` ändert die Datenbank nicht.
- Abhängig von A4 und R2. Erst danach.
- Der Beispielplan `docs/beispiel-planer.md` wird als `planer/2`-JSON im Repo abgelegt und zum Roundtrip-Testfall.

---

## 4. Checkliste pro Schritt

- [ ] Vorher-Tests grün (SQLite und `composer test:mariadb` in `mollo-planer`, `npm test` in `mollo-version`)
- [ ] Änderung nur im Umfang des Schritts
- [ ] Neue Tests für das neue Verhalten
- [ ] Nachher-Tests grün
- [ ] Stand im betroffenen Plan-Dokument aktualisiert
- [ ] Eigener Commit

---

## 5. Stopp-Punkte (vor dem Weitermachen fragen)

- `migrate:fresh` oder anderes Löschen von Daten außerhalb der Testdatenbank
- Breaking Change an einem GraphQL-Feld oder MCP-Werkzeug (Connector und Frontend betroffen)
- Verhaltensänderung bei R2 (`status`, `position` im Assistenten)
- Unklare Fundstelle oder widersprüchliche Angaben in den Dokumenten
- Jede Änderung an Entscheidungen aus SSoT §2 oder Schema-Review „Entscheidungen“

---

## 6. Was nicht zu tun ist

- Keine Code-Änderungen in der Planungssitzung dieses Projekts. Diese Anleitung gilt für die Umsetzung in eigenen Sitzungen.
- Keine zweite Vorlage für das Planer-Schema neben dem JSON-Schema.
- Keine Entfernung der Labels, Rollen oder Grenzwerte ohne Entscheidung in den Planungsdokumenten.
- Keine Wiederverwendung von `id`-Werten, auch nach dem Löschen.
