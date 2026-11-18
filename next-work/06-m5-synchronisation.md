# Auftrag: M5 Synchronisation (T2, T3, W1)

Status: offen
Stand: 2026-11-18, `dev` bis `a93fb95`. M3 und M4 sind abgenommen.
Dokument: `docs/auftrag-m5.md` (maßgeblich).

1. T2: `pull`, `push`, `sync`, `pull --watch` in `tools/planer/cli` (§2), mit Integrationstest gegen den echten MCP-Server der App.
2. T3: Vorlage `tools/planer/templates/github-action/planer-export.yml` mit README (§3). `mollo-planer` ist öffentlich, Checkout ohne Token.
3. W1: Webhook je Projekt, Art „GitHub“ (`repository_dispatch` mit Token) und „Allgemein“ (HMAC), gebündelt höchstens alle 5 Minuten, Oberfläche in den Projekteinstellungen (§4). Dazu Scheduler-Eintrag für `queue:work` und Cron-Abschnitt in `docs/deploy-template.md`.
4. Erledigte Dateien `01` bis `05` hier nicht anfassen; die Planung räumt sie weg.

Abschluss: Prüfungen grün, `docs/umsetzung.md` „Stand“ nachgeführt, oben `Status: erledigt (<Commits>)`, anhalten.
