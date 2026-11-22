# Mollo Planer — Junie Guidelines (PhpStorm)

## 1. Stack & MCP-Tools
- **Stack:** Laravel + Lighthouse GraphQL (`backend/graphql/*.graphql`) + Fortify im Backend, **Nuxt** (Vue + TipTap + Font Awesome Pro *Duotone Light*) im Frontend[cite: 7, 8].
- **MCP-Server (`mollo-planer`, `POST /mcp`):** Nutzt das Konto des Token-Inhabers und prüft jedes Recht einzeln[cite: 7, 8].
- **Tools:** `list_projects`, `list_templates`, `list_users`, `export_plan`, `import_plan`, `update_tasks`, `add_comment`, `list_comments`, `create_idea`, `list_ideas`, `get_project_ai_context`[cite: 7].

## 2. Token-sparender Umgang mit den Tools
1. **Mit `list_projects` starten:** Projekt-ID, Rolle und erlaubte Abilities prüfen[cite: 7, 16]. `list_users` oder `list_templates` nur bei Bedarf aufrufen[cite: 7, 17, 18].
2. **Gefiltert exportieren:** Bei großen Plänen `export_plan` möglichst mit `version`, `milestone_ids`, `task_status` oder `include_released: false` nutzen (`excerpt: true` -> beim Schreiben zwingend `mode: "partial"`)[cite: 7, 11].
3. **IDs & Revisionen:** Bestehende IDs (`m-<id>`, `t-<id>`, `k-<id>`) behalten; neue als `m-new-1`, `t-new-1`, `k-new-1` anlegen[cite: 7]. `mode: "full"` (Default) prüft `revision` und verschiebt Fehlendes in den Papierkorb; `mode: "partial"` ergänzt/ändert nur[cite: 7].
4. **Immer `dry_run: true` zuerst:** Sowohl `import_plan` als auch `update_tasks` (mit `base_revision`) erst mit `dry_run: true` testen, Fehler/Änderungen prüfen und erst dann scharf ausführen[cite: 7]. `include_document` bei `import_plan` nur bei Bedarf aktivieren.
5. **Kommentare, Ideen & AI-Kontext schlank halten:** `list_comments` mit kleinem `limit`/`max_chars` nutzen; `list_ideas`, `create_idea` und `get_project_ai_context` nur bei direktem Bezug aufrufen[cite: 12, 14, 15].

## 3. Verbindliche Domänen- & Code-Regeln
- **Status-Ableitung:** `Todo` und `Milestone` leiten ihren Status automatisch aus Tasks ab – nie manuell setzen[cite: 7]. Task-Status (`idea`, `planned`, `open`, `in_progress`, `review`, `done`) über `update_tasks` pflegen[cite: 7, 11].
- **GraphQL & Enums:** `camelCase` in GraphQL, `snake_case` in DB (`@rename`); Enums mit `App\Enums` synchron halten (`tests/Unit/GraphqlEnumBindingTest.php`)[cite: 7].
- **Sichtbarkeit & Rechte:** Lesen über `visibleToViewer` (`null` / `[]`), Schreiben über Policies/`@can` (`"This action is unauthorized."`)[cite: 7]. Niemals nur im Nuxt-Frontend prüfen[cite: 7, 8].
- **RichText & XSS:** Whitelisted TipTap-JSON / Markdown-Whitelist (kein HTML, keine Bilder/Tabellen/Codeblöcke; max. 20.000 Zeichen)[cite: 6, 7]. Im Nuxt-Frontend **nie `v-html`** verwenden[cite: 7].
- **Icons & Limits:** Font-Awesome-Pro-Namen ohne Stil-Präfix (Stil *Duotone Light* fest im Frontend)[cite: 7, 8]. Limits beachten (200 Milestones/Projekt, 50 ToDos/Milestone, 50 Tasks/ToDo, 50 Mitglieder, 30 Labels, Beschreibungen bis 5.000 Zeichen)[cite: 7].
- **Sicherheit:** Keine Klartext-Tokens oder Secrets ausgeben/loggen; 2FA-/Passkey-Middleware in Produktion nicht lockern[cite: 3, 7, 8].

## 4. Arbeitsweise in PhpStorm
- Nur zielgerichtet ändern, was für den Auftrag nötig ist; relevante Tests/Checks ausführen und Ergebnisse knapp zusammenfassen.