# Entwicklung von Mollo Planer

Dieses Dokument spiegelt die Entwicklung wider. Es ist zugleich der Seed für die Datenbank: `php artisan db:seed --class=DevelopmentHistorySeeder` legt daraus das Projekt „Mollo Planer · Entwicklung“ an (nur lokal).

## Aufbau

- **Milestone** (`## …`): eine Version. Der Titel beginnt mit der Versionsnummer.
- **ToDo** (`### …`): der Arbeitskontext, in dem gearbeitet wurde.
- **Task** (`- [x] [art] Titel`): ein Feature, Fehler, eine Planung, Recherche oder ein Design. `[x]` erledigt, `[ ]` offen. Die Art ist `feature`, `bug`, `planning`, `research`, `design` oder `blocked` (blockiert, Label „Blockiert“).
- Zeilen darunter, eingerückt um zwei Leerzeichen, sind die Beschreibung des Tasks. Der Commit steht in der Form `Commit abc1234`.
- Eine Zeile unter der Überschrift eines Milestones oder ToDos ohne Aufzählungszeichen ist deren Beschreibung.

Der Status von Milestones und ToDos wird nicht geschrieben. Er entsteht aus den Tasks.


## v0.1 Backend-Grundlage
Status: veröffentlicht

Laravel-Backend: Datenmodell, Rechte, GraphQL-API, Anmeldung, Demo-Daten und Betrieb.

### Datenmodell und Rechte

- [x] [feature] Add Laravel backend, docs and migrations (step 1)
  Commit 8854922

- [x] [feature] Add ProjectAbility enum and ProjectPermissions table (step 2)
  Commit 19dbe1a

- [x] [feature] Add policies for projects, milestones, todos, tasks, comments and users (step 3)
  Commit b9339a3

- [x] [feature] Add visibility scopes, todo builder and status observers (steps 4 and 5)
  Commit e9f108f


### GraphQL-API

- [x] [feature] Configure Lighthouse, bind enums and lock API until strong auth (step 6)
  Commit 753c063

- [x] [feature] Add RichText scalar, rich text and icon rules (step 7)
  Commit d14e7ef

- [x] [feature] Add GraphQL resolvers and mutations (step 8)
  Commit ac2ec02

- [x] [feature] Batch-load GraphQL lists to avoid N+1 queries
  Commit ece5cf6

- [x] [feature] Run custom mutations in transactions and lock projects for changes
  Commit 2623394

- [x] [feature] Add user avatars: upload, crop to 500x500 WebP, remove
  Commit 6b0e35b


### Anmeldung und Benachrichtigungen

- [x] [feature] Add authentication: strong sessions, passkeys, invitations (step 9)
  Commit 214c632

- [x] [feature] Add task notifications for assignments and comments (step 10)
  Commit edbfde3

- [x] [feature] Add app:create-admin command for the first admin (step 11)
  Commit 13d2075

- [x] [feature] Add Laravel Boost guidelines, skills and MCP server
  Commit 101396f

- [x] [feature] Complete step 12: outsider access, mail escaping, MariaDB test run
  Commit 5b7a003


### Demo-Daten und Postman

- [x] [planning] Add demo data seeder and README with seeding instructions
  Commit 8440945

- [x] [feature] Empty DatabaseSeeder so migrate --seed creates no known-password user
  Commit 40c7c2e

- [x] [feature] Add Postman collection (v3 YAML) for the demo data
  Commit 78c48bb

- [x] [feature] Ignore Postman environments
  Commit 9b5156e

- [x] [feature] Let admins switch two-factor authentication off outside production
  Commit 30ac0d3

- [x] [feature] Seed demo data with two-factor authentication switched off
  Commit f6b734a


### Betrieb

- [x] [feature] db init
  Commit 1768ff3

- [x] [feature] Switch development setup from WSL to macOS and add backend CI
  Commit 4268c4f


## v0.2 Frontend-Grundlage
Status: veröffentlicht

Nuxt-Frontend: Einrichtung, Anbindung, Anmeldung, Dashboard, Projekte, Kanban, Profil, Verwaltung.

### Einrichtung und Plan

- [x] [planning] Add confirmed frontend plan
  Commit 4c6bb50

- [x] [feature] Set up the frontend: npm, SPA, i18n, layout, CI (frontend step 1)
  Commit ad1b4d4

- [x] [feature] Use Font Awesome Pro Duotone Light for all icons (frontend step 2)
  Commit 6b21828


### Anbindung und Anmeldung

- [x] [feature] Connect the frontend to the API (frontend step 3)
  Commit ef5d9fa

- [x] [feature] Add login, password reset and invitation (frontend step 4)
  Commit 08afd39

- [x] [feature] Retry once with a fresh CSRF cookie after 419
  Commit 3258d43

- [x] [feature] Let the login work for sessions that are already logged in
  Commit 1599744


### Dashboard, Projekte und Kanban

