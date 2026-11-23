# 35 · PR 30 (Werkzeug-Schicht) mit `dev` zusammenführen: dev-Funktionen in `App\Tools` übernehmen

Status: offen
Priorität: HOCH (Oliver: PR 30 hat Merge-Konflikte auf GitHub). Branch: `refactor/werkzeug-schicht` (PR nach `dev`, Oliver merget). Entscheid (Oliver): **Refactor behalten, dev-Funktionen portieren.** Die Schicht `App\Tools` aus Auftrag 34 bleibt, die Funktionen aus `dev` werden dort neu umgesetzt.

## Befund

- PR 30 (ein Commit `2a6f9ef`) verschiebt die MCP-Tools nach `App\Tools` (`Tool`, `ToolResult`, `Schema`, `ToolRegistry`, Adapter `App\Mcp\ToolAdapter` und `App\Assistant\ToolBridge`). Die Tools laufen über GraphQL (`GraphQLRunner`) als die angemeldete Person.
- `dev` hat seit dem Abzweig dieselben Tools in `App\Mcp\Tools\*Tool` erweitert (Aufträge 30–33, Import-Warnungen). Beim Merge von `origin/dev` entstehen **21 Konflikte**: `MolloPlanerServer.php`, 12 Tool-Dateien (gelöscht im PR, geändert in `dev`), `RunsGraphQL.php` und `ToolRegistry`-nahe Support-Dateien, 4 Feature-Tests (`tests/Feature/Mcp/*`, `AiContext`), `docs/claude-connector.md`, `docs/umsetzung.md`.
- Ein Merge, der nur Konflikte auflöst, ist falsch: Entweder gehen die dev-Funktionen verloren oder die Schicht des PRs wird aufgegeben.

## Ziel

PR 30 ist ohne Konflikte mit `dev` mergbar. Jede Funktion, die `dev` in den MCP-Tools ergänzt hat, steht danach in der Schicht `App\Tools` (und damit auch im Assistenten), mit denselben Tests wie in `dev`. Es gibt **keine** zweite Kopie in `App\Mcp\Tools`.

## Vorgehen (Reihenfolge)

0. Vorbereitung
   - `git fetch origin`, Branch `refactor/werkzeug-schicht` aktualisieren, `git merge origin/dev`. Bei den 21 Konflikten gilt: Dateien unter `app/Mcp/Tools/` bleiben **gelöscht** (Schicht des PRs); den Inhalt aus `dev` vorher mit `git diff $(git merge-base HEAD origin/dev) origin/dev -- <datei>` lesen und in Schritt 1–4 übertragen.
   - Nicht konfliktbehaftete dev-Änderungen (Dokumente, Agent-Konten, Statistik, Importer) unverändert übernehmen.
1. Gemeinsame Bausteine
   - `App\Mcp\Support\McpCursor` (Cursor-Paginierung) und `McpText` (Textkürzung, Beschreibungs-Chunks) aus `dev` übernehmen. Ort: neben der Schicht (z. B. `App\Tools\Support`), damit MCP und Assistent sie teilen. Namespace anpassen, Tests mitnehmen.
   - `RunsGraphQL` aus `dev` mit `GraphQLRunner`/`Tool::graphql()` abgleichen. Neue Hilfen aus `dev` nach `Tool` oder `Support` verlegen, nicht zurück in `App\Mcp`.
2. Tools mit grosser Abweichung zuerst
   - `ListIdeas`: Sichtbarkeit nach Recht, Filter (Status, Projekt, Idee), Cursor, Standard 15 Ideen und 400 Zeichen Beschreibung je Eintrag, Beschreibungs-Chunks, Zeitstempel (Verhalten genau wie `ListIdeasTool` in `dev`).
   - `ListComments`, `AddComment`, `CreateIdea`: Änderungen aus `dev` (Kürzung, Cursor, Zugriffsregeln) übernehmen.
   - Zugriffsregeln, die `dev` direkt per Eloquent prüft, laufen in der Schicht über GraphQL (`visibleToViewer`, Policies) oder über `Rights::allows`. **Keine** Rollenprüfung, kein Sonderweg. Wo GraphQL ein Feld nicht liefert, `Schema` (`schema.graphql`) erweitern und `composer schema:frontend` ausführen; Schema-Änderungen vorher bei Oliver nachfragen.
3. Übrige Tools
   - `ExportPlan` (Milestone-IDs normalisiert, kompakte Ausgabe, Filter `version`, `milestone_ids`, `task_status`, `include_released`, `excerpt: true`).
   - `ImportPlan` (Warnungen für unbekannte Zuweisungen mit Pfad, E-Mail, Hinweis; optionale Dokumente; Modus `full` lehnt Ausschnitt ab).
   - `UpdateTasks` (Status, Commit, Zuweisung, `base_revision`, `dry_run`, alles oder nichts).
   - `GetProjectAiContext` (sparsame Antwort, Filter, Seiten), `ListProjects` (kürzere Beschreibung), `ListTemplates`, `ListUsers`.
   - **Neu in `dev`:** `get_project_statistics` (`GetProjectStatisticsTool`) als Klasse `App\Tools\GetProjectStatistics` anlegen, in `ToolRegistry` eintragen, nutzt die Abfrage `projectStatistics`; schreibgeschützt (`readOnly()`).
4. Server und Adapter
   - `MolloPlanerServer`: Tool-Liste kommt aus `ToolRegistry`, dev-Anpassungen (Beschreibungstexte, Anleitung) in den Server übernehmen. `routes/ai.php` prüfen.
5. Tests
   - Die dev-Tests (`tests/Feature/Mcp/*`, `AiContext/AiContextTest.php`, `Planer/ImportPlanApiTest.php`, `Assistant/DraftPlanTest.php`) nach der Schicht umstellen. Behauptungen von `dev` bleiben **unverändert**; nur der Zugang ändert sich. Keine Tests löschen, abschwächen oder auskommentieren.
   - Neue Tests für `ToolRegistry` und für beide Adapter (MCP, Assistent), falls der PR sie noch nicht hat.
6. Dokumentation
   - `docs/claude-connector.md` und `docs/umsetzung.md`: beide Stände zusammenführen (Aufträge 30–33 aus `dev` **und** Auftrag 34 aus dem PR); Abschnitt «Stand» nachführen.

## Abschluss

- `git merge origin/dev` ist abgeschlossen, GitHub zeigt PR 30 ohne Konflikte.
- `php artisan test`, `composer test:mariadb`, `./vendor/bin/pint`, `php artisan lighthouse:validate-schema` sind grün; bei Schema-Änderungen auch `composer schema:frontend` und im Frontend `npm run codegen`, `npm run typecheck`.
- Bei jedem Tool vergleichen: Antwort in `App\Tools` ist inhaltlich dieselbe wie `ListIdeasTool` & Co. in `dev` (Stichprobe je Tool mit gleichen Eingaben).
- Status oben auf `erledigt (<Commit>)` setzen, `docs/umsetzung.md` ergänzen. Ein Commit je Schritt 1–6. Nicht mergen, Stuart liest den PR.

## Offene Punkte

- Wo GraphQL die dev-Funktion (z. B. Cursor oder Chunks) nicht hergibt: erst bei Oliver nachfragen, ob das Schema erweitert oder die Funktion in einem Dienst (`App\Services`) umgesetzt wird. Nicht stillschweigend an den Rechten vorbei lesen.
- Alternative bei zu vielen Konflikten: Branch neu aufbauen (`dev` als Basis, Commit `2a6f9ef` per Cherry-pick, Konflikte Tool für Tool). Gleiches Ziel, gleiche Abschlusskriterien.
