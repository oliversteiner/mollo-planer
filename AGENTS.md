# Mollo Planer

Web-App zur Projektverwaltung: Milestones → ToDos → Tasks → Kanban, dazu ein Dashboard als Startseite.
Zwei Umgebungen, TEST und PROD; Frontend und API laufen als getrennte Subdomains. Die Adressen und Angaben zum Server stehen nicht im Repo, sondern in `~/www/agents/AGENTS.md` und `~/www/agents/mollo-planer/betrieb.md`.

Allgemeine Regeln für alle Projekte: `~/www/agents/AGENTS.md`. Zuerst lesen, falls sie nicht schon geladen ist.

**Planung:** im Mollo Planer auf TEST, Projekt **2 · Mollo Planer**. Agent dieses Repos ist **Bob** (eigenes Konto, Token `MOLLOPLANER_BOB_TOKEN`, eingetragen in `.mcp.json`; die Adresse des Servers kommt aus `MOLLOPLANER_MCP_URL`). Aufträge liegen lokal in `next-work/` (nicht im Git), die Reihenfolge in `next-work/README.md`; jede Auftragsdatei nennt ihr ToDo, der Stand steht im Planer. Ablauf: Abschnitt «Zusammenarbeit: next-work und Planer» in `~/www/agents/AGENTS.md`. TEST enthält echte Daten: kein Dump, kein `migrate:fresh`, keine Seeder auf TEST.

## Bevor du etwas baust, lies
- `docs/README.md`: Einstieg, **Vorrang der Dokumente** und welche historisch sind. Zuerst lesen.
- `docs/umsetzung.md`: Arbeitsschritte, Reihenfolge, Meilensteine, Stand. **Was dort steht, wird gebaut.**
- `docs/planer-abgleich.md`, `docs/rollen-funktionen-rechte.md`, `docs/assistent-ideen-konzept.md`: Entscheidungen (Planer v2, Rechte, Assistent).
- `spec/format-v2.md`, `spec/planer-v2.schema.json`: das Format Planer v2.
- `backend/graphql/schema.graphql`: das GraphQL-Schema (Lighthouse). Ist der Vertrag zwischen Backend und Frontend.
- `docs/frontend-webawesome-plan.md`: Oberfläche (Web Awesome Pro). Für alles an der Oberfläche gilt es vor `frontend-plan.md`.

Wo sich Dokumente widersprechen, gilt die Reihenfolge in `docs/README.md` (zuerst `umsetzung.md`). `backend-plan.md` und `graphql-kontext.md` sind historisch. Weicht der Code vom Schema oder von den Dokumenten ab, nicht stillschweigend ändern, sondern nachfragen.

## Stack
- **Backend:** Laravel 13 headless in `backend/` mit PHP 8.5 (lokal, CI, Test- und Produktionsserver; `composer.json` verlangt `^8.5`), Lighthouse (GraphQL), Sanctum (SPA-Cookie-Auth), Fortify (Login, Passwort-Reset, 2FA, Passkeys, alles REST, nicht GraphQL)
- **KI-Assistent:** offizielles PHP-SDK `anthropic-ai/sdk` (Claude-API), Schlüssel `ANTHROPIC_API_KEY` nur in `.env`
- **Bilder:** Bild-API von Laravel (`intervention/image`, Treiber GD) für Avatarbilder
- **Datenbank:** MariaDB, Laravel-Connection `mariadb` (lokal und produktiv)
- **Frontend:** Nuxt 4 (Vue 3) mit **Web Awesome Pro** (Web Components `wa-*`, Theme „Matter“; nicht Nuxt UI, kein Tailwind) in `frontend/`, reine SPA (`nuxt generate`), npm, Apollo, TipTap (`@tiptap/vue-3`), Font Awesome Pro (Stil fest „Duotone Light“, auch für Bedienelemente, über `wa-icon`; die Pro-SVGs liegen **nicht** im Repo, sondern in einem privaten Repo und kommen mit `npm run icons:fetch` nach `frontend/vendor/`; ohne sie nutzt der Build Font Awesome Free)
- **Hosting:** Webhoster mit SSH, nur PHP und MySQL/MariaDB, Queue per Cron (Angaben in `~/www/agents/mollo-planer/betrieb.md`). Hosting-Themen erst später, nicht jetzt bearbeiten.
- **Entwicklung:** macOS (Mac mini, Apple Silicon) mit Homebrew (PHP, Composer, MariaDB, Mailpit), Node 24 über nvm, PhpStorm, GitHub

