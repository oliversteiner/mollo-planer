# Mollo Planer: Kontext für Claude Desktop

Dieses Dokument in ein Claude-Desktop-Projekt unter „Kontext“ einfügen. Es fasst das Projekt zusammen. Die genauen Entscheidungen stehen im Repository in `docs/` (Vorrang: `docs/backend-plan.md`, dann `docs/graphql-kontext.md`). Das Schema `backend/graphql/schema.graphql` ist der Vertrag zwischen Backend und Frontend.

Stand: 2026-11-12.

---

## Was das Projekt ist

Mollo Planer ist eine Web-App zur Projektverwaltung. Die Struktur:

- **Milestones** (Meilensteine) enthalten **ToDos**.
- **ToDos** enthalten **Tasks**.
- Tasks erscheinen im **Kanban-Board** und in einer **Zeitachse** (Milestones, darin ToDos zum Aufklappen, darin Tasks).
- Startseite ist ein **Dashboard** mit anstehenden ToDos, offenen Tasks der Person und Projekten mit Fortschritt.

Die Oberfläche ist Deutsch und Englisch. Standard ist Deutsch. Die Produktiv-Domain ist `planer.mollo.dev`, die API unter `api.planer.mollo.dev`.

Geplant, noch nicht gebaut: eine Desktop-App mit Tauri für macOS und Windows (`docs/desktop-plan.md`). Sie zeigt dieselbe Oberfläche, die Daten bleiben auf dem Backend.

---

## Stack

**Backend** (`backend/`):
- Laravel 13 headless, PHP 8.5
- Lighthouse (GraphQL), die einzige API. Keine Eloquent API Resources, keine REST-Versionierung.
- Sanctum (SPA-Cookie-Auth)
- Fortify für Login, Passwort-Reset, 2FA, Passkeys und Einladungen. Das ist REST, nicht GraphQL.
- `anthropic-ai/sdk` für den KI-Assistenten (Modell `claude-opus-5-5`, Schlüssel nur in `.env`)
- `laravel/mcp` für den MCP-Server „Mollo Planer“ unter `POST /mcp`
- `intervention/image` (GD) für Avatarbilder
- MariaDB (lokal und produktiv), Tests auch gegen SQLite

**Frontend** (`frontend/`):
- Nuxt 4 (Vue 3) als reine SPA (`ssr: false`, `nuxt generate`)
- **Web Awesome Pro** (Web Components `wa-*`, Theme „Matter“). Nicht Nuxt UI, nicht Tailwind.
- Font Awesome Pro, fester Stil **„Duotone Light“** für alle Icons
- Apollo Client 3.14, GraphQL Code Generator, TipTap 3 für Rich Text, `vue-draggable-plus` fürs Kanban
- npm (kein pnpm), Vitest für Tests

**Hosting:** Hostpoint (Schweiz), nur PHP und MariaDB, Queue per Cron. Hosting-Themen sind **nicht** Teil der aktuellen Arbeit.

**Entwicklung:** macOS (Apple Silicon) mit Homebrew (PHP, Composer, MariaDB, Mailpit), Node 24 über nvm, PhpStorm, GitHub.

---

## Befehle

Im Ordner `backend/`:
- `php artisan test`, dazu `composer test:mariadb` vor Commits, die Abfragen, Sperren oder Migrationen ändern
- `php artisan migrate`
- `php artisan lighthouse:validate-schema`
- `php artisan migrate:fresh --seed --seeder=DemoSeeder` (**löscht alle Daten**, nur lokal)
- `./vendor/bin/pint` (Code-Stil)
- `composer schema:frontend` nach jeder Schemaänderung, danach im Frontend `npm run codegen`

