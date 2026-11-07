# Projektverwaltung (FCA DB)

Web-App zur Projektverwaltung: Milestones → ToDos → Tasks → Kanban, dazu ein Dashboard als Startseite.
Produktiv-Domain später: `project.fcadb.ch` (Frontend und API als getrennte Subdomains).

## Bevor du etwas baust, lies
- `docs/backend-plan.md`: bestätigte Entscheidungen und die **Reihenfolge beim Bauen**. Gilt vor allem anderen.
- `docs/graphql-kontext.md`: alle Entscheidungen im Detail (Rechte, Status-Automatik, Mails, RichText, Icons, Kommentare).
- `backend/graphql/schema.graphql`: das GraphQL-Schema (Lighthouse). Ist der Vertrag zwischen Backend und Frontend.

Wo sich die Dokumente widersprechen: `backend-plan.md` gewinnt. Weicht der Code vom Schema ab, nicht stillschweigend das Schema ändern, sondern nachfragen.

## Stack
- **Backend:** Laravel 13 headless in `backend/`, Lighthouse (GraphQL), Sanctum (SPA-Cookie-Auth), Fortify (Login, Passwort-Reset, 2FA, Passkeys, alles REST, nicht GraphQL)
- **Datenbank:** MariaDB, Laravel-Connection `mariadb` (lokal und produktiv)
- **Frontend (später):** Nuxt (Vue 3) in `frontend/`, Apollo, TipTap (`@tiptap/vue-3`), Font Awesome Pro (Stil fest „Duotone Light“)
- **Hosting:** Hostpoint (Schweiz), nur PHP und MySQL/MariaDB, Queue per Cron. Hosting-Themen erst später, nicht jetzt bearbeiten.
- **Entwicklung:** Windows 11 mit WSL, PhpStorm, GitHub

## Befehle
Alle Befehle in WSL im Ordner `backend/` ausführen:
- `php artisan test` (Tests)
- `php artisan migrate`
- `php artisan lighthouse:validate-schema` (Schema prüfen)
- `./vendor/bin/pint` (Code-Stil)

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
- [ ] 3. Policies
- [ ] 4. Scopes und Builder
- [ ] 5. Status-Observer
- [ ] 6. Schema einbinden, Lighthouse konfigurieren
- [ ] 7. Skalar `RichText`, Regeln
- [ ] 8. Resolver und Mutationen
- [ ] 9. Authentifizierung (Fortify, Passkeys, 2FA-Sperre)
- [ ] 10. Notifications
- [ ] 11. Artisan-Befehl erster Admin
- [ ] 12. Tests
