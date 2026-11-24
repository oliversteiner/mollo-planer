# 43 · MCP und Arbeit mit dem Planer: so wenig Tokens wie nötig, Doku unter /mcp für Agenten

Planer: planer.mollo.dev · Projekt 2 «Mollo Planer» · ToDo `t-378` (Status der Teile dort nachführen).

Status: Teile A bis E gemergt (PR #41). Folgepunkte k-1482 (Kommentare je Task begrenzt), k-1484 (OAuth, Variable `MOLLOPLANER_<NAME>_TOKEN`, `.mcp.json`, Titelgrenze in `llms.txt` und `/mcp`) auf Branch `fix/mcp-folgepunkte`, PR nach dev, nicht gemergt; k-1483 (`mcp_tool_calls.user_id` im Verzeichnis der Konto-Verweise) erledigt mit Auftrag 37 E (PR #46). E4 (`AGENTS.md`, `.junie/AGENTS.md`) siehe Stuart. Ursprünglich: Status: offen. Auftrag von Oliver über Stuart (2026-11-23). Nach 42 A. Branch von `dev` (`feature/mcp-sparsam`), PR nach `dev`, nicht mergen. Teile je ein Commit. Werkzeuge nur in `App\Tools` (Auftrag 34), der Assistent profitiert mit.

## Messung (Stuart, 2026-11-23, Stand `dev`)

| Was | Zeichen | rund Tokens |
|---|---|---|
| `tools/list` (12 Werkzeuge, Name + Beschreibung + Schema) | 9 715 | 2 400 |
| Instruktionen des Servers | rund 2 600 | 650 |
| `export_plan` Club DB ganz (31 Meilensteine, 113 ToDos, 389 Tasks) | 94 530 | **23 600** |
| davon Titel 41 %, Beschreibungen 22 %, IDs 8 %, Status 6 %, Commit 5 % | | |
| davon Tasks mit Status `done`: 312 von 389 (80 %) | | |

Folgerung: Der feste Aufwand (Werkzeugliste, Instruktionen) ist in Ordnung. Teuer ist, dass ein Agent für fast alles den **ganzen Plan** liest: zur Orientierung, um eine Task zu finden, und nur um `base_revision` für `update_tasks` zu bekommen. Erledigtes macht 80 % davon aus. JSON ist schon kompakt (keine Einrückung); am Format selbst ist wenig zu holen.

## A. Lesen in Stufen

1. **`export_plan` mit `view: "outline"`:** nur Projekt, Revision und je Meilenstein `id`, `title`, `version`, `released`, Status und Zähler der Tasks je Status. Keine ToDos, keine Tasks, keine Beschreibungen. Ziel Club DB: unter 1 500 Tokens. Ergebnis trägt `excerpt: true`.
2. **`include_descriptions`** (Vorgabe `true`, damit nichts bricht): `false` lässt alle Beschreibungen weg; Ergebnis ist dann `excerpt: true` (nur mit `mode: "partial"` zurückschreibbar, fehlende Beschreibung heisst «unverändert», nie «leeren»). Test dafür.
3. **Neues Werkzeug `list_tasks`** (nur lesen): flache, knappe Liste statt Baum. Filter: `project_id` (Pflicht), `status[]`, `assignee` (`"me"` oder E-Mail), `milestone_ids[]`, `query` (Titel), `ids[]`; `limit` (Vorgabe 30, höchstens 100), `cursor`. Je Task: `id`, `title`, `status`, `todo` (`id`, `title`), `milestone` (`id`, `title`), `assigneeEmail`, `commit` nur wenn gesetzt. Dazu einmal `revision`. Mit `ids[]` und `include: ["description", "comments"]` liefert es **eine Task mit allem**, was man zum Bearbeiten braucht (Kommentare: die letzten 5, gekürzt wie in `list_comments`). Feste Abfragezahl.
4. Leere und Vorgabe-Werte in allen Antworten weglassen (`null`, `[]`, `""`), sofern das Format v2 es erlaubt; prüfen und melden, was es bringt.

## B. Schreiben ohne Umweg

1. **`update_tasks`: `base_revision` wird optional.** Ohne Angabe gilt die Änderung je Task und Feld (Status, Commit, Zuweisung sind unabhängig voneinander; kein Verlust fremder Änderungen an anderen Feldern oder Tasks). Mit Angabe wie heute. Begründung: Heute erzwingt jede fremde Änderung im Projekt einen neuen Export.
2. **Konflikt mit `base_revision`:** Antwort enthält die aktuelle `revision` und den **aktuellen Stand nur der angefragten Tasks**, damit der Agent ohne `export_plan` neu entscheiden kann.
3. **Antwort knapp:** `ok`, `revision`, je geänderte Task `id` und nur die geänderten Felder (alt → neu). Unveränderte Tasks nur als Zahl.
4. **Kein Pflicht-Trockenlauf bei `update_tasks`:** Es ist atomar und geprüft; `dry_run` bleibt möglich. In Instruktionen und Doku den Satz «run dry_run first» nur noch für `import_plan` (verdoppelt sonst jeden Aufruf).
5. **`import_plan`:** Fehler und Warnungen auf die ersten 20 begrenzen, dazu die Gesamtzahl; `idMap` nur für neue IDs.

## C. Fester Aufwand

* Instruktionen und Beschreibungen entdoppeln: Regel steht **einmal** (Instruktionen: Ablauf und Regeln; Beschreibung: was das Werkzeug tut; Schema: Parameter). Ziel: `tools/list` plus Instruktionen zusammen höchstens 11 000 Zeichen trotz neuem Werkzeug. Die grössten Schemas (`list_comments` 1 367, `import_plan` 1 354, `update_tasks` 1 129, `get_project_statistics` 1 064) zuerst.
* Neuer Ablauf in den Instruktionen (ersetzt den heutigen):
  1. `list_projects` (liefert Revision).
  2. Überblick: `get_project_statistics` oder `export_plan view:"outline"`.
  3. Arbeit finden: `list_tasks` (z. B. `assignee:"me"`, `status:["open","in_progress"]`).
  4. Eine Task lesen: `list_tasks ids:[…] include:[…]`.
  5. Status, Commit, Zuweisung: `update_tasks` direkt.
  6. Struktur ändern: gefilterter `export_plan` (ein Meilenstein) → `import_plan mode:"partial"`, vorher `dry_run`.
  7. Ganzer Export nur, wenn der ganze Plan umgebaut wird.
* **Budget-Tests:** Zeichen von `tools/list` + Instruktionen; `outline`, `list_tasks` (30 Zeilen) und ganzer Export auf einer festen Fixture (Grösse wie Club DB). Die Tests halten Obergrenzen fest, damit es nicht wieder wächst.
* **Messen im Betrieb:** je Werkzeugaufruf eine Logzeile (Werkzeug, Konto-ID, Zeichen der Antwort, Dauer), keine Inhalte. Ein Befehl `app:mcp-usage` fasst die letzten 7 Tage zusammen (Aufrufe und Zeichen je Werkzeug und Agent).

## D. Assistent (gleiche Werkzeuge)

* Die neuen Stufen auch im Assistenten nutzen (kompakter Baum statt ganzem Export, wo es reicht).
* **Prompt-Caching** der Anthropic-API für den festen Teil (Instruktionen, Werkzeuge) prüfen und einschalten, wenn die Bibliothek es kann; Wirkung mit Zahlen melden. Vorher die aktuelle Doku lesen, nicht aus der Erinnerung bauen.

## E. Doku unter `/mcp` (für Agenten)

Heute: `frontend/public/mcp/index.html` (en) und `de/index.html`, je rund 12 000 Zeichen HTML. Fehler: «elf Werkzeuge» (es sind 12, `get_project_statistics` fehlt), «Claude Code: in `.mcp.json` bereits eingetragen» (seit `dcd410f` entfernt), «Trockenlauf zuerst» auch für `update_tasks`.

1. **Neue Datei `/mcp/llms.txt`** (reines Markdown, englisch, höchstens 4 000 Zeichen): das, was ein Agent braucht, ohne HTML zu lesen. Aufbau:
   * Was es ist, Endpunkte TEST/PROD, Anmeldung (Bearer, eigenes Agent-Konto), ein Satz zur Sicherheit des Tokens.
   * **Günstig arbeiten** (der Ablauf aus C als nummerierte Liste, je Schritt die Grössenordnung in Tokens).
   * **Rezepte**, je 2 bis 4 Zeilen mit den genauen Aufrufen: «Was ist offen für mich?», «Task erledigt melden (mit Commit)», «Neue ToDos in einen Meilenstein», «Neues Projekt aus Vorlage», «Notiz an einer Task».
   * **Nicht tun:** ganzer Export zur Orientierung; `mode:"full"` mit einem Ausschnitt; Status von Meilenstein/ToDo setzen; Token in Dateien oder Prompts.
   * Tabelle der Werkzeuge (Name, ein Halbsatz, liest/schreibt).
   * Regeln und Grenzen (IDs, Markdown-Whitelist, Limits) in fünf Zeilen.
   * Im HTML `<link rel="alternate" type="text/markdown" href="/mcp/llms.txt">` und oben ein sichtbarer Hinweis «Für Agenten: /mcp/llms.txt».
2. **HTML-Seiten** korrigieren und um einen Abschnitt «Günstig arbeiten» mit denselben Rezepten ergänzen; Einrichtung der Clients (Claude Desktop, Claude Code, JetBrains, Junie) bleibt nur dort. Claude Code: `claude mcp add --transport http …` mit Token aus der Umgebung; vorher aktuelle Doku prüfen.
3. **Nie mehr auseinanderlaufen:** Werkzeugtabelle und Anzahl werden beim Build aus `ToolRegistry` erzeugt (kleiner Artisan-Befehl schreibt JSON, ein Skript setzt es in `llms.txt` und die Seiten ein) **oder** ein Test vergleicht die Namen in den drei Dateien mit der Registry (`frontend/tests/unit/mcp-page.test.ts` erweitern). Bob wählt, Begründung melden.
4. `docs/claude-connector.md` und `AGENTS.md`/`.junie/AGENTS.md` (dort auch die Reste `[cite: 31]` entfernen) auf den neuen Ablauf bringen; für die Agenten Bob, Kevin, Steve, Junie gilt er ab Merge.

## Prüfen

`composer test`, `composer test:mariadb`, `composer analyse`, Frontend-Prüfungen. In der Abschlussmeldung dieselbe Tabelle wie oben, vorher/nachher, plus für das Rezept «Task erledigt melden» die Summe der Tokens heute (list_projects + ganzer Export + dry_run + update) gegen neu.

## Nicht Teil

OAuth für den Connector (Auftrag 41). Ein eigenes Textformat statt JSON (bringt laut Messung wenig, Titel dominieren).
