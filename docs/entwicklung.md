# Entwicklung von Mollo Planer

Dieses Dokument spiegelt die Entwicklung wider. Der Seed für die Datenbank ist die daraus erzeugte Datei `docs/entwicklung.planer.json` (planer-v2): `php artisan db:seed --class=DevelopmentHistorySeeder` legt daraus das Projekt „Mollo Planer · Entwicklung“ an (nur lokal, braucht einen Admin). Dieses Dokument ist der Text, aus dem die Datei einmal mit `tools/planer convert v1-zu-v2` entstand; gepflegt wird künftig die Datei.

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

## v0.9 Automatisches Deployment
Status: veröffentlicht

Automatischer Deploy von main auf den Hostpoint-Server: Tests, Freigabe, Datenbank-Backup, Migrationen, Frontend. Anleitung in der README, Vorlage für weitere Projekte in docs/deploy-template.md.

### Workflow und Server

- [x] [feature] GitHub-Actions-Workflow deploy.yml: Tests, Build, Freigabe im Environment production, Übertragung per SSH
  Commit 793a072
- [x] [feature] deploy/deploy.sh auf dem Server: Backup, Wartungsmodus, Migrationen, Caches, Frontend veröffentlichen
  Commit 793a072
- [x] [feature] Deploy-Schlüssel nur für das Skript: authorized_keys mit command= und restrict
  Commit 793a072
- [x] [feature] Backend-Tests in der CI mit PHP 8.5, wie auf dem Server
  Commit 793a072

### Fehler beim ersten Lauf

- [x] [bug] Lint-Fehler in AppLogo.vue blockierte den Frontend-Test im Deploy
  Commit c4cc932
- [x] [bug] Frontend-Test erwartete role img und currentColor, das Logo auf main war veraltet
  Commit d07161c
- [x] [bug] Platzhalter-Host aus der Anonymisierung ließ den Deploy an der Namensauflösung scheitern; Host und Benutzer jetzt als Repository-Variablen
  Commit d3fcfa3

### Anonymisierung und Doku

- [x] [planning] Server-Angaben im Repo anonymisiert: Benutzer, Host und lokaler Pfad
  Commit 5c7ee81
- [x] [planning] Anleitung zum automatischen Deployment im README
  Commit 793a072
- [x] [planning] Deploy-Vorlage docs/deploy-template.md für weitere Projekte
  Commit 793a072
- [x] [planning] Secrets und Variablen in der Vorlage klar getrennt beschrieben
  Commit 32df2ad

### Abschluss

- [x] [planning] Erster automatischer Deploy erfolgreich, Datenbank-Backup vor den Migrationen
  Commit 0968159


## v1.0 Planer-Datei, KI-Assistent und Zen-Modus
Status: veröffentlicht

Rechte als Daten, Archiv und Papierkorb, Vorlagen und Labels, Planer-Datei mit Abgleich (Export, Import, Kommandozeile, GitHub-Action, Webhook), KI-Assistent und Ideen, Zen-Modus mit Stimmung.

### Fundament: Rechte, Status und Release-Angaben

- [x] [feature] Rechte, Funktionen und Rollen sind einstellbar (role_id statt fester Rollen)
  Commit 8def24b

- [x] [feature] Task-Status „Idee“ und „Geplant“, Werte in Englisch, Labels je Sprache
  Commit 33351c7

- [x] [feature] Release-Version, Release-Status und Commit an Milestones und Tasks
  Commit db53ef2

- [x] [feature] Benutzergeschichte an Tasks
  Commit 2d04b51

- [x] [feature] Revision des Plans zählt nur die App; ein geändertes Projekt wird neu geladen
  Commit 8e2bf87

- [x] [feature] Reihenfolge der ToDos im Milestone und der Tasks im ToDo (verschieben)
  Commit aa4b336

- [x] [bug] Mitglieder: Ersteller, Zuweisung und Löschrecht bleiben stimmig
  Commit 522ea86

- [x] [bug] Meine Tasks blendet Ideen standardmäßig aus
  Commit 271e6ac


### Archiv und Papierkorb

- [x] [feature] Archivierte Projekte sind nur lesbar, archivieren und zurückholen
  Commit 6feb667

- [x] [feature] Löschen kommt in den Papierkorb, als ein Vorgang wiederherstellbar
  Commit 0e8a7db

- [x] [feature] Papierkorb-Seiten und „Rückgängig“ nach dem Löschen
  Commit 37f0933

