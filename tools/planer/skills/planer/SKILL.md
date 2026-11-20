---
name: planer
description: Ändert die Planung eines Projekts in der Datei docs/<projekt>.planer.json (Format planer-v2) über die Helfer von mollo-planer. Nutzen, wenn Milestones, ToDos oder Tasks angelegt, verschoben oder in ihrem Status geändert werden sollen, und wenn der Stand einer Aufgabe gefragt ist.
---

# Planer-Datei ändern

Die Datei `docs/<projekt>.planer.json` ist die Planung. Ändere sie nur über die Helfer. Schreibe sie nie komplett neu. Die Helfer prüfen jede Änderung und schreiben erst, wenn die Prüfung besteht.

Aufruf im Projektordner, aus dem Repository von `mollo-planer`:

```bash
node /pfad/zu/mollo-planer/tools/planer/cli/bin/mollo-planer.mjs <befehl> …
```

Ist nur eine `docs/*.planer.json` im Ordner, genügt der Befehl. Mehrere Dateien: mit `--datei <pfad>` angeben.

## Befehle

| Befehl | Zweck | Ausgabe |
|---|---|---|
| `list` | Überblick: IDs, Titel, Status. Ohne Beschreibungen. | Eine Zeile je Element |
| `show <id>` | Ein Element mit allen Kindern, als JSON | JSON |
| `revision` | Stand der App (ändern die Helfer nie) | Zahl |
| `add milestone --title T [--version M.m [--release-status planned\|released]] [--due JJJJ-MM-TT]` | Milestone anlegen, ans Ende | `m-new-N angelegt` |
| `add todo --milestone m-<Zahl> --title T [--due …]` | ToDo anlegen, ans Ende | `t-new-N angelegt` |
| `add task --todo t-<Zahl> --title T [--status …] [--assignee E-Mail] [--label L]… [--commit H\|none] [--due …]` | Task anlegen, ans Ende | `k-new-N angelegt` |
| `add <art> --json` | Element mit Beschreibung, userStory und allem übrigen. JSON auf stdin, ohne `id` | wie oben |
| `move <id> [--to <ziel>] [--position N]` | Verschieben. Task: `--to` ist ein ToDo. ToDo: `--to` ist ein Milestone. Ohne `--to` bleibt der Elternteil. | `<id> verschoben` |
| `status <k-<Zahl>> idea\|planned\|open\|in_progress\|review\|done [--commit H\|none]` | Status eines Tasks setzen | `k-<Zahl>: alt → neu` |

## Regeln

- **IDs vergibt der Helfer.** Gib keine `id` an. Die nächste freie Nummer kommt aus der Ausgabe. Die ID bleibt beim Verschieben gleich.
- **Jede Änderung setzt `"changed": true`.** Die `revision` ändern die Helfer nie, sie kommt nur von der App. Lesebefehle ändern nichts.
- **Eine Änderung, die die Prüfung nicht besteht, wird abgelehnt.** Die Meldung nennt den Pfad, z. B. `milestones[1].todos[0].tasks[2].assigneeEmail`. Die Datei bleibt dann unverändert. Korrigiere den Aufruf und versuche es erneut.
- **Veröffentlichte Versionen** (`release.status` = `released`) haben keine offenen Tasks. Erledigte Tasks dort brauchen einen Commit: `--commit <Hash>`, oder `--commit none` für Altbestand.
- **`--commit none`** ist nur bei `status done` erlaubt.
- **Längere Inhalte per JSON.** Eine Beschreibung mit Absätzen oder Listen, eine userStory oder mehrere Felder: `add task --todo t-<Zahl> --json` und das Objekt auf stdin. Das Objekt enthält `title` und die Felder, aber keine `id`.

## Grenzen

- Beschreibungen von Milestones und ToDos dürfen keine Zeile mit `#` oder `- [` am Anfang haben. Sonst liest die Markdown-Ansicht sie als Struktur. Das Werkzeug lehnt so etwas nicht ab, der Renderer bricht ab. Solche Stellen einrücken oder umformulieren.
- Labels (`labels`) sind Schlüssel aus `project.labels`. `add task --label` nimmt Namen oder Schlüssel und trägt den Schlüssel ein. Ein unbekanntes Label endet mit Code 2.
- `status` gilt nur für Tasks. Für Milestones und ToDos gibt es keinen Status in der Datei. Er wird von der App berechnet.

