# Mollo Planer: Kontext für Claude Desktop

Stand: 2026-11-13 (neu gefasst in D1). Dieses Dokument in ein Claude-Desktop-Projekt unter „Kontext“ einfügen. Es fasst das Projekt zusammen und verweist auf die maßgeblichen Dokumente im Repository. **Einstieg und Vorrang der Dokumente: `docs/README.md`.**

---

## Was das Projekt ist

Mollo Planer ist eine Web-App zur Projektverwaltung:

- **Milestones** enthalten **ToDos**, ToDos enthalten **Tasks** (Reihenfolge je Elternteil).
- Tasks erscheinen im **Kanban** und in der **Zeitachse**. Task-Status: `idea`, `planned`, `open`, `in_progress`, `review`, `done` (`idea`/`planned` in Umsetzung, M1).
- Status von ToDo und Milestone werden berechnet (Observer), nie eingegeben.
- Milestones können eine **Version** tragen (`release`: Nummer `M.m`, `planned`/`released`), Tasks einen **Commit** und eine **User-Story**.
- Startseite: Dashboard. Oberfläche Deutsch und Englisch, Standard Deutsch. Produktiv: `planer.mollo.dev`, API `api.planer.mollo.dev`.

**Planer v2:** Ein Plan lässt sich als Datei `<projekt>.planer.json` (Format `planer-v2`) exportieren und importieren. Das Werkzeug `mollo-planer` (Node, ohne Abhängigkeiten) liegt in `tools/planer/`, Format und Schema in `spec/`. Die App-Datenbank ist der Live-Speicher, die Datei ein versionierter Export (Revision zählt die App). Details: `docs/planer-abgleich.md`, `spec/format-v2.md`.

## Stack

- **Backend** (`backend/`): Laravel 13 headless, PHP 8.5, Lighthouse (GraphQL, einzige API außer Fortify-REST und `POST /mcp`), Sanctum, Fortify (Login, Reset, 2FA, Passkeys, Einladung, Selbstregistrierung mit Freigabe), `anthropic-ai/sdk` (KI-Assistent), `laravel/mcp` (Connector), `intervention/image`, MariaDB (Tests auch SQLite).
- **Frontend** (`frontend/`): Nuxt 4 als SPA, Web Awesome Pro (`wa-*`, Theme „Matter“), Font Awesome Pro „Duotone Light“, Apollo, GraphQL Code Generator, TipTap 3, `vue-draggable-plus`, Vitest, npm.
- **Werkzeug** (`tools/planer/`): Node ≥ 20, `npm test`.
- **Hosting:** Hostpoint (nur PHP und MariaDB, Queue per Cron, kein Node auf dem Server).

## Befehle

- `backend/`: `php artisan test`, `composer test:mariadb`, `php artisan migrate`, `php artisan lighthouse:validate-schema`, `./vendor/bin/pint`, `composer schema:frontend`, `php artisan permissions:sync-defaults`, lokal `php artisan migrate:fresh --seed --seeder=DemoSeeder` (löscht Daten).
- `frontend/`: `npm run dev`, `npm run lint`, `npm run typecheck`, `npm test`, `npm run codegen`, `npm run build`.
- `tools/planer/`: `npm test`; CLI `node cli/bin/mollo-planer.mjs validate|json|render|convert|template|add|move|status|list|show …`.

## Regeln (verbindlich)

- Code, Kommentare, Commit-Texte **englisch**. Oberfläche und Mails DE/EN über `lang/` bzw. `i18n/locales/`, nie hart codiert. In deutschen Texten „ToDo“.
- **Technische Werte klein und englisch** (`de`/`en`, `planned`/`released`, `none`, Rollenschlüssel `owner`/`manager`/`member`/`guest`).
- GraphQL camelCase, Datenbank snake_case.
- **Rechte:** Der Code fragt nur `App\Support\Rights` nach **Rechten** (z. B. `task.update`). Rollen und Funktionen sind Daten und in den Einstellungen definierbar (Recht → Funktion → Rolle, je eine Systemrolle pro Person und eine Projektrolle pro Mitgliedschaft). Grundregeln (Aussperren verhindern, letzter Owner, fremde Kommentare, eigene Tasks, Archiv nur lesbar, 2FA) bleiben im Code. Details: `docs/rollen-funktionen-rechte.md`. Frontend fragt Rechte ab (`viewerRights` bzw. heute noch `viewerAbilities`), nie Rollennamen.
- **Rich Text:** gespeichert als TipTap-JSON, nie HTML, Anzeige nie mit `v-html`. Whitelist in `RichTextDocument.php` und `utils/rich-text.ts` gleich halten. In der Planer-Datei Markdown nach derselben Whitelist (kanonische Form, `spec/format-v2.md` §6.1).
- **Mails** nur über Notifications mit `ShouldQueue` und `afterCommit`, Sprache = `project.locale`.
- **Oberfläche** nur `wa-*` und eigene Bausteine, Layout über `wa-stack`/`wa-cluster`/`wa-grid`, Tokens `--wa-*`. Kein Nuxt UI, kein Tailwind.
- **Tests** zu jeder Policy, Mutation und Regel; `OutsiderAccessTest`/`MutationTest` werden über `MutationCoverageTest` auf Vollständigkeit geprüft.
- **Geheimnisse** nie ins Repository.
- **Breaking Changes** sind erlaubt (nur Demo- und Testdaten). Bei Widersprüchen zwischen Code und Dokumenten: anhalten und fragen.

## Arbeitsweise

- **Planung** (Claude-Projekt „Mollo Planer“): analysiert und plant, ändert keinen Code, legt Plandokumente in `docs/` ab.
- **Umsetzung** (eigene Sitzung): arbeitet `docs/umsetzung.md` ab, ein Schritt ein Commit, Tests grün, Stand nachführen, nach jedem Meilenstein Abnahme durch Oliver.

## Meilensteine (Stand 2026-11-13)

M1 Fundament (Rechte einstellbar, Status `idea`/`planned`, Release/Commit, Neuladen über Revision) → M2 Archiv und Papierkorb → M3 Vorlagen und Labels je Projekt → M4 Datei-Abgleich (Export, Import, neuer Connector) → M5 Synchronisation (`pull`/`push`, GitHub Action, Webhook) → M6 KI-Assistent für alle, Ideen, KI-Kontext. Aktueller Stand: `docs/umsetzung.md`.

## Anmeldung und Sicherheit (unverändert)

- 2FA ist Pflicht (TOTP oder Passkey), in Produktion nicht abschaltbar.
- Selbstregistrierung mit E-Mail-Bestätigung und Freigabe durch eine berechtigte Person; zusätzlich Einladung durch berechtigte Personen.
- GraphQL erst nach starker Anmeldung, höchstens 120 Anfragen pro Minute, Introspection nur in Produktion aus.
- MCP-Zugang über persönliche Tokens (90 Tage), handelt mit den Rechten der Person.
