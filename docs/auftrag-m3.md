# Auftrag Meilenstein M3 „Vorlagen und Labels“ (S3e, S7)

Stand: 2026-11-17, Planung. Geprüft: `mollo-planer` bis `0bdea9b` (nach Abnahme M2 mit Fix F5).
Rolle: Auftrag für die Umsetzung (Bob). Präzisiert `umsetzung.md` S3e und S7 auf den heutigen Code. Wo sich dieses Dokument und `planer-abgleich.md` §3 bzw. §9 widersprechen, gilt dieses Dokument.

**M2 ist abgenommen (2026-11-17).** M3 kann starten. Zuerst §0a.

---

## 0. Was schon da ist

- `spec/format-v2.md` §6.1 (kanonisches Markdown) und `tools/planer/core/richtext.mjs` (T6) mit Testfällen in `spec/fixtures/richtext/` (`*.in.md`/`*.out.md`, `*.error.md`, `*.hint.in.md`/`*.hint.out.md`).
- `App\Support\RichTextDocument` (Whitelist der App, Marks `bold`, `italic`, `code`, `link`), `RichTextRule`, Scalar `RichText`, `App\Mcp\Support\PlainTextDocument` (bleibt bis B5a).
- `league/commonmark` ist installiert (über Laravel), keine neue Abhängigkeit nötig.
- Mitgelieferte Vorlagen als Dateien in `spec/templates/` (T4): `software-development`, `event`, `podcast`, jeweils mit Labels und kleinem Gerüst.
- Rechte `templates.manage`, `templates.manageAny` gibt es im Enum `Right`; `labelSets.manage`/`labelSets.manageAny` hängen bis S7 an den Funktionen „Vorlagen“ bzw. „Alle Vorlagen“.
- `projects.locale` gibt es schon.
- Label-Sets heute: `label_sets`, `labels.label_set_id`, `labels.name`, `labels.key` (nur mitgelieferte), `projects.label_set_id`, `BuiltinLabelSets.php`, Seite `/label-sets`, Komponente `components/label-set/FormModal.vue`, `setProjectLabelSet`, Migrationen `2026_11_09_234005`, `2026_11_11_090000`. Das GraphQL-Enum `Right` hat noch `LABEL_SETS_MANAGE`/`LABEL_SETS_MANAGE_ANY`.
- Aus M1/M2: `App\Planer\PlanerRevision` (zählt einmal je Projekt und Transaktion), `TaskLabels::set` als einziger Weg für Labels am Task (RF3), `useProjectMutation`/`refreshProject` (RF4), `Rights` mit Obergrenze für archivierte Projekte (`Right::holdsWhenArchived`), Papierkorb über `App\Services\Deletion` mit `SoftDeletes` an Project, Milestone, ToDo, Task, Kommentar, Milestones in der Oberfläche nach `position` (F5.4, `moveMilestone`), `createProject` mit `members` (F2) und `description` als Rich Text (S6 C2), übersetzte Standard-Ablehnung (F5.5).
- `RightUsageTest` (`tests/Unit`): `templates.manage` und `templates.manageAny` stehen als „S7“ in `PENDING`.

## 0a. Zuerst: Vorarbeit und Folgen aus M2