- [x] [feature] Projektbeschreibung als Rich-Text
  Commit 2e27bdc

- [x] [bug] Rückgängig funktioniert über offenen Panels, Meldung behält ihre Zeit
  Commit 480ff50


### Vorlagen und Labels

- [x] [feature] Vorlagen mit Gerüst und Labels, Labels je Projekt
  Commit 59310b1

- [x] [feature] Vorlagenseite, Label-Editor und Projekt-Labels
  Commit 26a6334

- [x] [bug] Fehlermeldungen der Labels am Feld der Sprache, Einzahl und Mehrzahl
  Commit d344d7b


### Planer-Datei und Abgleich

- [x] [feature] Format planer-v2: Datei mit Milestones, ToDos, Tasks, Mitgliedern und Labels
  Commit f2e9a10

- [x] [feature] Export der Planer-Datei (kanonischer Text) und Prüfung mit Meldungen
  Commit 5867d4a

- [x] [feature] Import mit Probelauf, Rechte je Änderung, ein Papierkorb-Vorgang für Gelöschtes
  Commit 7e60484

- [x] [feature] Claude-Connector mit zwei Werkzeugen: Plan lesen und schreiben
  Commit e0cfa8f

- [x] [feature] Demo-Daten als Planer-Dateien
  Commit ab1ac37

- [x] [feature] Vorlagen aus einem Projekt oder einer hochgeladenen Datei
  Commit 42f881f

- [x] [feature] Planer-Baum mit Markierungen, Datei-Import mit Vorschau und Downloads
  Commit ef802e8

- [x] [bug] Import vergleicht die Reihenfolge nach IDs, Vorschau markiert Geändertes und Gelöschtes
  Commit 33330f7


### Synchronisation

- [x] [feature] Kommandozeile: pull, push, sync und pull --watch gegen die App
  Commit 5b859c3

- [x] [feature] GitHub-Action-Vorlage hält eine Planer-Datei im Gleichschritt mit der App
  Commit f98ddfb

- [x] [feature] Webhook je Projekt, gebündelt und signiert, Queue über den Scheduler
  Commit edddcf1

- [x] [feature] Webhook-Karte in den Projekteinstellungen
  Commit 1f245df

- [x] [bug] Zeitangaben ohne Zeitzone zeigten eine Stunde zu früh (DateTime mit Zone)
  Commit d520f73

- [x] [bug] pull --watch lief nur eine Runde
  Commit 9f7b4c1


### KI-Assistent und Ideen

- [x] [feature] Private Ideen mit Entwurf, Papierkorb, MCP-Werkzeuge
  Commit 503874b

- [x] [feature] KI-Kontext eines Projekts: Text und Datei, Freigabe für externe Werkzeuge
  Commit 5f5ab87

- [x] [feature] Assistent ergänzt bestehende Projekte, Rückfragen mit Knöpfen, Vorschau mit Streichen
  Commit fda0b9c

- [x] [feature] Import mit Vorlage, Idee und „nur ergänzen“
  Commit 61230f7

- [x] [feature] Assistent für alle mit dem Recht, Grenzen je Person (Stunde und Tag)
  Commit 1530c30

- [x] [design] Einstieg „Mit dem Assistenten ergänzen“ im Projekt, „In Ideen ablegen“ mit Titelvorschlag
  Commit 591f44f

- [x] [bug] Mitglieder tragen Entwürfe mit den Rechten je Änderung ein
  Commit c9adff5

- [x] [bug] Assistent wählt keinen Milestone mehr still
  Commit c9adff5

- [x] [bug] Schema der Antwort passte nicht zur Grenze der Claude-API; Test und Befehl assistant:ping
  Commit 142f359


### Zen-Modus

- [x] [feature] Ruhige Ansicht und Fokus für Task und ToDo, Cookie je Gerät
  Commit 03404f6

- [x] [feature] Suche mit Cmd/Ctrl+K: Seiten, Projekte, Aktionen, „Zen“
  Commit feeae53

- [x] [feature] Zeitachse zeigt in Zen den aktuellen Milestone
  Commit aa00978

- [x] [feature] Farbschema Steingarten mit dem Slide-In „Stimmung“
  Commit a276eec

- [x] [feature] Zen über die Suche und den Knopf in der Kopfleiste, zuletzt besuchter Bereich
  Commit 56f3084

- [x] [design] Status, Fokusringe und Gefahr folgen der Stimmung
  Commit 0618c23


