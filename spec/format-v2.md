# Format planer-v2: `<projekt>.planer.json`

Version 2 des Formats. Gilt für die Planung eines Projekts: Milestones, ToDos und Tasks. Eine Datei pro Projekt, im Ordner `docs/`, Name `<projekt>.planer.json`.

Die Datei ist ein JSON-Dokument. Die Form beschreibt das JSON-Schema `spec/planer-v2.schema.json`. Die Prüfung der Datei ist der Validator (`mollo-planer validate`, ab P2). Das Format folgt dem Schema der App (`ProjectPlanInput` in `mollo-planer/backend/graphql/plan.graphql`), damit Markdown, App-Oberfläche und MCP-Connector dieselbe Struktur bearbeiten.

Für Versionen nach Spezifikation v1 (`version-<name>.md`) gilt `spec/format.md` unverändert.

---

## 1. Beispiel

```json
{
  "$schema": "planer-v2.schema.json",
  "format": "planer-v2",
  "revision": 3,
  "project": {
    "name": "Mollo Planer",
    "description": "Projektverwaltung mit Milestones, ToDos und Kanban.",
    "locale": "de",
    "members": [{ "email": "oliver@mollo.ch", "role": "owner" }],
    "labels": [{ "key": "backend", "names": { "de": "Backend", "en": "Backend" }, "category": "area", "color": "cyan" }]
  },
  "milestones": [
    {
      "id": "m-1",
      "title": "Schema-Refactoring",
      "dueDate": "2026-12-15",
      "release": { "version": "1.4", "status": "planned" },
      "todos": [
        {
          "id": "t-1",
          "title": "Ordering-Service ausbauen",
          "tasks": [
            { "id": "k-1", "title": "Sperre für Milestones ergänzen", "status": "done", "labels": ["backend"], "commit": null },
            { "id": "k-002", "title": "Lückenschluss nach Löschen", "status": "open", "assigneeEmail": "oliver@mollo.ch", "labels": ["backend"] }
          ]
        }
      ]
    }
  ]
}
```

Das Beispiel ist gegen `spec/planer-v2.schema.json` geprüft. Die Rolle `owner` ist ein Beispielwert.

---

## 2. Felder

Pflicht ist mit „ja“ markiert. Alle anderen Felder dürfen fehlen. Fehlt ein Feld, das auch `null` sein darf, gilt es als nicht gesetzt. Fehlt `labels`, gilt es als leere Liste.

### 2.1 Datei

| Feld | Pflicht | Typ und Grenze |
|---|---|---|
| `$schema` | nein | String. Hinweis für Editoren. Der Validator ignoriert das Feld und prüft immer gegen `spec/planer-v2.schema.json`. |
| `format` | ja | Genau `"planer-v2"`. |
| `revision` | ja | Ganze Zahl, mindestens 0. Stand der App beim letzten Export oder Import, siehe Abschnitt 5.2. |
| `changed` | nein | Nur `true`, sonst fehlt das Feld. Die Datei wurde lokal geändert und ist noch nicht eingetragen. |
| `project` | ja | Objekt, Abschnitt 2.2. |
| `milestones` | ja | Liste mit mindestens 1 und höchstens 30 Milestones. |

### 2.2 Projekt

| Feld | Pflicht | Typ und Grenze |
|---|---|---|
| `name` | ja | String, 2 bis 120 Zeichen. |
| `description` | nein | String, höchstens 2000 Zeichen, oder `null`. |
| `locale` | ja | `"de"` oder `"en"`. Die App speichert dieselben Werte (Abschnitt 5.1). |
| `members` | ja | Liste mit höchstens 50 Einträgen. Leer ist erlaubt. |
| `labels` | nein | Liste mit höchstens 30 Labels, siehe Abschnitt 5.4. Fehlt sie, hat das Projekt keine Labels. |

Ein Mitglied (`members[]`):

| Feld | Pflicht | Typ und Grenze |
|---|---|---|
| `email` | ja | E-Mail-Adresse. |
| `role` | ja | `"owner"`, `"manager"` oder `"member"`. Das sind die Werte des GraphQL-Enums `ProjectRole` der App (`backend/graphql/enums.graphql`). |