1. **RF8** (`docs/refactoring-m1-m3.md`): `App\Plan\PlanSchema` nach `App\Planer` verschieben, eigener Commit vor S3e. Danach gibt es `App\Plan` nicht mehr.
2. **Papierkorb und Labels:** Labels kommen nicht in den Papierkorb (endgültig löschen wie heute). Wird ein Projekt-Label gelöscht, verschwindet es auch an Tasks im Papierkorb (`label_task` per Kaskade); ein später wiederhergestellter Task hat es dann nicht mehr. Das ist so gewollt; die Bestätigung beim Löschen eines Labels zählt Tasks im Papierkorb **nicht** mit. Ein Projekt im Papierkorb behält seine Labels. Test dafür.
3. **Archiv:** `updateProjectLabels` und `applyTemplateLabels` gehören in die Liste von `ArchivedProjectMutationsTest` (im archivierten Projekt abgelehnt). Vorlagen sind app-weit und vom Archiv nicht betroffen; `createProject` mit `templateId` aus einer Vorlage geht immer.
4. **Revision:** Anlegen aus Vorlage samt Labels, Mitgliedern und Gerüst zählt als eine Änderung (Revision 1, wie F2). `updateProjectLabels` und `applyTemplateLabels` je +1. Labels an Tasks im Papierkorb ändern die Revision beim Löschen eines Labels nicht zusätzlich.
5. **Reihenfolge des Gerüsts:** `TemplateScaffold` legt Milestones, ToDos und Tasks in der Reihenfolge der Vorlage an (`position`, F5.4) und setzt Tasks in der Kanban-Spalte „Geplant“ der Reihe nach (`board_position`).
6. **Beschreibungen:** Die Projektbeschreibung ist Rich Text (S6 C2). `TemplateScaffold` wandelt `project.description` und alle anderen Beschreibungen der Vorlage mit `MarkdownToTiptap` (Modus `strict`) um.
7. **Oberfläche:** Projekt-Labels in den Einstellungen laufen über `useProjectMutation` (der eigene Vorgang löst keinen Hinweis „inzwischen geändert“ aus). Nach Anlegen, Ändern, Duplizieren, Löschen einer Vorlage den Apollo-Cache der Vorlagenliste leeren (`cache.evict`, wie bei Rollen und Projekten). Löschen einer Vorlage mit Bestätigung (`useConfirmedDelete`), nicht über den Papierkorb.

## 1. Entscheidung vorab: Spezifikation und Editor müssen zusammenpassen

Beim Vergleich ist aufgefallen: `spec/format-v2.md` §6.1 (gebaut in T6) weicht an drei Stellen von `planer-abgleich.md` §9.4 ab.

| Punkt | §9.4/§9.6 (Planung) | §6.1 (gebaut) | Editor der App |
|---|---|---|---|
| Gemischt verschachtelte Listen | erlaubt | **Fehler** | erlaubt (TipTap) |
| Listen tiefer als 3 Ebenen | auf 3 gekürzt | erlaubt | erlaubt |
| Inline-Code in Fett/Kursiv | nur Code | erlaubt | erlaubt |

Grundsatz für die Entscheidung: **Was der Editor speichern kann, muss als Datei gültig sein.** Sonst bricht der Export (I1) an Beschreibungen, die jemand ganz normal im Editor geschrieben hat.

**Vorschlag (gilt, wenn Oliver nicht widerspricht):**
- Gemischt verschachtelte Listen werden in der Datei **erlaubt**. `tools/planer` (Validator, `richtext.mjs`, §6.1) wird angepasst, `mixed-list.error.md` wird zum Paar `mixed-list.in.md`/`.out.md`.
- Tiefe und Code in Fett/Kursiv bleiben wie in §6.1 (erlaubt). §9.4/§9.6 gelten an diesen Stellen nicht mehr.
- Die Startnummer einer nummerierten Liste geht verloren (Liste ab 1), wie in §6.1. Die App nimmt `orderedList.attrs.start` weiter an (`RichTextDocument` erlaubt es heute, der Editor schreibt es); `TiptapToMarkdown` lässt es weg, `MarkdownToTiptap` setzt es nie. (Korrektur 2026-11-17: Die frühere Aussage „der Validator der App lässt `start` nicht zu“ stimmt nicht mit dem Code überein und gilt nicht.)

## 2. S3e Rich-Text-Konverter (zuerst, unabhängig)

1. Namensraum **`App\Planer\RichText`** (neu; `PlanSchema` liegt nach RF8 schon in `App\Planer`).
2. `MarkdownToTiptap::convert(string $markdown, Mode $mode): Result` mit `Result { array $doc; list<Warning> $warnings }`.
   - Modus `strict` (Datei, Import): ein Konstrukt aus der Fehlerliste von §6.1 ist ein Fehler mit Pfad, nichts wird geschrieben.
   - Modus `lenient` (Assistent, Ideen, MCP-Ausschnitte, Einfügen): Klartext nach der Tabelle §9.6, je Fall eine Warnung (Art, Zeile, Auszug).
   - Ergebnis besteht immer `RichTextRule`.
   - Parser: `league/commonmark` mit `CommonMarkCoreExtension`, nur AST, nie HTML ausgeben. Autolinks, Tabellen und Durchgestrichen nur so weit aktivieren, wie es zum Erkennen für §9.6 nötig ist.