## Befehle
Alle Befehle im Terminal im Ordner `backend/` ausführen:
- `php artisan test` (Tests)
- `composer test:mariadb` (dieselben Tests gegen MariaDB, Datenbank `molloplaner_test`; vor Commits, die Abfragen, Sperren oder Migrationen ändern)
- `php artisan migrate`
- `php artisan lighthouse:validate-schema` (Schema prüfen)
- `php artisan migrate:fresh --seed --seeder=DemoSeeder` (Demo-Daten wie in der GraphQL-Demo, nur lokal; **löscht alle Daten**. In eine leere Datenbank: `php artisan db:seed --class=DemoSeeder`)
- `./vendor/bin/pint` (Code-Stil)
- `composer schema:frontend` (nach jeder Schemaänderung: schreibt `frontend/graphql/schema.graphql`, danach im Frontend `npm run codegen`)
- `php artisan boost:update --no-discover` (Boost-Guidelines und -Skills neu erzeugen, läuft auch nach `composer update`)

Werkzeug für `planer-v2` im Ordner `tools/planer/` (Node 20 oder neuer, keine Abhängigkeiten):
- `npm test` (Tests, zusammen mit der Prüfung vor jedem Commit)
- `node cli/bin/mollo-planer.mjs validate <datei>` (Prüfung einer Planer-Datei, Formatregeln in `spec/format-v2.md`)