- [x] [feature] Add dashboard and project list (frontend step 5)
  Commit 46c6d57

- [x] [feature] Add project overview and todo list with editing (frontend step 6)
  Commit c4a3a53

- [x] [feature] Add rich text editor and icon picker (frontend step 7)
  Commit 445c93c

- [x] [feature] Add Kanban board and task details (frontend step 8)
  Commit ab7d6bd

- [x] [feature] Treat null as "no filter" for optional list filters
  Commit da58889

- [x] [feature] Leave out null filters in todo list and my tasks
  Commit 7ac9967

- [x] [feature] Edit Kanban tasks by double-click, one overlay at a time
  Commit 68c4c38

- [x] [feature] Empty the comment field after sending a comment
  Commit a2997da


### Profil, Sicherheit und Verwaltung

- [x] [feature] Add my tasks and profile (frontend step 9)
  Commit 58c4e6c

- [x] [feature] Add two-factor and passkey management (frontend step 10)
  Commit 54bc2a8

- [x] [feature] Add project settings and members (frontend step 11)
  Commit e5b411c

- [x] [feature] Add admin area: users and app settings (frontend step 12)
  Commit 65ae595

- [x] [feature] Move language and appearance settings to the profile
  Commit ee72c32

- [x] [feature] Add component and middleware tests, polish (frontend step 13)
  Commit 0004bb6


## v0.3 Oberfläche mit Web Awesome

Umstellung der Oberfläche auf Web Awesome Pro (Theme Matter), Entfernen von Nuxt UI und Tailwind.

### Bausteine

- [x] [feature] Add Web Awesome Pro next to Nuxt UI (phase A, step 1)
  Commit b1893bf

- [x] [feature] Use the fonts of the Web Awesome theme Matter instead of Public Sans
  Commit 93b7e24

- [x] [feature] Load all Web Awesome icons as Duotone Light from our own server
  Commit 624d8a9

- [x] [feature] Replace the Nuxt UI toast by Web Awesome toasts with the same call
  Commit 336cf92

- [x] [feature] Replace UTimeline with a vertical Web Awesome stepper
  Commit 86ec2b9

- [x] [feature] Add form field wrappers for Web Awesome with v-model and server messages
  Commit ac94a2b

- [x] [feature] Add dialog, empty state and person components for Web Awesome
  Commit dc7ebfe


### Seiten umgestellt

- [x] [feature] Move the layouts to wa-page with own navigation
  Commit 317f55b

- [x] [feature] Move login, password pages and the security setup to Web Awesome
  Commit c1afcde

- [x] [feature] Move the dashboard and the project list to Web Awesome
  Commit 8ddbb13

- [x] [feature] Move the project overview and the milestone dialog to Web Awesome
  Commit e08a8a6

- [x] [feature] Move Kanban, todo list, task details and comments to Web Awesome
  Commit 49ad39b

- [x] [bug] Fix the status filter binding of the todo list
  Commit c66fcae

- [x] [feature] Move my tasks and the profile to Web Awesome
  Commit 102e641

- [x] [feature] Move project settings, members and the admin pages to Web Awesome
  Commit 39bd8cd

- [x] [feature] Move the AI assistant to Web Awesome
  Commit bca6902

- [x] [feature] Move the error page to Web Awesome
  Commit b261b3e


### Rich-Text und Farbmodus

- [x] [feature] Replace the Nuxt UI editor and icon picker with TipTap and Web Awesome
  Commit 706837e

- [x] [feature] Use an own light/dark setting instead of the Nuxt UI colour mode
  Commit 417704d

- [x] [planning] Update the Web Awesome plan with the notes of step 5
  Commit b96e45e


### Nuxt UI entfernt und Feinschliff

- [x] [feature] Remove Nuxt UI and Tailwind
  Commit cdf7f66

- [x] [feature] Name every form field for screen readers and drop unused texts
  Commit 9fe3917

- [x] [planning] Update the docs for the Web Awesome frontend
  Commit 057e4b8

- [x] [design] Put the chat introduction right above the input field
  Commit ecee113

- [x] [design] Centre the chat introduction in the free space
  Commit ebd9b7d

- [x] [design] Fit the assistant page into the screen without scrolling
  Commit bc99f22

- [x] [design] Mark the status of a task card by its left edge
  Commit 5563879

- [x] [design] Match status colours, widen timeline rows, enlarge card headings
  Commit ef03cf4

- [x] [bug] Fix findings of the check in the browser
  Commit 945fe0d


### Abschluss der Umstellung

- [ ] [planning] Sichtprüfung jeder Seite in hell und dunkel, de und en
- [ ] [planning] Tastatur, Fokus und Kontrast im Theme Matter prüfen

## v0.4 Projektansicht, Timeline und KI-Assistent
Status: veröffentlicht

Zeitachse, Kanban-Verbesserungen, Projektnavigation in der Seitenleiste, KI-Assistent und MCP-Zugang.

