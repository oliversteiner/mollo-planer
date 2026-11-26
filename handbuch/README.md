# Handbuch: Leitfaden für Kapitel

Das Handbuch hat **eine Quelle**: ein Kapitel je Funktion als Markdown, deutsch in `handbuch/de/`, englisch in `handbuch/en/`, mit gleichem Dateinamen. Der Build (`npm run manual` im Ordner `frontend/`) macht daraus:

- die **Hilfe in der App** (`/help`, nur angemeldet, mit Bildern),
- die **Ausgabe für Agenten** (`/manual/` englisch, `/manual/de/`: HTML ohne Skript, Markdown, `llms.txt`, ohne Bilder und ohne die Schritte für Menschen; ein Kapitel ohne «Für Agenten» bekommt dort einen Satz, der auf die Hilfe der App verweist),
- die **Beispielprompts** (in beiden).

Konzept und Entscheide: `docs/handbuch.md`. Wer ein Kapitel schreibt (Mensch oder Agent), hält sich an diesen Leitfaden; die Musterkapitel leben ihn vor.

## Ein Kapitel

Dateiname: kurz, klein, mit Bindestrichen, gleich in beiden Sprachen (`task-anlegen.md`). Die `id` im Kopf ist derselbe Name.

```markdown
---
id: task-anlegen
title: Task anlegen und bearbeiten
area: plan
scope: app
right: task.create
pages: /projects/[id]/kanban, /projects/[id]
tools: list_tasks, update_tasks
commands:
since: 1.6
---

## Wozu

Ein, zwei Sätze: was die Funktion bringt.

## So geht's

1. Öffne …
2. Wähle {{ui:kanban.addTask}}.

   {{bild:neuer-task|Das Formular «Neuer Task» mit Titel und Status}}

3. …

## Gut zu wissen

- Grenzen, Sonderfälle, typische Fehler.

## Für Agenten

Welche Werkzeuge, welche Felder, was über MCP nicht geht.

## Beispielprompts

- Ein Prompt zum Kopieren, mit allem, was der Agent wissen muss, Platzhalter wie [Projekt] in eckigen Klammern.
- …
```

### Kopf

| Feld | Inhalt |
|---|---|
| `id` | Name des Kapitels, gleich wie der Dateiname, gleich in beiden Sprachen |
| `title` | Titel in der Sprache der Datei |
| `area` | Bereich: `first-steps`, `projects`, `plan`, `kanban`, `my-tasks`, `comments`, `documents`, `ideas`, `assistant`, `templates`, `trash`, `statistics`, `calm`, `planer-file`, `agents`, `admin` |
| `scope` | `core` (Basis: Konten, Rechte, Verwaltung) oder `app` (Planung) |
| `right` | Schlüssel des nötigen Rechts (`task.create`, `users.manage` …) oder leer |
| `pages` | Seiten der App, mit Komma getrennt, wie ihre Route (`/admin/users`, `/projects/[id]/kanban`) |
| `tools` | Werkzeuge des MCP-Servers, mit Komma getrennt |
| `commands` | Einträge der Befehlspalette (ihre `id`, zum Beispiel `page-users`), mit Komma getrennt |
| `since` | Version, seit der es die Funktion so gibt |

`pages`, `right`, `tools` und `commands` prüft ein Test gegen den Code: Was es nicht gibt, ist ein Fehler.

### Abschnitte

Genau diese Überschriften, in dieser Reihenfolge. Ein Abschnitt darf fehlen (zum Beispiel «Für Agenten», wenn es kein Werkzeug gibt), keiner darf dazukommen.

| deutsch | englisch | Inhalt | geht in |
|---|---|---|---|
| `## Wozu` | `## Purpose` | ein, zwei Sätze | Hilfe, Agenten |
| `## So geht's` | `## How to` | **nummerierte** Schritte, ein Handgriff je Schritt, Bilder darunter | Hilfe |
| `## Gut zu wissen` | `## Good to know` | Grenzen, Sonderfälle, typische Fehler | Hilfe, Agenten |
| `## Für Agenten` | `## For agents` | Werkzeuge, Felder, was über MCP nicht geht | Agenten |
| `## Beispielprompts` | `## Example prompts` | zwei bis vier Prompts als **Aufzählung**, ein Punkt je Prompt | Hilfe, Agenten |

Beide Sprachen haben dieselben Abschnitte, dieselbe Zahl von Schritten und dieselbe Zahl von Prompts (ein Test prüft das).

### Erlaubtes Markdown

Absätze, `###` Zwischentitel, Listen, Zitate, **fett**, *kursiv*, `Code`, Links mit `https://`. Kein HTML, keine Tabellen, keine Codeblöcke, keine Bilder in Markdown-Schreibweise (der Build weist sie ab).

### Bezeichnungen der Oberfläche

Tasten, Menüpunkte und Felder werden nicht abgetippt, sondern über ihren Schlüssel in `frontend/i18n/locales/de.json` und `en.json` eingesetzt:

```markdown
Wähle **{{ui:admin.users.newAgent.title}}**.
```

Der Build setzt den Text der jeweiligen Sprache ein. Wird die Taste in der App umbenannt, steht nach dem nächsten Build der neue Name im Kapitel. Nur Schlüssel mit reinem Text (ohne `{…}` und ohne Mehrzahlformen) gehen; ein Test prüft, dass jeder Schlüssel in beiden Sprachen existiert.