3. `TiptapToMarkdown::convert(array $doc): string` schreibt genau die kanonische Form von §6.1 (gleiche Ausgabe wie `richtext.mjs`).
4. **Gemeinsame Testfälle**, damit PHP und CLI nicht auseinanderlaufen:
   - Zu jedem `*.in.md`/`*.out.md` ein `*.tiptap.json` (das erwartete Dokument). PHP prüft: `in.md` → JSON = `tiptap.json`; `tiptap.json` → Markdown = `out.md` (byte-gleich); `out.md` → JSON → Markdown = `out.md`.
   - Zu jedem `*.error.md` ein `*.lenient.json` mit `{ "doc": …, "warnings": [ { "kind": … } ] }`. PHP prüft `strict` = Fehler, `lenient` = dieses Ergebnis. `tools/planer` liest die neuen Dateien nicht, sein Test bleibt, wie er ist (außer `mixed-list`, §1).
   - Ein Test geht alle Dateien in `spec/fixtures/richtext/` durch: jede `.md` hat ihr Gegenstück, sonst rot.
5. Ein Eigenschaftstest: zufällige gültige TipTap-Dokumente (aus der Whitelist) → Markdown → TipTap ergibt dasselbe Dokument (normalisiert: leere Absätze, `start`).
6. `RichTextDocument`-Whitelist gegen den Konverter prüfen (offen aus T6): jede erlaubte Knoten-/Mark-Art kommt in mindestens einem Testfall vor.
7. Noch kein Einbau in Import, Export oder Assistent (das ist I1, I2, B5a). Die Vorlagen in §3 nutzen den Konverter schon.

## 3. S7 Vorlagen und Projekt-Labels

### 3.1 Datenmodell (Breaking Change, `migrate:fresh` lokal)

- `labels`: `project_id` (cascade) statt `label_set_id`; `key` **Pflicht** (1–40, `a-z0-9_-`), eindeutig je Projekt, entsteht beim Anlegen aus dem Namen in der Sprache des Projekts und **ändert sich nie**; `names` (JSON Sprache → Name, 1–40) statt `name`; `category`, `color`, `position` wie bisher. Höchstens 30 je Projekt. Name je Sprache eindeutig je Projekt (in PHP geprüft, nicht als Index).
- `projects.label_set_id` entfällt; `projects.template_id` (nullable, `nullOnDelete`) nur zur Anzeige der Herkunft.
- Tabelle `templates`: `key` (nur mitgelieferte, eindeutig), `name`, `description`, `locale`, `content` (JSON, `planer-v2`-Vorlage nach §3.8), `created_by` (nullOnDelete), Zeitstempel. Name eindeutig.
- `label_sets` und `BuiltinLabelSets.php` entfallen, ebenso die Label-Namen in `lang/`. Die Übersetzungen der **Kategorien** mitgelieferter Vorlagen bleiben in `lang/` (Schlüssel `type`, `phase`, `trade`, …), eigene Kategorien erscheinen, wie sie geschrieben sind.
- Rechte: `labelSets.manage`/`labelSets.manageAny` aus dem Enum `Right`, GraphQL und Frontend entfernen; die Funktionen „Vorlagen“ und „Alle Vorlagen“ bekommen `templates.manage` bzw. `templates.manageAny`. `RightUsageTest` und `SchemaAgreementTest` nachführen.
- `frontend/app/utils/db-schema.ts` nachführen.

### 3.2 Mitgelieferte Vorlagen