### Betrieb und Entwicklung

- [x] [feature] Entwicklungsserver im lokalen Netz erreichbar, API-Adresse aus dem Host abgeleitet
  Commit 7ece663

- [x] [feature] Rechte nur über Rights, eine Liste für Seiten- und Menürechte
  Commit 9673541

## Browser-Tests der Anmeldung (Auftrag 20, 2026-11-18)

Der ganze Weg vom Konto bis zur Arbeitsfläche läuft als Browser-Test (Playwright, Chromium) in `frontend/e2e/`: Registrierung, Bestätigungslink (auch im zweiten Browserprofil), «Konto absichern» mit Passkey und Authenticator-App, Anmelden mit Code, Wiederherstellungscode und Passkey, «Passwort vergessen», Freigabe, Ablehnung, Reset des zweiten Faktors durch den Admin, Drossel, ein Durchlauf auf Englisch. Szenarien: `e2e/auth-flow.spec.ts` (Nummern wie in `next-work/20-anmelde-ui-tests.md`). Je Szenario werden die Zielseite, die sichtbare Meldung, Fehler in der Browser-Konsole und unerwartete 4xx/5xx geprüft; erwartete Antworten (zum Beispiel 422, 429, 403 bei gesperrtem Konto) stehen je Szenario im Test.

**Starten** (im Ordner `frontend/`, Voraussetzung: PHP 8.5, `npm install`, einmal `npx playwright install chromium`, und **Mailpit läuft** auf `localhost:8025` / `1025`):

```bash
npm run test:e2e
```

Der Lauf startet **sein eigenes** Backend (`php artisan serve` auf Port 8100) und seinen eigenen Dev-Server des Frontends (Port 3100, Build-Ordner `.nuxt-e2e`), neben dem normalen Entwicklungsstand. Nichts davon berührt die Entwicklungsdatenbank, einen Testserver oder die Produktion.

- **Datenbank:** eine eigene SQLite-Datei (`/tmp/mollo-planer-e2e.sqlite`), vor jedem Lauf per `migrate:fresh --seed --seeder=E2eSeeder` neu aufgebaut. Der Seeder legt einen Admin (`admin@e2e.test`) und ein Mitglied an, beide mit Authenticator-App. Passwort und Schlüssel stehen in `frontend/e2e/support/environment.ts` (nur für diese Wegwerf-Datenbank), nicht im Seeder. Der Seeder läuft nicht in Produktion.
- **Umgebung des Backends** (`e2e/support/environment.ts`): `QUEUE_CONNECTION=sync` (die Mail ist da, wenn der Test sie liest), `SESSION_DRIVER=database`, `CACHE_STORE=database`, `PASSWORD_UNCOMPROMISED_CHECK=false` (kein Aufruf des Prüfdienstes), `FRONTEND_URL=http://localhost:3100`, `PASSKEYS_RELYING_PARTY_ID=localhost`, `SANCTUM_STATEFUL_DOMAINS=localhost:3100`, `SESSION_DOMAIN=localhost`, Mail an Mailpit. Kein echter SMTP-Zugang.
- **Mails** liest der Test über die API von Mailpit, je Empfänger; jedes Szenario nutzt eigene, eindeutige Adressen.
- **Drosseln:** Alle Tests laufen von `127.0.0.1`; vor jedem Szenario wird der Cache geleert (`php artisan cache:clear`). Die Grenzwerte der App werden **nicht** angehoben; ein eigenes Szenario prüft die Drossel der Registrierung.
- **Passkeys** über den virtuellen Authenticator von Chromium (WebAuthn über CDP, mit Benutzerprüfung).
- **Abgelaufene Links:** der Bestätigungslink wird mit einer Gültigkeit in der Vergangenheit erzeugt (`tinker`), der Reset-Token in `password_reset_tokens` zurückdatiert. Es gibt keine Hilfsroute in der App.
- **Befunde:** Ein Szenario, das eine Abweichung der App zeigt, ist mit `test.fail()` und der Begründung markiert und geht in den Bericht an die Planung. Die Warnungen der Browser-Konsole (Web Awesome und Lit im Entwicklungsmodus) landen nach dem Lauf in `frontend/test-results/console-warnings.txt`, ungefiltert.
- **Nicht im Umfang:** `app:create-admin` und `app:user-2fa` brauchen ein Terminal (Auftrag 18) und sind durch die Backend-Tests abgedeckt; «Passwort anzeigen»; Barrierefreiheit.

