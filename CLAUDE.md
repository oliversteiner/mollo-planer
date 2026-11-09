# Projektverwaltung (FCA DB)

Web-App zur Projektverwaltung: Milestones → ToDos → Tasks → Kanban, dazu ein Dashboard als Startseite.
Produktiv-Domain später: `project.fcadb.ch` (Frontend und API als getrennte Subdomains).

## Bevor du etwas baust, lies
- `docs/backend-plan.md`: bestätigte Entscheidungen und die **Reihenfolge beim Bauen**. Gilt vor allem anderen.
- `docs/graphql-kontext.md`: alle Entscheidungen im Detail (Rechte, Status-Automatik, Mails, RichText, Icons, Kommentare).
- `backend/graphql/schema.graphql`: das GraphQL-Schema (Lighthouse). Ist der Vertrag zwischen Backend und Frontend.
- `docs/frontend-plan.md`: Entscheidungen und Bau-Reihenfolge für das Frontend.

Wo sich die Dokumente widersprechen: `backend-plan.md` gewinnt. Weicht der Code vom Schema ab, nicht stillschweigend das Schema ändern, sondern nachfragen.

## Stack
- **Backend:** Laravel 13 headless in `backend/`, Lighthouse (GraphQL), Sanctum (SPA-Cookie-Auth), Fortify (Login, Passwort-Reset, 2FA, Passkeys, alles REST, nicht GraphQL)
- **KI-Assistent:** offizielles PHP-SDK `anthropic-ai/sdk` (Claude-API), Schlüssel `ANTHROPIC_API_KEY` nur in `.env`
- **Bilder:** Bild-API von Laravel (`intervention/image`, Treiber GD) für Avatarbilder
- **Datenbank:** MariaDB, Laravel-Connection `mariadb` (lokal und produktiv)
- **Frontend:** Nuxt 4 (Vue 3) mit Nuxt UI in `frontend/`, reine SPA (`nuxt generate`), npm, Apollo, TipTap (`@tiptap/vue-3`), Font Awesome Pro (Stil fest „Duotone Light“, auch für Bedienelemente)
- **Hosting:** Hostpoint (Schweiz), nur PHP und MySQL/MariaDB, Queue per Cron. Hosting-Themen erst später, nicht jetzt bearbeiten.
- **Entwicklung:** macOS (Mac mini, Apple Silicon) mit Homebrew (PHP, Composer, MariaDB, Mailpit), Node 24 über nvm, PhpStorm, GitHub

## Befehle
Alle Befehle im Terminal im Ordner `backend/` ausführen:
- `php artisan test` (Tests)
- `composer test:mariadb` (dieselben Tests gegen MariaDB, Datenbank `projektverwaltung_test`; vor Commits, die Abfragen, Sperren oder Migrationen ändern)
- `php artisan migrate`
- `php artisan lighthouse:validate-schema` (Schema prüfen)
- `php artisan migrate:fresh --seed --seeder=DemoSeeder` (Demo-Daten wie in der GraphQL-Demo, nur lokal; **löscht alle Daten**. In eine leere Datenbank: `php artisan db:seed --class=DemoSeeder`)
- `./vendor/bin/pint` (Code-Stil)
- `composer schema:frontend` (nach jeder Schemaänderung: schreibt `frontend/graphql/schema.graphql`, danach im Frontend `npm run codegen`)
- `php artisan boost:update --no-discover` (Boost-Guidelines und -Skills neu erzeugen, läuft auch nach `composer update`)

Frontend im Ordner `frontend/` (Node 24 aus nvm, siehe `frontend/.nvmrc`):
- `npm run dev` (http://localhost:3000)
- `npm run lint`, `npm run typecheck`, `npm test` (Vitest: `tests/unit` ohne Nuxt, `tests/nuxt` für Komponenten und Middleware)
- `npm run codegen` (Typen aus `graphql/schema.graphql` und den `graphql(...)`-Abfragen)
- `npm run build` (statische Dateien in `.output/public`)

## Laravel Boost
- Boost liefert `backend/CLAUDE.md` (allgemeine Laravel-Regeln), Skills in `backend/.claude/skills/` und den MCP-Server `laravel-boost` (`.mcp.json` im Projektordner: Doku-Suche `search-docs`, Datenbankschema, Logs).
- `backend/CLAUDE.md` und die Skills werden von Boost erzeugt, **nicht von Hand ändern**. Auswahl in `backend/boost.json`, Ausschlüsse in `backend/config/boost.php` (Laravel Cloud und Tailwind sind ausgeschlossen).
- Bei Widersprüchen gilt diese Datei und `docs/backend-plan.md` vor den Boost-Regeln. Insbesondere: API nur über GraphQL (Lighthouse), keine Eloquent API Resources oder REST-Versionierung. Den Abschnitt „Stand“ und `docs/backend-plan.md` nachführen, auch wenn Boost sagt, Dokumentation nur auf Anfrage.

## Regeln
- **Code** (Klassen, Methoden, Variablen, Kommentare im Code) auf Englisch. **UI-Texte und Mails** in Deutsch und Englisch über `lang/de` und `lang/en`, nie hart codiert.
- GraphQL in camelCase, Datenbank in snake_case (`@rename`).
- **Rechte nur über `App\Support\ProjectPermissions`.** Policies fragen nur diese Tabelle. Keine Rollenprüfung (`role === 'owner'`) irgendwo sonst.
- Lesen filtert der Scope `visibleToViewer`, Schreiben prüft eine Policy per `@can`.
- Status von ToDo und Milestone wird nur vom Observer berechnet, nie per Eingabe gesetzt.
- RichText wird als TipTap-JSON gespeichert, **nie HTML**. Frontend zeigt es mit TipTap an, **nie mit `v-html`**.
- Mails nur über Notifications mit `ShouldQueue` und `afterCommit`, Sprache = `project->locale`.
- Keine Passwörter, Tokens oder `.env`-Inhalte ins Repository. Das Font-Awesome-npm-Token gehört in `.npmrc` (lokal, nicht eingecheckt) bzw. in ein GitHub-Secret.
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