### 2.3 Milestone

| Feld | Pflicht | Typ und Grenze |
|---|---|---|
| `id` | ja | `m-<Zahl>` (ID in der App) oder `m-new-<Zahl>` (lokal neu, noch nicht eingetragen), z. B. `m-12` oder `m-new-1`. |
| `title` | ja | String, 2 bis 160 Zeichen, Klartext. |
| `description` | nein | Markdown, höchstens 5000 Zeichen, oder `null`. Siehe Abschnitt 6. |
| `dueDate` | nein | Datum `JJJJ-MM-TT`, oder `null`. |
| `release` | nein | Objekt, siehe unten. Nur auf Milestones erlaubt. |
| `todos` | ja | Liste mit höchstens 50 ToDos. Leer ist erlaubt. |

`release`:

| Feld | Pflicht | Typ und Grenze |
|---|---|---|
| `version` | ja | `M.m`, ganze Zahlen, z. B. `1.4`. Keine Patch-Stufe. |
| `status` | ja | `"planned"` oder `"released"`. |

### 2.4 ToDo

| Feld | Pflicht | Typ und Grenze |
|---|---|---|
| `id` | ja | `t-<Zahl>` oder `t-new-<Zahl>`, z. B. `t-41` oder `t-new-2`. |
| `title` | ja | String, 2 bis 160 Zeichen, Klartext. |
| `description` | nein | Markdown, höchstens 5000 Zeichen, oder `null`. |
| `dueDate` | nein | Wie beim Milestone. |
| `tasks` | ja | Liste mit höchstens 50 Tasks. Leer ist erlaubt. |

### 2.5 Task

| Feld | Pflicht | Typ und Grenze |
|---|---|---|
| `id` | ja | `k-<Zahl>` oder `k-new-<Zahl>`, z. B. `k-88` oder `k-new-1`. |
| `title` | ja | String, 2 bis 160 Zeichen, Klartext. |
| `description` | nein | Markdown, höchstens 5000 Zeichen, oder `null`. |
| `dueDate` | nein | Wie beim Milestone. |
| `status` | ja | `"open"`, `"in_progress"`, `"review"` oder `"done"`. Siehe Abschnitt 3. |
| `assigneeEmail` | nein | E-Mail eines Mitglieds aus `project.members`, oder `null`. |
| `labels` | nein | Liste von Schlüsseln aus `project.labels`. Jeder Schlüssel 1 bis 40 Zeichen. |
| `commit` | nein | `null`, `"none"` (Altbestand) oder Hash mit 7 bis 40 Zeichen, nur `0-9` und `a-f`. Siehe Abschnitt 5.3. |
| `userStory` | nein | Objekt, siehe Abschnitt 4, oder `null`. |

Die Art einer Aufgabe (`feature`, `bug`, `planning`, `research`, `design`, `blocked`) ist kein eigenes Feld. Sie ist ein Label des Projekts (`labels`, Schlüssel aus `project.labels`).

### 2.6 Nicht im Format

Diese Werte stehen nicht in der Datei, weil die App sie berechnet oder speichert: Status von Milestone und ToDo, Fortschritt, Kommentare, Zeitstempel, Papierkorb-Markierungen. Das Schema verbietet unbekannte Felder (`additionalProperties: false`). Ein Eintrag wie `"status"` am Milestone wird deshalb abgelehnt.

---

## 3. Task-Status

| Status | Markierung in Markdown | Bedeutung |
|---|---|---|
| `idea` | `[i]` | Vorschlag im Projekt, noch nicht beschlossen. |
| `planned` | `[p]` | Beschlossen, noch nicht zur Arbeit freigegeben. |
| `open` | `[ ]` | Offen. Spalte „Offen“ im Board. |
| `in_progress` | `[~]` | In Arbeit. |
| `review` | `[?]` | Zur Prüfung. |
| `done` | `[x]` | Erledigt. |

Die sechs Werte stehen in dieser Reihenfolge. Die Werte `idea` und `planned` kommen aus Entscheidung §8 (planer-abgleich). Sie gehören noch nicht zum Enum der App (Schritt S3).

