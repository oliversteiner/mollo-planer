# Release 1.0 – Plan

Stand 2026-11-18 · Stuart. Bringt M1–M6 und den Zen-Modus von `dev` nach `main` und damit auf den Hostpoint-Server.

## Entscheidungen (Oliver, 2026-11-18)

- **Version 1.0** (heute veröffentlicht: 0.9, Tag `v0.9.0`).
- **Kein Probelauf** auf einer Kopie der Produktions-Datenbank. Der Rückweg ist die Sicherung, die `deploy.sh` vor den Migrationen anlegt, plus eine zusätzliche Sicherung von Hand direkt vor dem Deploy (siehe Schritt 3).
- **Nutzer:** nur Oliver. Keine Ankündigung nötig. Oliver richtet seinen Claude-Connector nach dem Deploy neu ein.

## Ausgangslage

- `origin/main` = `d3e50c7` (2026-11-12, PR #15). `dev` ist 229 Commits voraus.
- 19 neue Migrationen (`2026_11_12_100000` bis `2026_11_18_120000`). Sie bauen bestehende Daten um: role_id statt Rollen-Text (unbekannte Rollen werden zu „mitglied“/„member“), label_sets → templates, Projektbeschreibung → Rich-Text, Spalten für Archiv und Papierkorb, Webhooks, Ideen, KI-Kontext.
- Einige `down()`-Migrationen verlieren Daten (z. B. label_sets: `label_task` wird gelöscht). **`migrate:rollback` ist kein Rückweg.** Zurück geht es nur mit Datenbank-Sicherung + vorherigem Release.
- `deploy.yml` deployt von `main`: Tests → Build → Freigabe im Environment „production“ → `deploy.sh` (Sicherung, `down`, composer, `migrate --force`, `planer:sync-templates`, Caches, Frontend, `up`).

## Bedingungen vor dem Release

1. Zen ist abgenommen (Oliver prüft selbst; danach `next-work/13-zen-farben.md`, ZG1–ZG3).
2. `next-work/14-release-1-0.md` ist erledigt (siehe unten).
3. Alle Tests grün auf `dev` (Backend, Frontend, Lint, Schema-Budget-Test des Assistenten).

## Brüche gegenüber 0.9

| Bereich | Änderung | Folge |
|---|---|---|
| Claude-Connector (MCP) | neue Werkzeuge, alte entfallen | Connector in Claude neu verbinden, Token neu erzeugen |
| GraphQL | Felder und Mutationen umbenannt/entfernt (Rollen, Labels → Vorlagen, Planer-Import) | Postman-Sammlung ist auf `dev` angepasst; alte Skripte gegen die API prüfen |
| Rollen | feste Rollen-Keys über role_id | Mitglieder-Rollen nach dem Deploy kurz ansehen |
| Datum | `DateTime` jetzt ISO 8601 mit Zeitzone | nur für eigene Skripte relevant |

## Neue Einstellungen auf dem Server (`.env`, nie ins Repo)

- `ANTHROPIC_API_KEY` und die Limits des Assistenten (30 Entwürfe/Stunde, 200/Tag; Namen wie in `.env.example`). Ohne Schlüssel bleibt der Assistent aus; der Rest läuft.
- `QUEUE_CONNECTION=database`.
- `CORS_ALLOWED_ORIGINS` leer lassen (nur für die Entwicklung im LAN).
- `SANCTUM_STATEFUL_DOMAINS` = Domain des Frontends, ohne LAN-IP.
- Mail-Versand wie bisher (`MAIL_MAILER` nicht `log`).
- Build-Variable `NUXT_PUBLIC_API_BASE` im GitHub-Repository (Variable, kein Secret) gesetzt lassen. In der Entwicklung ist sie leer, in Produktion muss sie die API-Adresse enthalten.

## Ablauf am Release-Tag

1. **Stand prüfen:** `dev` grün, Zen abgenommen, Release-Notizen 1.0 in `docs/entwicklung.planer.json` und `frontend/app/data/versions.json` (Bob, Ticket 14).
2. **Server-`.env` ergänzen** (Oliver, per SSH): Einträge oben. `php artisan config:clear` ist nicht nötig, `deploy.sh` baut die Caches neu.
3. **Zusätzliche Sicherung** (Oliver, per SSH): `mysqldump` wie in `deploy.sh`, Datei mit Namen `vor-1.0-<datum>.sql.gz` außerhalb des Rotations-Ordners ablegen (deploy.sh behält nur 10). Dazu den Ordner des laufenden Release-Builds merken.
4. **Pull Request `dev` → `main`** (Bob bereitet vor, Oliver merged). Titel „Release 1.0“, Beschreibung = Release-Notizen + Brüche.
5. **Deploy freigeben** im Environment „production“. Log verfolgen; bei Fehler in `migrate` bleibt die Seite im Wartungsmodus → Rückweg.
6. **Tag `v1.0.0`** auf den Merge-Commit setzen und pushen (das Release-Datum in der Versionsseite kommt aus dem Tag; danach `npm run versions` auf `dev` und committen).
7. **Cron-Eintrag** im Hostpoint-Panel, jede Minute (Vorlage in `docs/deploy-template.md`):
   `* * * * * cd <api-pfad> && <php-pfad> artisan schedule:run >> /dev/null 2>&1`
8. **Nachkontrolle** (Stuart im Browser, Oliver per SSH): siehe unten.

## Nachkontrolle nach dem Deploy

- Anmelden, Dashboard, Meine Aufgaben, ein Projekt in Kanban/Timeline öffnen.
- Rollen der Mitglieder und Projekt-Rollen stimmen (role_id-Migration).
- Vorlagen sind da (`planer:sync-templates`): Neues Projekt aus Vorlage im Probelauf (dryRun) importieren.
- Jedes bestehende Projekt als Planer-Datei exportieren lässt sich (prüft die umgebauten Daten).
- Papierkorb und Archiv öffnen ohne Fehler.
- Assistent: `php artisan assistant:ping` auf dem Server, dann ein Entwurf im Browser.
- Queue: eine Benachrichtigungs-Mail auslösen, nach spätestens zwei Minuten angekommen (zeigt, dass der Cron läuft). `jobs`-Tabelle leer, `failed_jobs` leer.
- Zen: über die Suche „Zen“ und über den Button, Stimmung speichern.
- Versionsseite zeigt 1.0 mit Datum.
- Claude-Connector neu verbinden, `list_projects` funktioniert.
- Danach die noch offenen Punkte aus M5: GitHub-Webhook/Action-Schleife einmal echt durchspielen; Test-Token und Test-Webhooks danach löschen.

## Rückweg

Wenn eine Migration scheitert oder die Nachkontrolle einen schweren Fehler zeigt:

1. Seite bleibt bzw. geht in den Wartungsmodus (`php artisan down`).
2. Datenbank aus der Sicherung von Schritt 3 (oder der von `deploy.sh`) einspielen.
3. Vorherigen Release-Ordner wieder veröffentlichen (Backend-Code und Frontend von 0.9) bzw. `main` auf `d3e50c7` zurück und den 0.9-Stand erneut deployen.
4. `php artisan up`, Fehler in `storage/logs/laravel.log` sichern, Fix auf `dev`, neuer Anlauf.

Kein `migrate:rollback` in Produktion (Datenverlust, siehe oben).

## Nach 1.0 (nicht Teil des Releases)

- M6 ⏸: Limits im Betrieb beobachten, MCP-Werkzeuge für Ideen/KI-Kontext, heller Modus/Englisch/Telefonbreite.
- Zen ⏸: Telefonbreite, Englisch, Tastatur.
- Dokumentenverwaltung (`docs/dokumentenverwaltung.md`).
- Aufräumen: erledigte `next-work`-Dateien archivieren, alte `.git`-Lock-Reste entfernen.