### Timeline und Kanban

- [x] [feature] Add a timeline view to the project overview
  Commit 3fa7f8c

- [x] [feature] Show the project overview as timeline only, with editable tasks
  Commit db61f17

- [x] [feature] Move tasks to another todo by drag and drop in the timeline
  Commit ca83336


### Projektnavigation

- [x] [feature] Move project pages into the sidebar
  Commit c605f72

- [x] [feature] List the projects in the sidebar and let the sidebar stand out
  Commit 87d8306

- [x] [feature] Add page header with breadcrumb and tabs for the project pages
  Commit ba1fd96

- [x] [feature] Show the logo in the sidebar and on the login pages
  Commit 3ec4030

- [x] [feature] Give projects an icon
  Commit 9ad6769


### KI-Assistent und MCP

- [x] [feature] Add an AI assistant page that drafts projects with Claude
  Commit cf603a6

- [x] [feature] Remove the example prompts from the assistant chat
  Commit 515b86b

- [x] [feature] Let Claude fill projects through an MCP server
  Commit fcdb942


## v0.5 Labels
Status: veröffentlicht

Labels für Tasks: Label-Sets, Anzeige, Auswahl, Verwaltungsseite und Filter.

### Label-Sets im Backend

- [x] [planning] Add the plan for task labels (label sets)
  Commit 20407b4

- [x] [feature] Add label sets and task labels to the backend
  Commit 89f4c00


### Labels im Frontend

- [x] [feature] Show and set task labels, choose the label set of a project
  Commit be0890c

- [x] [feature] Add the label sets page with create, duplicate, edit and delete
  Commit 6504e42

- [x] [feature] Filter the Kanban by label and seed demo labels
  Commit 1274af7


## v0.6 Rollen und Rechte

Globale Rollen (admin, projektleitung, koordination, mitglied, lesend) und Funktionen, Rechte-Klasse, Umbau.

### Pläne

- [x] [planning] Add the plan for user roles and permissions
  Commit 60501d1

- [x] [planning] Add the status and open decisions to the roles plan
  Commit 8e7a0b9

- [x] [bug] Fix the role name in the migration test note
  Commit b375136


### Umsetzung

- [x] [feature] Replace the admin flag with global roles and abilities
  Commit 407cdb0


### Dokumentation und Umgebung

- [x] [feature] Ignore direnv .envrc files in the frontend
  Commit 0c36bec

- [x] [planning] Document how to create the first admin in the README
  Commit 4e6d331


### Abschluss

- [ ] [planning] Prüfung im Browser je Rolle (admin, projektleitung, mitglied)

## v0.7 System-Bereich und API
Status: veröffentlicht

Bereich System in der Seitenleiste, Status, Datenbank-Doku, Rechte-Tabelle und GraphQL unter /api.

### Systemseiten

- [x] [feature] Statusendpunkt GET /status mit Tests
  Commit 780a462
- [x] [feature] Seitenleiste: Bereich System mit Einträgen Datenbank und Status
  Commit 780a462
- [x] [feature] Statusseite /system/status für Backend, Datenbank und GraphQL
  Commit 780a462
- [x] [feature] Datenbank-Doku /system/db mit Test gegen die Migrationen
  Commit 780a462
- [x] [feature] Rechte-Tabelle /system/rechte nur für Admins, gelesen aus den Rechte-Klassen
  Commit 780a462
- [x] [feature] GraphQL-Endpunkt von /graphql nach /api umgestellt
  Commit 780a462
- [x] [feature] Versionen (API, App) und Zugriff auf der Statusseite wie in fcadb
  Commit 17744b7
- [x] [bug] Statusseite zeigte „nicht erreichbar“: /status fehlte in der CORS-Liste
  Commit 17744b7
- [x] [planning] Prüfung im Browser als Admin und als Mitglied
  Commit 17744b7
- [x] [planning] Commit auf feature/system
  Commit 17744b7
- [x] [planning] Merge von feature/system nach dev
  Commit d17115c

## v0.8 Desktop-App (Tauri)

Desktop-App für macOS und Windows mit Anmeldung per Token und Auto-Update. Plan: docs/desktop-plan.md.

### Plan

- [x] [planning] Plan für die Desktop-App mit den Entscheidungen festgehalten
  Commit 780a462
- [x] [planning] GitHub-Milestone „Desktop-App (Tauri)“ angelegt
  Commit 780a462

### Umsetzung

- [ ] [research] Spike: Tauri-Hülle lädt die lokale Build, Token-Anmeldung gegen die lokale API
- [ ] [feature] Anmeldung per Token im Schlüsselbund
- [ ] [feature] Bundle für macOS und Windows, unsigniert
- [ ] [feature] Signierung und Notarisierung (macOS), Code-Signing (Windows)
- [ ] [feature] Auto-Update mit signierten Updates
- [ ] [planning] Release-Prozess über GitHub Actions für macOS und Windows