---

## 4. User-Story

Optionales Feld `userStory` am Task. Es ist ein Objekt aus drei Teilen. Zusammen ergeben sie den Satz „Als … möchte ich …, damit …“.

```json
"userStory": {
  "role": "Mitglied",
  "goal": "meine Tasks nach Status filtern",
  "benefit": "ich in Meetings schnell den Stand zeige"
}
```

| Teil | Pflicht | Grenze |
|---|---|---|
| `role` | ja, wenn `userStory` gesetzt ist | 1 bis 80 Zeichen. |
| `goal` | ja, wenn `userStory` gesetzt ist | 1 bis 160 Zeichen. |
| `benefit` | ja, wenn `userStory` gesetzt ist | 1 bis 160 Zeichen. |

- Alle drei Teile sind Klartext. Keine Formatierung, damit der Satz einheitlich bleibt.
- Ohne Angabe ist das Feld `null` oder fehlt.
- Die Markdown-Ansicht schreibt die Story als ersten Absatz der Beschreibung, kursiv und eingerückt (Abschnitt 7.2).

---

## 5. Regeln

### 5.1 Reihenfolge und Positionen

- **Reihenfolge = Array-Reihenfolge.** Es gibt keine Positionsfelder. So entstehen keine Lücken, wenn Elemente verschoben oder gelöscht werden.

### 5.2 IDs und Konflikte

- **`id`** ist die ID mit Präfix: `m-<Zahl>`, `t-<Zahl>`, `k-<Zahl>` ist die ID in der App (Datenbank, V1 in planer-abgleich §7). `m-new-<Zahl>`, `t-new-<Zahl>`, `k-new-<Zahl>` sind lokal neu entstandene Elemente, die der Import durch die echte ID ersetzt. Eindeutig je Datei. Es gibt kein Feld `appId`.
- **`revision`** ist der Stand der App beim letzten Export oder Import. Nur die App erhöht sie. Die Helfer ändern sie nie.
- **`changed`** setzen die Helfer auf `true`, sobald sie die Datei ändern. `push` ist nur erlaubt, wenn `revision` der Datei gleich der Revision der App ist. `pull` überschreibt keine Datei mit `changed: true`. Nach einem erfolgreichen `push` hat die Datei die neue Revision der App und kein `changed`.
- Ein echtes Zusammenführen gibt es in der ersten Fassung nicht. Der Mensch entscheidet: lokale Änderungen verwerfen (`pull --force`) oder neu auf den aktuellen Stand anwenden.

### 5.3 Releases und Commits

- **`release`** gibt es nur auf Milestones. Fehlt es, hat der Milestone keine Version.
- **Freigabe:** Eine Version mit `release.status: "released"` darf keinen Task mit `status` ungleich `done` haben.
- **Commit-Pflicht:** Ein Task mit `status: "done"` in einer Version mit `release.status: "released"` braucht einen Hash oder `"none"`. `null` ist dort ein Fehler. Die Regel prüft der Validator, nicht das Schema, weil das Schema den Milestone nicht kennt, zu dem der Task gehört.
- **Altbestand:** `"none"` kennzeichnet einen erledigten Task ohne Hash, wie `  Ohne Commit` in Spezifikation v1, Regel 6. Der Wert ist nur bei `status: "done"` erlaubt. Bei anderen Status ist er ein Fehler. Der Validator prüft das.
- **`null`** heißt: kein Commit eingetragen. Bei erledigten Tasks in einer veröffentlichten Version ist das ein Fehler. Für geplante Versionen und offene Tasks ist es normal.
- **Hash:** Nur Kleinbuchstaben und Ziffern, 7 bis 40 Zeichen. Das entspricht der Regel in `src/parse.mjs` der Spezifikation v1.

### 5.4 Labels und Mitglieder