- `php artisan planer:sync-templates` liest `spec/templates/*.planer.json`, prüft sie (§3.4 unten) und schreibt sie nach `templates` (Schlüssel = Dateiname ohne `.planer.json`). Läuft im `DatabaseSeeder` und im Deployment (`deploy-template.md` ergänzen). Mitgelieferte Vorlagen sind unveränderlich (nur duplizieren).
- **Englische Namen korrigieren:** In allen drei Dateien sind die englischen Label-Namen klein geschrieben und teils roh (`research`, `topic idea`, `speakers`). Richtig: „Research“, „Design“, „Feature“, „Bug“, „Planning“, „Blocked“; „Topic idea“, „Guest booking“, „Audio editing“, „Social media“; „Location“, „Speakers“, „Marketing“, „Confirmed“. Danach `tools/planer` `validate --template` auf alle drei.

### 3.3 Backend und GraphQL

```graphql
type Template {
  id: ID!
  key: String              # nur mitgelieferte
  name: String!
  description: String
  locale: String!
  labels: [TemplateLabel!]!
  outline: TemplateOutline! # Gerüst nur zum Ansehen: Milestones > ToDos > Tasks (Titel, Version, Labels)
  isBuiltin: Boolean!
  createdBy: User
  viewerCanUpdate: Boolean!
  viewerCanDelete: Boolean!
}
type TemplateLabel { key: String!, names: JSON!, name: String!, category: String, color: LabelColor! }

templates: [Template!]!
template(id: ID!): Template

createTemplate(input: TemplateInput!): Template!          # Name, Beschreibung, Sprache, Labels; leeres Gerüst
updateTemplate(id: ID!, input: TemplateInput!): Template! # nur Name, Beschreibung, Labels
duplicateTemplate(id: ID!): Template!                     # mit Gerüst
deleteTemplate(id: ID!): Boolean!                         # endgültig, nicht im Papierkorb

createProject(input: { …, templateId: ID })               # Labels und Gerüst übernehmen
updateProjectLabels(projectId: ID!, labels: [ProjectLabelInput!]!): Project!
applyTemplateLabels(projectId: ID!, templateId: ID!, mode: REPLACE | APPEND): Project!
```

- `Label.name` ist der Name in der Sprache der Oberfläche, sonst der Sprache des Projekts; `Label.names` liefert alle.
- `ProjectLabelInput`: `key` (fehlt bei neuen Labels), `names`, `category`, `color`. Fehlende Labels werden gelöscht; an Tasks hängende Labels werden dabei entfernt, mit Bestätigung im Frontend, die die Anzahl der betroffenen Tasks nennt.
- `applyTemplateLabels`: `APPEND` übernimmt Labels, deren `key` im Projekt fehlt. `REPLACE` setzt die Labels der Vorlage; Labels mit gleichem `key` werden aktualisiert (Tasks behalten sie); Labels, die an Tasks hängen und nicht in der Vorlage sind, bleiben am Ende stehen; die übrigen werden gelöscht.
- Projekt aus Vorlage: Labels mit allen Sprachen kopieren. Fehlt einem Label der Name in der Sprache des Projekts, wird der Name in der Sprache der Vorlage übernommen (Hinweis im Frontend). Das Gerüst legt ein kleiner Dienst `App\Planer\TemplateScaffold` an (Beschreibungen über `MarkdownToTiptap` im Modus `strict`); er wird in I2 durch den Import ersetzt.
- Gültigkeit von `content`: Vorlagen, die die App selbst baut (anlegen, ändern, duplizieren), halten die Regeln von §3.8 durch ihre Bauweise. Ein PHP-Test prüft das mit `tools/planer` (`validate --template`), der Test wird übersprungen, wenn Node fehlt.
- Rechte: lesen alle Angemeldeten, auch Systemrolle Lesend und Gast (entschieden §3.6 Nr. 4; §3.4 ist an dieser Stelle überholt). Anlegen und Duplizieren `templates.manage`; ändern und löschen eigene mit `templates.manage`, fremde mit `templates.manageAny`; mitgelieferte nie. Projekt-Labels: `project.update`.
- `planer_revision` +1 bei jeder Änderung der Projekt-Labels (sie stehen in der Datei). Änderungen an Vorlagen zählen nicht.
- `SchemaAgreementTest` ergänzen: `LabelColor` und Sprachcodes gegen `spec/planer-v2.schema.json` (offen aus S3 Rest), Label-`key`-Muster, Höchstzahl 30.
- MCP: `list_templates`, `create_project` mit optionalem `template_id`. Archivierte Projekte bleiben gesperrt (M2).