- **Veröffentlichungsdatum:** `release.date` (`JJJJ-MM-TT`) ist immer optional. Bei `released` ohne Datum gibt `validate` einen Hinweis, bei `planned` mit Datum einen Fehler. In der Markdown-Ansicht steht es als `- Veröffentlicht: <Datum>` unter dem Status.
- **Aus einer v1-Datei umwandeln:** `convert v1-zu-v2 <version-*.md> [--name N] [--release-dates <csv>] [--labels <json>]` nimmt jede Art als Label an, kürzt Titel über 160 Zeichen (der ganze Titel bleibt in der Beschreibung) und meldet am Ende jede Kürzung, jeden Task mit mehreren Commits und die Zahlen. Die Datei bleibt unverändert, das Ergebnis geht nach stdout, die Hinweise nach stderr. Regeln: `spec/format-v2.md` §11.1.

## Typischer Ablauf

1. `list`, um die Aufgabe zu finden.
2. `show <id>`, falls Beschreibung oder Kinder gebraucht werden.
3. Die Änderung mit einem Helfer machen. Die Ausgabe enthält die neue ID und die Revision.
4. Nicht die ganze Datei erneut lesen.

- **Vorlagen:** `mollo-planer template <datei> [--out <datei>]` macht aus einer Planer-Datei eine Vorlage (neue IDs, Status `planned`, ohne Zuweisungen und Fälligkeiten). `mollo-planer validate <datei> --template` prüft sie.

## Mit der App abgleichen

Die App ist die Quelle der Revision, die Datei im Repository die Arbeitskopie. Verbindung: `MOLLO_PLANER_URL` und `MOLLO_PLANER_TOKEN` in der Umgebung oder `.mollo-planer` (JSON, gehört nicht ins Repository); das Projekt mit `--project <id>`, `MOLLO_PLANER_PROJECT` oder `projects` in `.mollo-planer`. Den Zugang nie als Option, nie in den Chat, nie ins Repository. `.mollo-planer` mit Zugang gehört dem Benutzer allein: `chmod 600 .mollo-planer` (das Werkzeug warnt, wenn andere sie lesen können).

| Befehl | Was es macht |
|---|---|
| `pull [datei] [--project <id>] [--force]` | Holt den Plan aus der App und schreibt die Datei kanonisch. „unverändert“, wenn nichts neu ist. Hat die Datei `changed`, bricht es ab (Exit 3); `--force` verwirft die Änderungen. |
| `push [datei] [--partial] [--force] [--yes]` | Trägt die Datei ein. Zeigt **zuerst** den Trockenlauf (Anzahl je Art, Löschungen mit Titel), schreibt danach die echten IDs und die neue Revision in die Datei. |
| `sync [datei] [--yes]` | Mit `changed`: `push`, danach `pull`. Sonst `pull`. |
| `pull --watch [--interval 60]` | `pull` in einer Schleife, mindestens 10 s Abstand. |

- **Vor der Arbeit `pull`.** So arbeitest du auf der aktuellen Revision. Die Revision änderst du nie von Hand.
- **Danach ein Helfer, dann `push`.** `push` trägt nichts ein, wenn die Datei kein `changed` hat. Weicht die Revision der App ab, endet es mit Exit 4: zuerst `pull`, die eigenen Änderungen mit den Helfern neu anwenden.
- **Löschungen nur mit `--yes`, und nur nach Rückfrage beim Menschen.** Ohne `--yes` endet `push` mit Exit 5 und der Liste. Was fehlt, kommt in der App in den Papierkorb und lässt sich dort zurückholen.
- **Nie committen ohne Auftrag.** Das Werkzeug committet nie.
- **Exit-Codes:** 0 ok oder unverändert, 1 Aufruf falsch, 2 Datei ungültig oder Fehler der App (mit Pfad), 3 lokale Änderungen, 4 Revision weicht ab, 5 Löschungen ohne `--yes`, 6 Verbindung oder Zugang (Zugänge laufen nach 90 Tagen ab).