- `assigneeEmail` verweist auf ein Mitglied. Der Validator prüft das (P2), Groß- und Kleinschreibung spielen keine Rolle.
- `project.labels` steht in der Datei: je Label `key` (`a-z`, `0-9`, `_`, `-`, 1 bis 40 Zeichen), `names` (Namen je Sprache, `de` und `en`, je 1 bis 40 Zeichen, Pflicht ist der Name in der Sprache des Projekts), optional `category` und `color` (einer der zehn Töne der App). Reihenfolge = Array-Reihenfolge.
- Der Validator prüft: Schlüssel und Namen sind je Datei eindeutig. Jeder Schlüssel in `labels` eines Tasks ist in `project.labels` vorhanden.

### 5.5 Kanonische Form

- Zwei Leerzeichen Einrückung, feste Schlüsselreihenfolge wie in Abschnitt 1, Zeilenende am Schluss. Abweichungen sind ein Hinweis, kein Fehler. Das sorgt für lesbare Git-Diffs.
- Ein optionales Feld, das `null` ist, fehlt in der kanonischen Form. `null` und fehlend bedeuten dasselbe: nicht gesetzt. Eine leere `labels`-Liste fehlt ebenso. Leere Pflichtlisten (`members`, `todos`, `tasks`) bleiben.
- Dieselben Daten ergeben immer denselben Text. Die Schlüsselreihenfolge der Eingabe spielt keine Rolle.

### 5.6 Leere Container

- Leere ToDos und Tasks sind erlaubt, weil die App sie zulässt. Die Regel aus Spezifikation v1, nach der jeder ToDo und jeder Milestone Inhalt braucht, gilt für planer-v2 nicht.

---

## 6. Formatierung in Beschreibungen

Die Whitelist der App gilt auch für die Datei. Sie steht in `backend/app/Support/RichTextDocument.php` im Repository `mollo-planer`. Der Validator führt dieselbe Liste; ein Test vergleicht beide (P2).

| Wunsch | Markdown | Knoten in der App |
|---|---|---|
| Überschrift Ebene 2 | `## Text` | `heading`, `level` 2 |
| Überschrift Ebene 3 | `### Text` | `heading`, `level` 3 |
| fett | `**Text**` | Mark `bold` |
| kursiv | `*Text*` | Mark `italic` |
| Aufzählung | `- Punkt` | `bulletList` mit `listItem` |
| nummerierte Liste | `1. Punkt` | `orderedList` mit `listItem` |
| Zitat | `> Text` | `blockquote` |
| Link | `[Text](https://…)` | Mark `link` |
| Inline-Code | `` `Text` `` | Mark `code` (die App braucht diesen Mark, Spur B) |
| harter Zeilenumbruch | zwei Leerzeichen am Zeilenende | `hardBreak` |
| Absatz | Leerzeile | `paragraph` |

- **Erlaubte Schemata bei Links:** `http`, `https`, `mailto`.
- **Nicht erlaubt**, der Validator lehnt ab: HTML, Bilder, Tabellen, Code-Blöcke, Überschriften Ebene 1 und ab Ebene 4, Links mit anderem Schema.
- **Titel** (Milestone, ToDo, Task) und die Felder der User-Story sind Klartext ohne Formatierung.
- **Roundtrip:** Für alle Konstrukte der Whitelist gilt `Markdown → RichText → Markdown` ohne Verlust (P5).
- **Grenze:** Verschachtelte Listen mit gemischten Typen gehören nicht zur Whitelist.

Das Schema prüft nur die Länge der Beschreibung. Die Struktur prüft der Validator.

---

## 7. Markdown-Ansicht und Grammatik

Die Markdown-Ansicht ist für Menschen. Die Datei bleibt die Quelle. `mollo-planer render` erzeugt sie aus der Datei (P3). Der umgekehrte Weg, `convert md2json`, folgt in P5 und liest dieselbe Grammatik zurück. Das Beispiel `fixtures/golden/beispiel-planer.md` ist die Referenz für die Ansicht.

### 7.1 Struktur

- Nur nicht eingerückte Zeilen bilden die Struktur:
  - `## vM.m Titel` für einen Milestone mit Version.
  - `## Titel` für einen Milestone ohne Version (`release` fehlt). Erkannt wird er an seiner ID-Zeile `m-<Zahl>` oder `m-new-<Zahl>`.
  - `### Titel` für ein ToDo.
  - `- [ ] Titel`, `- [i] Titel`, `- [p] Titel`, `- [~] Titel`, `- [?] Titel` oder `- [x] Titel` für einen Task.
