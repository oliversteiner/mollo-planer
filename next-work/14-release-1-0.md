# 14 · Release 1.0 vorbereiten

Status: erledigt (d5e96e7 R1, e8dca05 R2, e234eab R3, e8851d8 R4); Entwurf-PR: https://github.com/oliversteiner/mollo-planer/pull/16

Plan: `docs/release-1.0.md`. Erst nach Zen-Abnahme und Ticket 13 mergen; vorbereiten kannst du jetzt.
Keine Secrets, keine echten Server-Angaben ins Repo. Nicht deployen, nicht mergen, nicht taggen – das macht Oliver.

## R1 Versionsangaben 1.0

- Milestone „v1.0 …“ (Titel nach Format in `docs/entwicklung.md`) in `docs/entwicklung.planer.json` mit den Arbeitspaketen M1–M6 und Zen als ToDos und den wichtigsten Tasks (feature/design/bug) mit Commit. Status veröffentlicht.
- `npm run versions` → `frontend/app/data/versions.json`. Datum bleibt null bis zum Tag `v1.0.0`.
- Abnahme: Versionsseite zeigt 1.0 mit Release-Notizen „Neu / Verbessert / Behoben“.

## R2 `.env.example` und Doku

- `.env.example`: alle neuen Schlüssel vorhanden und kommentiert (Assistent-Limits, Queue, CORS mit Hinweis „nur Entwicklung, in Produktion leer“, Sanctum).
- `docs/deploy-template.md` und README: neue Server-Einstellungen, Cron-Eintrag, `assistant:ping`, Hinweis „kein migrate:rollback in Produktion“.
- `NUXT_PUBLIC_API_BASE` als Build-Variable in der Deploy-Doku bestätigen (leer = nur Entwicklung).

## R3 Prüfung der Migrationen ohne Produktionsdaten

Kein Probelauf mit echten Daten (Entscheidung Oliver). Ersatz:
- Lokale Datenbank auf Stand `d3e50c7` (0.9) aufsetzen, mit 0.9-Demo-Daten seeden, dann auf `dev` wechseln und `migrate` + `planer:sync-templates` laufen lassen.
- Danach: jedes Projekt exportieren, Rollen der Nutzer stimmen, Labels → Vorlagen übernommen, Beschreibungen als Rich-Text lesbar.
- Ergebnis kurz in `docs/release-1.0.md` unter „Prüfung der Migrationen“ nachtragen (Merge, nicht überschreiben).

## R4 Pull Request vorbereiten

- Branch-Stand prüfen: alle Tests grün (Backend, Frontend, Lint, Schema-Budget).
- PR `dev` → `main` als **Entwurf** anlegen: Titel „Release 1.0“, Beschreibung = Release-Notizen + Tabelle „Brüche gegenüber 0.9“ aus dem Plan.
- Postman-Sammlung und `docs/claude-connector.md` auf aktuellem Stand (neue MCP-Werkzeuge).

Fertig, wenn R1–R4 erledigt und committet sind. Melde den PR-Link.
