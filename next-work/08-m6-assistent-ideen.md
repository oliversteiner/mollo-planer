# Auftrag: M6 Assistent und Ideen (B5a, B6)

Status: erledigt (503874b, 5f5ab87, 61230f7, 1530c30, fda0b9c, 8f6c28e); Abnahme durch die Planung
Stand: 2026-11-18, `dev` bis `9f7b4c1`.
Dokument: `docs/auftrag-m6.md` (maßgeblich), Grundlage `docs/assistent-ideen-konzept.md`.

1. B6a Ideen: Tabelle `ideas`, `todos.idea_id`, Rechte, GraphQL, Papierkorb, MCP `create_idea`/`list_ideas` (§2).
2. B6b KI-Kontext: `project_ai_contexts`, 20 000 Zeichen, Upload `.txt`/`.md`, Freigabe, MCP `get_project_ai_context` (§3).
3. B5a Assistent: `importPlan` mit `templateId` (nur neues Projekt), `ideaId`, `appendOnly`; `draftPlan` mit `choices` und Vorschau; Grenze 30/Stunde, 200/Tag; altes `ProjectPlan*` entfernen; MCP `list_users` nach Konzept §8 (§4).
4. Oberflächen: Assistent für `assistant.use` mit Knöpfen und Streichen, Seite `/ideas`, Karte KI-Kontext (§4.7).

Abschluss: Prüfungen grün, im Browser angesehen, `docs/umsetzung.md` „Stand“ nachgeführt, oben `Status: erledigt (<Commits>)`, anhalten.