## Anhang

Ein Anhang ist ein Kapitel mit `type: appendix` im Kopf (Beispiel: `anhang-schreibregeln.md`). Er hat keine festen Abschnitte, keine Pflicht zu `area`, `scope`, `since`, `right`, `pages` und `tools`; die Überschriften (Ebene 2 und 3) wählt die Autorin oder der Autor. Beide Sprachen brauchen eine Datei mit derselben `id`; Inhalt und Abschnitte dürfen abweichen (die englischen Schreibregeln beschreiben englische Regeln und sind keine Übersetzung). Die Hilfe zeigt Anhänge in einem eigenen Bereich «Über dieses Handbuch», in `/manual/` und `llms.txt` stehen sie zuletzt.

## Sprache und Stil

Alles Sprachliche (Bedienung beschreiben, Sprachregeln für Menschen, Wörterliste) steht im Kapitel «Schreibregeln»: `handbuch/de/anhang-schreibregeln.md`. Diese Datei beschreibt nur das Technische.

## Bilder

Ein Bild ist ein **Platzhalter auf einer eigenen Zeile**, eingerückt unter dem Schritt, den es zeigt, mit **Leerzeile davor** (sonst gehört die Zeile zum Text des Schritts):

```markdown
2. Wähle **{{ui:kanban.addTask}}**.

   {{bild:neuer-task|Das Formular «Neuer Task» mit Titel und Status}}
```

- **Ausschnitt statt ganzer Bildschirm:** die Aufnahme zeigt den Dialog, das Formular oder den Kopf mit den Reitern (`locator.screenshot()`), ohne Mauszeiger, bei der Breite der Hilfe lesbar, nie halb gescrollt.
- **Sprache der Aufnahme:** Gebietsschema und Zeitzone der Sprache (`de-CH`, `en-GB`), Daten im deutschen Bild deutsch. Testdaten neutral und sprechend («Entwurf prüfen», nicht «E2E …», kein Platzhalter-Latein).
- Name: klein, mit Bindestrichen, gleich in beiden Sprachen. Nach dem `|` der Alternativtext in der Sprache der Datei.
- Ein Bild zeigt nichts, was der Text nicht sagt: Ohne Bilder bleibt das Kapitel vollständig.
- **Automatische Bilder:** je Kapitel eine Aufnahme-Datei `handbuch/aufnahmen/<kapitel>.mts`; `npm run manual:shots` (im Ordner `frontend/`) nimmt jedes Bild in Deutsch und Englisch auf, gegen die Wegwerf-Datenbank der Browser-Tests (nie TEST oder PROD), nur mit Testdaten. Ergebnis: `handbuch/bilder/auto/<sprache>/<kapitel>/<name>.webp`.
- **Eigene Bilder** ersetzen ein automatisches mit gleichem Namen: `handbuch/bilder/eigen/<sprache>/<kapitel>/<name>.webp` (oder `.png`), für ein Bild ohne Text in beiden Sprachen `handbuch/bilder/eigen/alle/<kapitel>/<name>.webp`. Höchstens 1280 px breit und 300 KB. Das Skript schreibt nie in `eigen/`.
- **Prüfliste:** `handbuch/bilder/pruefen.md` nennt die Aufnahmen, bei denen das Skript Zweifel hatte (abgeschnitten, Ladeanzeige, leer, Zeitüberschreitung). Solche Bilder bei Bedarf durch eigene ersetzen.

## Was noch kein Kapitel hat

`handbuch/ohne-kapitel.txt` führt die Seiten, Rechte, Einträge der Befehlspalette und Werkzeuge, die noch in keinem Kapitel vorkommen. Ein Test schlägt an, wenn etwas weder in einem Kapitel noch in der Liste steht, und wenn die Liste etwas nennt, das inzwischen ein Kapitel hat. Die Liste wird nur kürzer.

## Nach einem Meilenstein (Schreib-Persona)

1. **Lesen:** die Tasks und PRs des Meilensteins im Planer, und was sich geändert hat: Seiten (`frontend/app/pages/`), Rechte (`backend/app/Enums/Right.php`), Werkzeuge (`backend/app/Tools/`), Befehlspalette, Sprachdateien.
2. **Kapitel anlegen oder anpassen:** zuerst deutsch, dann englisch, nach diesem Leitfaden. Jede Funktion, die der Meilenstein neu bringt, bekommt ein Kapitel oder einen Platz in einem bestehenden.
3. **Schritte nachvollziehen:** am laufenden System (lokal), nicht aus dem Code raten.
4. **Bilder:** Aufnahme-Datei anlegen oder anpassen, dann `npm run manual:shots`; `pruefen.md` lesen.
5. **Liste kürzen:** was jetzt ein Kapitel hat, aus `handbuch/ohne-kapitel.txt` streichen.
6. **Bauen und prüfen:** `npm run manual`, `npm run lint`, `npm run typecheck`, `npm test` (alle Exit 0).
7. **PR nach `dev`**, Bericht am Task im Planer: welche Kapitel, welche Bilder, was in `pruefen.md` steht.