- Alles, was eingerückt ist, gehört zum darüber liegenden Task.
- Vor jedem Milestone steht eine Trennlinie `---`.

### 7.2 IDs

- **Milestone und ToDo:** eine Zeile direkt unter der Überschrift, `<!-- id: m-12 -->`.
- **Task:** am Ende der Task-Zeile, `- [x] Titel <!-- id: k-88 -->`.

### 7.3 Inhalt eines Tasks

Unter der Task-Zeile folgen die Blöcke in dieser Reihenfolge, jeweils durch eine Leerzeile getrennt. Eingerückt wird um zwei Leerzeichen.

1. **Story:** `  *Als <Rolle> möchte ich <Ziel>, damit <Nutzen>.*`. Der Konverter erkennt das feste Muster „Als … möchte ich …, damit …“. Passt das Muster nicht, ist es ein Fehler.
2. **Metadaten:** als Aufzählung, in dieser Reihenfolge und nur, wenn der Wert gesetzt ist: `  - Fällig: <Datum>`, `  - Zuständig: <E-Mail>`, `  - Labels: <Name>, <Name>` (die Namen, nicht die Schlüssel), `  - Commit: <Hash>`. Für `commit: "ohne"` steht `  - Commit: Ohne Commit`.
3. **Beschreibung:** eingerückt. Eine Überschrift darin ist `  ### Hinweis` und kein ToDo. Das gilt auch für Listen und Zitate.

**Legende der Labels.** Ganz am Schluss, nach dem letzten Milestone, steht ein Trenner `---` und der Abschnitt `## Labels` mit einer Tabelle `| Schlüssel | Name | Kategorie | Farbe |` und der Trennzeile `|---|---|---|---|`. Jede Zeile ist ein Label aus `project.labels`. Ohne Labels fehlt der Abschnitt. Nach der Legende folgt nichts mehr. Name und Kategorie dürfen weder `|` noch `,` enthalten, sonst bricht die Ansicht. Der Konverter ordnet die Namen aus den Metadaten über diese Legende den Schlüsseln zu. Ein Name ohne Legendeneintrag ist ein Fehler mit Zeilenangabe.

### 7.4 Milestone und ToDo

- Die Metadaten stehen nicht eingerückt, direkt nach der ID-Zeile. Milestone: `- Status: <Status>` (nur mit `release`), `- Fällig: <Datum>`. ToDo: `- Fällig: <Datum>`.
- Die Beschreibung steht nicht eingerückt, nach den Metadaten. Sie darf keine Zeile haben, die mit `#` oder mit `- [` beginnt. Sonst würde sie als Struktur gelesen. Der Renderer bricht in diesem Fall mit einer Meldung ab. Der Autor muss die Stelle umformulieren oder einrücken.

### 7.5 Projektkopf

- `<!-- Ansicht von planer-v2, revision <N>, lang de. … -->` als erste Zeile. Ist `changed` gesetzt, steht `, geändert` hinter der Revision. `lang` ist die Sprache der festen Wörter (`de` oder `en`, Abschnitt 5.3).
- `# <Projektname>`, dann `- Sprache: <locale>`, dann die Projektbeschreibung, falls gesetzt.
- `## Mitglieder` mit einer Zeile je Mitglied: `- <E-Mail> | <Rolle>`.

### 7.6 Trennung

- Leerzeilen trennen die Blöcke. Ohne sie fasst Markdown Zeilen zu einem Absatz zusammen.

### 7.7 Beispiel-Abweichungen

Die Referenz `fixtures/golden/beispiel-planer.md` ist die Ansicht des Beispiels aus `mollo-planer/docs/beispiel-planer.md`. Sie weicht an drei Stellen ab:
- Kopfzeile: sie nennt Revision und Zweck der Ansicht, nicht den Ort des Beispiels.
- Rolle: `owner` statt des Platzhalters `OWNER`. Die Werte der Datei sind die `@enum`-Werte der App (Abschnitt 2.2).
- Story von k-001: der Wortlaut ist in der Fixture umformuliert. Die Grammatik ist dieselbe.