Im Ordner `frontend/`:
- `npm run dev` (http://localhost:3000)
- `npm run lint`, `npm run typecheck`, `npm test`, `npm run codegen`, `npm run build`

---

## Regeln (verbindlich)

**Code und Begriffe**
- Code (Klassen, Methoden, Variablen, Kommentare im Code) auf **Englisch**.
- UI-Texte und Mails auf Deutsch und Englisch über `lang/de`, `lang/en` (Backend) bzw. `i18n/locales/de.json`, `en.json` (Frontend). **Nie hart codiert.**
- GraphQL in camelCase, Datenbank in snake_case (`@rename`).
- In deutschen Texten heißt es überall „ToDo“, auch im Plural.

**Rechte: nur über zwei Tabellen**
- `App\Support\ProjectPermissions` für Projektrollen (Owner, Manager, Mitglied).
- `App\Support\SystemPermissions` für globale Rollen (`admin`, `projektleitung`, `koordination`, `mitglied`, `lesend`).
- Policies fragen nur diese Tabellen. Keine Prüfung wie `role === 'owner'` oder `is_admin` irgendwo sonst.
- Lesen filtert der Scope `visibleToViewer`. Schreiben prüft eine Policy per `@can`.
- Das Frontend fragt Fähigkeiten ab (`Project.viewerAbilities`, `viewerSystemAbilities`), nie einen Rollennamen. Das Backend prüft jede Änderung ohnehin.

**Status**
- Status von ToDo und Milestone berechnet **nur der Observer**, nie eine Eingabe.

**Rich Text**
- Gespeichert und übertragen wird **TipTap-JSON, nie HTML**.
- Das Frontend zeigt es mit TipTap an. **Nie `v-html`** (eine ESLint-Regel verbietet es).
- Die Whitelist (`App\Rules\RichTextRule` und `utils/rich-text.ts`) muss übereinstimmen.

**Mails**
- Nur über Notifications mit `ShouldQueue` und `afterCommit`. Sprache = `project->locale`.

**Oberfläche**
- Nur `wa-*`-Elemente von Web Awesome und eigene Bausteine (`FormInput`, `AppDialog`, `ActionMenu` …).
- Layout über die Hilfsklassen `wa-stack`, `wa-cluster`, `wa-grid` und so weiter, Stile in `<style scoped>` mit Tokens (`--wa-*`).
- Kein Nuxt UI, kein Tailwind.

**Tests**
- Zu jeder neuen Policy, Mutation oder Regel gehören Tests. Die Listen `OutsiderAccessTest` und `MutationTest` werden bei neuen Mutationen ergänzt.

**Geheimnisse**
- Keine Passwörter, Tokens oder `.env`-Inhalte ins Repository. Font-Awesome-Token in `frontend/.npmrc` (lokal) bzw. GitHub-Secret, Web-Awesome-Token aus `WEBAWESOME_NPM_TOKEN`.

**Schema**
- Das Schema ändert sich nur nach Rückfrage. Weicht der Code vom Schema ab, nicht stillschweigend das Schema ändern, sondern nachfragen.

**Dokumentation**
- Nach einem abgeschlossenen Schritt den Abschnitt „Stand“ im passenden Plan nachführen.

---

## Rollen und Rechte

**Globale Rollen** (`users.role`): `admin`, `projektleitung`, `koordination`, `mitglied`, `lesend`. Dazu optional `job_function` (Designer, Entwicklung, Tester, Inhalte, Technik, Helfende). Die Funktion dient nur der Anzeige.

- `admin` darf alles, außer fremde Kommentare ändern. Er kann sich nicht selbst löschen und sich die Admin-Rolle nicht selbst entziehen.
- `projektleitung` und `koordination` dürfen Projekte anlegen. `projektleitung` darf Label-Sets verwalten. Nur Admins verwalten Benutzer.
- `lesend` darf nur ansehen, auch als Owner eines Projekts (geplant laut `rollen-plan.md`, vor dem Bau zu prüfen).

**Projektrollen:** Owner, Manager, Mitglied.

| Fähigkeit | Owner | Manager | Mitglied |
|---|---|---|---|
| Projekt ändern (auch Sprache, Label-Set) | ja | ja | nein |
| Projekt löschen | ja | nein | nein |
| Mitglieder verwalten | ja | ja | nein |
| Milestone anlegen / ändern | ja | ja | ja |
| Milestone löschen | ja | ja | nein |
| ToDo anlegen / ändern | ja | ja | ja |
| ToDo löschen | ja | ja | nein |
| Task anlegen / ändern / zuweisen | ja | ja | nein |
| Task verschieben | ja | ja | ja |
| Task löschen | ja | ja | nein |
| Kommentieren | ja | ja | ja |
| Fremde Kommentare löschen | ja | ja | nein |

Weitere Regeln:
- Eigene Kommentare darf die Autorin oder der Autor ändern und löschen, solange die Person das Projekt sieht. Fremde Kommentare ändert niemand.
- Der letzte Owner eines Projekts kann nicht entfernt oder herabgestuft werden, außer durch einen Admin.
- Owner-Rolle vergeben oder entziehen dürfen nur Owner und Admins.
- Wer ein Projekt anlegt und kein Admin ist, wird dessen Owner.
- Wird ein Mitglied entfernt, werden seine Tasks im Projekt unzugewiesen.
- Tasks lassen sich nur Projektmitgliedern zuweisen.

---

## Anmeldung und Sicherheit

- Benutzer legt ein Admin an (`createUser`), es geht eine Einladungsmail mit Link (gültig 7 Tage, einmal verwendbar). Der erste Admin entsteht über `php artisan app:create-admin`. Selbstregistrierung mit Passwort ist geplant: E-Mail-Bestätigung und Freigabe durch einen Admin (`docs/registrierung-plan.md`).
- Login per E-Mail und Passwort (Fortify, REST).
- **2FA ist Pflicht.** Ein Konto braucht einen zweiten Faktor (TOTP-App) oder einen Passkey.
- Zwei gleichwertige Wege: **Passkey** (ein Schritt, Benutzerprüfung verlangt) oder **Passwort plus 2FA-Code**.
- Eine Middleware sperrt die GraphQL-Route, bis die Sitzung „stark“ angemeldet ist (Merkmal `App\Support\StrongAuthentication`). Antworten: `SECOND_FACTOR_SETUP_REQUIRED` bzw. `STRONG_AUTHENTICATION_REQUIRED`.
- Ein Admin kann die 2FA **nur außerhalb der Produktion** abschalten (`updateAppSettings(twoFactorEnabled: false)`). In Produktion ist sie immer Pflicht.
- `resetUserTwoFactor` (Admin) entfernt TOTP und alle Passkeys.
- Keine REST-Route `/api/user`. CORS nur für `FRONTEND_URL`. GraphQL höchstens 120 Anfragen pro Minute und Person. Introspection ist nur in Produktion aus.
- Passkeys laufen im Frontend nur unter `http://localhost:3000` (lokal), nicht unter `127.0.0.1`.

---

## Benachrichtigungen (Mails)

- **Neuer Task oder neue Zuweisung:** Mail an die zugewiesene Person.
- **Neuer Kommentar:** Mail an die zugewiesene Person und alle, die den Task schon kommentiert haben.
- Die handelnde Person bekommt nie eine Mail. Wer das Projekt nicht sieht, auch nicht. Gleiche Person nochmals zuweisen löst nichts aus.
- Jede Person schaltet Mails für sich ab (`User.notifyByEmail`).
- Sprache der Mails = Sprache des Projekts (`Project.locale`, DE oder EN).
- Mails enthalten keine Beschreibung und keinen Kommentartext. Der Link führt auf `FRONTEND_URL/projects/{id}/kanban?task={id}`.
- Nicht enthalten: Statuswechsel, Fälligkeit, Digest, @-Erwähnungen.

---

## Datenmodell und Funktionen

**Kern:**
- `Project`: Name, Beschreibung (Klartext), Icon, Sprache, Label-Set, Mitglieder mit Rolle
- `Milestone`, `ToDo`, `Task`: Titel, Beschreibung (RichText), Icon, Fälligkeit (`dueDate` nur bei Tasks), Status (berechnet)
- `Task`: Status (offen, in Arbeit, erledigt), Kanban-Position, Zuweisung, Labels
- `Comment`: Autor, Text (RichText), Zeitstempel

**Icons:** Gespeichert wird nur der Name (zum Beispiel `rocket`). Der Stil „Duotone Light“ ist fest und steht im Frontend. Der Server prüft nur das Format, nicht die Existenz. Ungültige Namen zeigen das Standard-Icon.

**Labels (Label-Sets):**
- Ein Set ist eine benannte Gruppe von Labels, app-weit. Die App bringt Sets mit: „Softwareentwicklung“ (Recherche, Design, Feature, Fehler, Blockiert), „Event“ (Location, Speaker, Marketing, Bestätigt), „Podcast“ (Themenidee, Gästebuchung, Audioschnitt, Social Media).
- Mitgelieferte Sets sind unveränderlich, anpassen geht nur über Duplizieren.
- Jedes Projekt wählt ein Set. Tasks bekommen mehrere Labels aus diesem Set.
- Farben sind Töne der Web-Awesome-Palette (zehn Töne), keine Hex-Werte.
- Beim Wechsel des Sets behalten Tasks ihre alten Labels, die sind dann nicht mehr wählbar.

**Rich Text:** Beschreibungen und Kommentare sind TipTap-JSON. Erlaubt sind Absätze, Überschriften Ebene 2 und 3, Aufzählungen, nummerierte Listen, Zeilenumbrüche, Zitate, fett, kursiv und Links (nur http, https, mailto). Maximal 20 000 Zeichen.

**Assistent und MCP:**
- **KI-Assistent** (`/assistant`, nur Admins): Chat, der einen Projektplan entwirft (Milestones, ToDos, Tasks, Mitglieder). Entwurf speichern passiert erst mit „Jetzt eintragen“.
- **MCP-Server** „Mollo Planer“ (`POST /mcp`): Claude füllt Projekte über natürliche Sprache. Zugang über persönliche Tokens mit Fähigkeit `mcp`, 90 Tage gültig. Werkzeuge: `list_projects`, `get_project`, `list_users`, `create_project` (nur Admins), `set_project_member`, `create_project_plan`, `set_task_status`. Es gibt kein Löschen. Ändern kann Claude nur den Status einzelner Aufgaben (`set_task_status`, ToDo und Milestone werden berechnet). Die Werkzeuge führen die GraphQL-Mutationen im Namen der Person aus, mit denselben Regeln.

**Avatare:** JPG oder PNG, höchstens 5 MB, wird auf 500 × 500 px quadratisch zugeschnitten und als WebP ohne Metadaten gespeichert.

**Projektrollen-Verhalten im Frontend:** Die Seiten zeigen Knöpfe nach `viewerAbilities`. Nicht-Admins sehen nur Projekte, in denen sie Mitglied sind. Eine öffentliche Projektliste gibt es nicht.

---

## Seiten (Frontend)

| Route | Inhalt |
|---|---|
| `/login` | Passwort, 2FA-Code oder Wiederherstellungscode, „Mit Passkey anmelden“ |
| `/forgot-password`, `/reset-password` | Passwort zurücksetzen |
| `/invitation` | Einladung annehmen, Passwort setzen |
| `/setup-security` | Erste Einrichtung: TOTP-App oder Passkey |
| `/` | Dashboard |
| `/projects` | Projektliste, Admins legen Projekte an |
| `/projects/:id` | Zeitachse der Milestones (Hauptansicht) |
| `/projects/:id/todos` | ToDo-Liste mit Filtern |
| `/projects/:id/kanban?task=` | Kanban, `?task=` öffnet Task-Details (Link aus den Mails) |
| `/projects/:id/members` | Mitglieder und Rollen |
| `/projects/:id/settings` | Name, Beschreibung, Icon, Mail-Sprache, Label-Set, Projekt löschen |
| `/label-sets` | Label-Sets verwalten |
| `/my-tasks` | Meine Tasks, filterbar nach Status |
| `/profile` | Name, E-Mail, Passwort, Avatar, Mail-Benachrichtigungen, 2FA, Passkeys, Sprache |
| `/assistant` | KI-Assistent (nur Admins) |
| `/admin/users` | Benutzer (nur Admins) |
| `/admin/settings` | App-Einstellungen, 2FA-Schalter (nur Admins, nur außerhalb Produktion) |
| `/system/status` | Status von Backend, Datenbank und GraphQL (alle Angemeldeten) |
| `/system/db` | Datenbank-Dokumentation (nur Benutzerverwaltung) |

Die Routen `/invitation`, `/reset-password` und `/projects/:id/kanban?task=` sind fest, weil das Backend sie in Mails verschickt.

---

## Demo-Daten

`php artisan db:seed --class=DemoSeeder` (oder `migrate:fresh --seed --seeder=DemoSeeder` lokal):
- Personen: Anna Keller (Admin), Ben Müller, Chiara Rossi, David Frei (`…@example.ch`)
- Projekte: „Website-Relaunch“ (Set „Softwareentwicklung“) und „Sommerfest 2027“ (Set „Event“), mit Milestones, ToDos, Tasks, Icons und Kommentaren
- Die 2FA ist nach dem Einspielen aus, die Apps sind aber eingerichtet.

---

## Aktueller Stand (laut Plänen, Stand 2026-11-12)

- **Backend:** Grundbau, Rechte, Mutationen, Auth, Mails, KI-Assistent, MCP und Tests sind umgesetzt (vollständige Liste in `docs/backend-plan.md`).
- **Frontend:** Umstellung auf Web Awesome Pro ist fast fertig. Offen ist die Prüfung im Browser (hell und dunkel, Deutsch und Englisch, Tastatur) und die Abnahme.
- **Rollen:** Umgesetzt im Backend und Frontend. Offen sind die Prüfung im Browser je Rolle und einige Entscheidungen in `docs/rollen-plan.md`.
- **Labels:** Backend, Frontend und Seite `/label-sets` umgesetzt.
- **System-Bereich:** Status und Datenbank-Doku umgesetzt, Browser-Prüfung offen.
- **Desktop-App (Tauri):** geplant, nicht begonnen. Anmeldung über ein persönliches Token im Schlüsselbund, nicht über Cookies.
- **Hosting bei Hostpoint:** bewusst später.

Branches: Die Arbeit lief auf `feature/webawesome`, `feature/labels`, `feature/roles`, `feature/system`. Der Hauptbranch ist `main`, Entwicklung auf `dev`.

---

## Arbeitsweise

- Vor dem Bau die Dokumente lesen. Die Reihenfolge der Bau-Schritte und die Entscheidungen des Auftraggebers gelten.
- Bei Widersprüchen zwischen Code und Schema oder zwischen Dokumenten: nachfragen statt raten.
- Änderungen an Rechten, Schema oder Migrationen nur nach Rückfrage bzw. mit Tests.
- Vor jedem Commit Lint, Typecheck, Tests und Build (Frontend) bzw. Tests und Pint (Backend) grün halten.
- Commit-Texte auf Englisch.