Frontend im Ordner `frontend/` (Node 24 aus nvm, siehe `frontend/.nvmrc`):
- `npm run dev` (http://localhost:3000, im WLAN erreichbar; die Backend-Adresse steht in `frontend/.env` als `NUXT_PUBLIC_API_BASE`)
- `npm run lint`, `npm run typecheck`, `npm test` (Vitest: `tests/unit` ohne Nuxt, `tests/nuxt` für Komponenten und Middleware)
- `npm run icons:check` (keine Pro-Dateien) und `npm run terms:check` (keine Domains, Adressen, Anbieter oder Angaben zum Server der eigenen Installation; die Liste kommt aus der Umgebungsvariable `PRIVATE_TERMS`, sie steht nicht im Repo; ohne sie nur eine Warnung). Beide laufen auch in der CI und gehören vor jeden Push.
- `npm run codegen` (Typen aus `graphql/schema.graphql` und den `graphql(...)`-Abfragen)
- `npm run build` (statische Dateien in `.output/public`)

## Laravel Boost
- Boost liefert `backend/CLAUDE.md` (allgemeine Laravel-Regeln), Skills in `backend/.claude/skills/` und den MCP-Server `laravel-boost` (`.mcp.json` im Projektordner: Doku-Suche `search-docs`, Datenbankschema, Logs).
- `backend/CLAUDE.md` und die Skills werden von Boost erzeugt, **nicht von Hand ändern**. Auswahl in `backend/boost.json`, Ausschlüsse in `backend/config/boost.php` (Laravel Cloud und Tailwind sind ausgeschlossen).
- Bei Widersprüchen gilt diese Datei und `docs/README.md` (mit `docs/umsetzung.md`) vor den Boost-Regeln. Insbesondere: API nur über GraphQL (Lighthouse), keine Eloquent API Resources oder REST-Versionierung. Den Stand in `docs/umsetzung.md` nachführen, auch wenn Boost sagt, Dokumentation nur auf Anfrage.

## Regeln
- **Code** (Klassen, Methoden, Variablen, Kommentare im Code) auf Englisch. **UI-Texte und Mails** in Deutsch und Englisch über `lang/de` und `lang/en`, nie hart codiert.
- GraphQL in camelCase, Datenbank in snake_case (`@rename`).
- **Rechte nur über `App\Support\Rights`.** Der Code fragt nach einem Recht (Enum `App\Enums\Right`): `Rights::allows($user, Right::X, $project)`. Welche Rolle ein Recht hat, steht in den Tabellen `roles`, `functions`, `function_rights`, `role_functions` (Standard in `config/permissions.php`, Details in `docs/rollen-funktionen-rechte.md`). Keine Rollenprüfung (`role === 'owner'`, `role === 'admin'`) irgendwo sonst, kein `is_admin`.
- Lesen filtert der Scope `visibleToViewer`, Schreiben prüft eine Policy per `@can`.
- Status von ToDo und Milestone wird nur vom Observer berechnet, nie per Eingabe gesetzt.
- RichText wird als TipTap-JSON gespeichert, **nie HTML**. Frontend zeigt es mit TipTap an, **nie mit `v-html`**.
- Mails nur über Notifications mit `ShouldQueue` und `afterCommit`, Sprache = `project->locale`.
- Keine Passwörter, Tokens oder `.env`-Inhalte ins Repository. **Das Repo ist öffentlich: keine Pro-Dateien committen** (Font Awesome Pro, `frontend/vendor/`, die Skills von Web Awesome Pro in `frontend/.claude/skills/webawesome*`; ein Wächter in CI und Test schlägt an). Font Awesome braucht kein npm-Token mehr, die Symbole holt `npm run icons:fetch` aus dem privaten Repo `mollo-pro-assets` (Secret `PRO_ASSETS_TOKEN`); das Web-Awesome-Token kommt aus der Umgebungsvariable `WEBAWESOME_NPM_TOKEN` (GitHub-Secret `WEBAWESOME_NPM_AUTH_TOKEN`).
- Oberfläche nur mit `wa-*`-Elementen von Web Awesome und den eigenen Bausteinen (`FormInput`, `AppDialog`, `ActionMenu` …), Layout mit den Hilfsklassen `wa-stack`/`wa-cluster`/… und `<style scoped>` mit Tokens (`--wa-*`). Kein Nuxt UI, kein Tailwind.
- Enums im GraphQL-Schema mit `App\Enums` synchron halten (`tests/Unit/GraphqlEnumBindingTest.php`).
- Rechte nie nur im Frontend prüfen.
- RichText höchstens 20.000 Zeichen.
- Icons: Font-Awesome-Pro-Namen ohne Stil-Präfix.
- Die 2FA- und Passkey-Middleware in Produktion nicht lockern.
- Änderungen nur über einen eigenen Branch und einen PR nach `dev`, nie direkt auf `dev`, nie mergen ohne Oliver (gilt für alle Agenten, auch Junie).
- **MCP-Konfigurationen** (`.mcp.json`, `.junie/mcp/mcp.json` und gleichartige Dateien im Repo) enthalten nur Server ohne Geheimnis (`laravel-boost`) und sonst höchstens `${VARIABLE}`. **Nie ein Token und nie eine Adresse mit Zugangsdaten** in diese Dateien. Der Planer-Server steht nur in der `.mcp.json` des Repos, mit `${MOLLOPLANER_<NAME>_TOKEN}` für das eigene Agentenkonto und `${MOLLOPLANER_MCP_URL}` für die Adresse (global gesetzt, zeigt auf TEST), und verbindet nur mit TEST. Planer-Server nie mit `--scope user` eintragen, weil sie sonst in jedem Projekt mit demselben Konto auftauchen. Olivers persönliche Server nur mit `--scope local`.
- «cpm»: Vor dem Merge Branch und Ziel-Branch nennen, wenn unklar. Commits von Junie mit Co-Author-Trailer.
- Zu jeder neuen Policy, Mutation oder Regel gehören Tests (siehe Punkt 12 im Backend-Plan).
- Nach einem abgeschlossenen Schritt aus der Bau-Reihenfolge den Abschnitt „Stand“ unten aktualisieren.

## Stand
- [x] 1. Migrationen
- [x] 2. Enum `ProjectAbility`, `ProjectPermissions`
- [x] 3. Policies
- [x] 4. Scopes und Builder
- [x] 5. Status-Observer
- [x] 6. Schema einbinden, Lighthouse konfigurieren
- [x] 7. Skalar `RichText`, Regeln
- [x] 8. Resolver und Mutationen
- [x] 9. Authentifizierung (Fortify, Passkeys, 2FA-Sperre)
- [x] 10. Notifications
- [x] 11. Artisan-Befehl erster Admin
- [x] 12. Tests

## Stand Frontend
- [x] 1. Einrichtung (npm, Template aufräumen, SPA, i18n, Layout, CI)
- [x] 2. Font Awesome
- [x] 3. API-Anbindung (Apollo, Codegen)
- [x] 4. Anmeldung
- [x] 5. Dashboard und Projektliste
- [x] 6. Projekt: Milestones und ToDos
- [x] 7. RichText und Icon-Picker
- [x] 8. Kanban und Task-Details
- [x] 9. Meine Tasks, Profil
- [x] 10. Sicherheit im Profil (2FA, Passkeys)
- [x] 11. Projekteinstellungen und Mitglieder
- [x] 12. Admin
- [x] 13. Tests und Feinschliff