---

## 8. Grenzen auf einen Blick

| Element | Grenze |
|---|---|
| Projektname | 2 bis 120 Zeichen |
| Projektbeschreibung | höchstens 2000 Zeichen |
| Mitglieder | höchstens 50 |
| Milestones | mindestens 1, höchstens 30 |
| ToDos je Milestone | höchstens 50 |
| Tasks je ToDo | höchstens 50 |
| Titel (Milestone, ToDo, Task) | 2 bis 160 Zeichen |
| Beschreibung (Milestone, ToDo, Task) | höchstens 5000 Zeichen |
| Label-Name je Task | 1 bis 40 Zeichen |
| `userStory.role` | 1 bis 80 Zeichen |
| `userStory.goal`, `userStory.benefit` | je 1 bis 160 Zeichen |
| Commit-Hash | 7 bis 40 Zeichen, `0-9` und `a-f` |
| `release.version` | `M.m`, ganze Zahlen |

---

## 9. Schema und Validator

- **Schema:** `spec/planer-v2.schema.json`, JSON Schema Draft 2020-12. Es ist die Form der Datei. Es gibt keine zweite Beschreibung der Form.
- **Was das Schema prüft:** Felder, Typen, Pflichtfelder, unbekannte Felder, Formate (Datum, E-Mail), Längen, Muster (IDs, Hash, Version), Aufzählungen (Status, Rolle, Locale), Anzahlen.
- **Was das Schema nicht prüft** (Validator, P2):
  - Eindeutigkeit der IDs in der Datei.
  - `assigneeEmail` ist ein Mitglied, `labels` sind Schlüssel aus `project.labels`.
  - Release-Regel: keine offenen Tasks in einer veröffentlichten Version.
  - Commit-Pflicht für `done`-Tasks in einer veröffentlichten Version, also Hash oder `"none"` statt `null`.
  - `"none"` nur bei `status: "done"`.
  - Whitelist der Formatierung und das Muster der Story in der Markdown-Ansicht. Stand P2: nicht geprüft, folgt mit der Markdown-Ansicht (P3 und P5).
  - Labels in `labels` sind in `project.labels` vorhanden (Abschnitt 5.4).
  - Kanonische Form (Hinweis, kein Fehler).
- **Garantie:** Eine Datei, die der Validator ohne Fehler annimmt, ist gültig für planer-v2 und importierbar. Dass der Stand der App dazu passt, prüft der Roundtrip-Test.

---

## 10. Entschieden (Abschluss der offenen Punkte)

Diese Punkte sind entschieden. Sie gelten für planer-v2.

1. **Schreibweise der Werte: klein und englisch in der Datei** (`locale` `de`/`en`, `planned`/`released`, `none`). Entschieden in planer-abgleich §5.1. Die Ansicht übersetzt die festen Wörter.
2. **Altbestand ohne Hash: `"none"`.** Ein erledigter Task ohne Hash bekommt `commit: "none"`. `null` bleibt „kein Commit eingetragen“ und ist bei erledigten Tasks in einer veröffentlichten Version ein Fehler. Begründung: `null` allein war mehrdeutig, und v1 kennzeichnet den Altbestand ausdrücklich.
3. **Ort der `$schema`-Referenz: ohne Wirkung.** Das Schema liegt fest in `spec/planer-v2.schema.json` im Repository `mollo-planer`. Der Validator prüft immer dagegen und ignoriert `$schema`. Das Feld ist nur ein Hinweis für Editoren. Begründung: Ein relativer Pfad aus dem Projektordner kann nicht überall stimmen, und ein Feld, das die Prüfung beeinflusst, wäre eine zweite Quelle.
4. **Grammatik-Tests kommen mit P5, nicht mit P1.** Die Tests prüfen `md2json` und die Ansicht. Beides entsteht erst in P3 und P5. P1 umfasst Spezifikation und Schema. Die Anleitung in `planer-datei-ssot.md` §9 Punkt 3 wird mit P5 umgesetzt. Die Planung in `mollo-planer` bleibt unverändert, wie vorgegeben.
5. **Mindestlänge von `userStory`-Teilen: 1 Zeichen.** Die Teile sind Pflicht, ein leerer Teil wäre kein Teil. Die Höchstlängen bleiben wie geplant.