### 3.4 Frontend

- Seite `/templates` ersetzt `/label-sets` (alte Route leitet um): Liste mit „mitgeliefert“/Ersteller, Editor (Name, Beschreibung, Sprache, Labels mit einem Feld je Sprache, Pflichtsprache markiert, Hinweis bei fehlender Übersetzung, Farbe, Kategorie, Reihenfolge), Gerüst als Baum nur zum Ansehen, Duplizieren, Löschen mit Bestätigung.
- Neues Projekt: Auswahl der Vorlage (oder „ohne Vorlage“), Vorschau der Labels und Größe des Gerüsts.
- Projekteinstellungen: Labels des Projekts bearbeiten (gleicher Editor), „Labels aus Vorlage übernehmen“ mit Auswahl Ersetzen/Ergänzen und Vorschau, was mit den Labels an Tasks passiert.
- Überall, wo Labels erscheinen (`TaskLabels`, `LabelPicker`, Filter, Kanban), `Label.name` nutzen.
- Menüpunkt „Vorlagen“ für alle Angemeldeten.
- **Nicht in M3:** „Als Vorlage speichern“ aus einem Projekt, Vorlage aus Datei hochladen, als Datei herunterladen (kommen mit I1, I2, I5). `umsetzung.md` S7 nennt „Als Vorlage speichern“ unter „Oberfläche“; das ist durch „Noch nicht in S7“ überholt.

## 4. Tests (mindestens)

- S3e: alle Testfälle in beide Richtungen, `strict` und `lenient`, Eigenschaftstest, Vollständigkeit der Testfall-Paare.
- Projekt aus Vorlage: Labels mit allen Sprachen, Gerüst mit Reihenfolge, Status `planned`, Version; `template_id` gesetzt.
- Projekt-Labels ändern verändert kein anderes Projekt und keine Vorlage.
- `applyTemplateLabels` `REPLACE` und `APPEND`, Labels an Tasks bleiben erhalten.
- Schlüssel ändern sich beim Umbenennen nicht; doppelte Namen je Sprache werden abgelehnt; mehr als 30 abgelehnt.
- Mitgelieferte Vorlagen unveränderlich; `planer:sync-templates` zweimal hintereinander ändert nichts.
- Rechte je Mutation mit Standardrolle und eigener Rolle; Lesend und Gast sehen Vorlagen, ändern nichts.
- `planer_revision` +1 bei Label-Änderung im Projekt, 0 bei Vorlagen.
- `OutsiderAccessTest`, `MutationCoverageTest`, `ArchivedProjectMutationsTest` ergänzen.
- Aus §0a: Label löschen entfernt es auch an Tasks im Papierkorb, Bestätigung zählt sie nicht; Projekt aus Vorlage hat Revision 1; Gerüst in der Reihenfolge der Vorlage; `RightUsageTest`: `templates.*` aus `PENDING`.

## 5. Ablauf und Abschluss

1. RF8 → §1 (Spezifikation und `tools/planer`) → S3e → S7 Datenmodell und Sync → S7 Backend/GraphQL → S7 Frontend. Ein Commit je Teil, `umsetzung.md` „Stand“ nachführen.
2. Stopp und Rückfrage, falls: `league/commonmark` ein Konstrukt aus §9.6 nicht als AST-Knoten liefert und nur über HTML erkennbar wäre; der Editor Inhalte speichert, die der Konverter nicht verlustfrei schreiben kann (außer `start`); die Kanonik in PHP und `richtext.mjs` in einem Fall nicht übereinstimmt.
3. Am Ende: alle Prüfungen grün (auch `tools/planer`), `migrate:fresh --seed --seeder=DemoSeeder`, im Browser ansehen (hell/dunkel, DE/EN, Tastatur, Telefonbreite), dann anhalten für die Abnahme M3.