---

6. **Inline-Code in Beschreibungen: entschieden, erlaubt.** Das Beispiel aus `mollo-planer/docs/beispiel-planer.md` nutzt Inline-Code. Die Whitelist bekommt den Mark `code`. Die App braucht dafür einen Code-Mark in `RichTextDocument.php` und im Editor (Spur B, offen).

---

## 11. Konvertierung

Zwei Befehle verbinden die Formate. Beide lesen nur, und die Ausgabe geht nach stdout oder nach `--out`.

### 11.1 v1 nach planer-v2

`mollo-planer convert v1-zu-v2 <version-<name>.md> [--name <Projektname>] [--locale DE|EN] [--out <datei>]`

| v1 | planer-v2 |
|---|---|
| `## vM.m Titel` mit Status | Milestone mit `release` `{ version: "M.m", status }`. Fehlt der Status, gilt `planned`. |
| Beschreibung unter der Überschrift | `description` des Milestones |
| `### Titel` | ToDo |
| `- [x] [art] Titel` | Task mit `status: "done"`, `labels: ["art"]` |
| `- [ ] [art] Titel` | Task mit `status: "open"`, `labels: ["art"]` |
| `  Commit <Hash>` | `commit: "<Hash>"` |
| `  Ohne Commit` | `commit: "none"` |
| eingerückte Zeilen unter dem Task | `description` des Tasks |

- **IDs:** Der Konverter vergibt `m-new-1`, `t-new-1`, `k-new-1`, … je Art in Dokumentreihenfolge.
- **Projekt:** `name` aus dem Dateinamen (`version-<name>.md` ergibt `<name>`) oder aus `--name`. Ist der Name zu kurz oder fehlt er, verlangt der Befehl `--name`. `locale` ist `de`, außer `--locale` sagt etwas anderes (`DE` oder `EN`). `members` ist leer, weil v1 keine Mitglieder kennt. `revision` ist `1`.
- **Gültigkeit:** Die v1-Datei muss gültig sein, sonst bricht der Befehl ab. Das Ergebnis wird wie jede planer-v2-Datei geprüft.

### 11.2 Markdown-Ansicht nach planer-v2

`mollo-planer convert md2json <datei.md> [--out <datei>]`

- Liest die Ansicht nach Abschnitt 7. Die `revision` steht in der Kopfzeile. Die Datei ist danach eine planer-v2-Datei und wird geprüft.
- Fehler nennen die Zeile. Ein Task ohne ID-Zeile ist ein Fehler, ebenso eine Metadatenzeile, die nicht zu den Schlüsseln gehört. Eine eingerückte Zeile mit einem Schlüssel wie `- Fällig:` in der Beschreibung eines Tasks wird als Metadatum gelesen. Das ist die einzige bekannte Mehrdeutigkeit.
- `$schema` ist kein Inhalt. Die Ansicht trägt es nicht, deshalb stellt der Roundtrip es nicht wieder her.

### 11.3 Roundtrip

- `render` → `convert md2json` → kanonische Form ergibt dieselbe Datei. Geprüft für das Beispiel und die gültige Fixture (ohne `$schema`).
- v1 → planer-v2 → Markdown → planer-v2 ergibt denselben Stand. Geprüft für die gültige v1-Fixture und für die echte Versionsdatei aus `club-db` (17 Milestones, 39 ToDos, 107 Tasks), die nur gelesen wurde.

## 8. Vorlagen

Eine Vorlage ist ein gültiges planer-v2-Dokument mit festen Werten (planer-abgleich §3.8): `revision` 0, nur `-new-`-IDs, `members` leer, keine Zuweisungen, Fälligkeiten und Commits, `release.status` nur `planned`, Task-Status `planned`, mindestens ein Label. `validate --template` prüft die Datei nach diesen Regeln zusätzlich zur normalen Prüfung. `template <datei> [--out <datei>]` setzt die Werte. Die mitgelieferten Vorlagen liegen in `spec/templates/`.
