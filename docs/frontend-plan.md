# Frontend-Plan

Stand: 2026-11-08, vom Auftraggeber bestätigt. Ergänzt `backend-plan.md` und `graphql-kontext.md`; das GraphQL-Schema bleibt der Vertrag. Wo dieser Plan und `backend-plan.md` sich widersprechen, gilt `backend-plan.md`.

## Bestätigt vom Auftraggeber
1. **Grundlage:** das vorhandene Starter-Template von Nuxt UI in `frontend/` (Nuxt 4, Nuxt UI 4, Tailwind 4).
2. **Paketmanager npm** statt pnpm: `pnpm-lock.yaml` und `pnpm-workspace.yaml` entfallen, dafür `package-lock.json`; CI-Workflow auf npm umstellen.
3. **Icons überall Font Awesome Pro „Duotone Light“**, auch für Bedienelemente (Buttons, Menü, Hinweise), nicht Lucide.
4. **Farben vorerst wie im Template** (primär grün, neutral slate). Austausch später an einer Stelle (`app.config.ts`, `assets/css/main.css`).
5. **Erst Plan, dann bauen**, Schritt für Schritt wie beim Backend.
6. **Backend-Ergänzungen** unten sind bestätigt.
7. **Projekte:** Nicht-Admins sehen nur Projekte, in denen sie Mitglied sind (wie das Backend liefert). Keine öffentliche Projektliste.
8. **Kopf der App:** vorerst der Text „Projektverwaltung“, Logo folgt.

## Feste Rahmenbedingungen
- **Reine Single-Page-App** (`ssr: false`), gebaut mit `nuxt generate` zu statischen Dateien. Hostpoint kann nur PHP, kein Node. Auslieferung auf `project.fcadb.ch`, API auf eigener Subdomain. Die Hosting-Details (Rewrite auf `index.html`, Domains, Cookies) kommen später.
- **UI-Texte in Deutsch und Englisch** über `@nuxtjs/i18n` (`i18n/locales/de.json`, `en.json`), nie hart im Template. Standard Deutsch; Sprache wählbar, gemerkt im Browser. Jede Anfrage an die API schickt `Accept-Language`, damit Fehlermeldungen in derselben Sprache kommen.
- **RichText nur mit TipTap**, gespeichert und übertragen als JSON, angezeigt mit TipTap (nur lesend oder im Editor), **nie `v-html`**. Eine ESLint-Regel verbietet `v-html` im ganzen Frontend.
- **Rechte nur aus dem Backend:** Knöpfe richten sich nach `Project.viewerAbilities` und `Comment.viewerCanEdit/viewerCanDelete`, nie nach einer eigenen Rollenprüfung im Frontend. Das Backend prüft ohnehin jede Änderung.
- **Code auf Englisch** (Komponenten, Variablen, Kommentare), wie im Backend.

## Technik
- **GraphQL:** `@apollo/client` mit `@vue/apollo-composable` als eigenes Nuxt-Plugin (kein `@nuxtjs/apollo`, da die SPA kein SSR braucht). Requests mit `credentials: 'include'`, Header `X-XSRF-TOKEN` aus dem Cookie, `Accept-Language`. Uploads (Avatar) über `apollo-upload-client`. Versionen und Zusammenspiel beim Einbau prüfen.
- **Typen aus dem Schema:** GraphQL Code Generator (Preset `client`). Quelle ist das kompilierte Schema aus `php artisan lighthouse:print-schema`, abgelegt in `frontend/graphql/schema.graphql`. Abfragen liegen als `.graphql`-Dateien neben den Seiten. Ein Backend-Test prüft, dass das abgelegte Schema aktuell ist.
- **Login (REST über Fortify):** kleiner Client mit `$fetch` für `/sanctum/csrf-cookie`, `/login`, `/two-factor-challenge`, `/logout`, `/forgot-password`, `/reset-password`, `/invitation` und die 2FA-/Passkey-Routen unter `/user/…`. Passkeys im Browser mit WebAuthn (Bibliothek beim Einbau prüfen, zum Beispiel `@simplewebauthn/browser`).
- **Sperre der API:** Antwortet `/graphql` mit `SECOND_FACTOR_SETUP_REQUIRED`, führt die App zur Einrichtung; bei `STRONG_AUTHENTICATION_REQUIRED` zum erneuten Login. Ist die 2FA per Admin-Schalter aus (`appSettings.twoFactorEnabled`), fragt die App nicht nach der Einrichtung.
- **Icons:** Font Awesome Pro aus dem privaten npm-Register. Das Token steht nur in `frontend/.npmrc` (lokal, nicht eingecheckt) bzw. im GitHub-Secret. Die SVGs von „Duotone Light“ werden als eigene Icon-Sammlung in Nuxt UI eingebunden (`@nuxt/icon`, `customCollections`), auch für die Standard-Icons von Nuxt UI (`app.config.ts`, `ui.icons`). Kein Web Font, kein Kit. Zweifarbigkeit über `--fa-primary-color`/`--fa-secondary-color` bzw. `currentColor` und Deckkraft. Ordner und Metadaten des Pakets beim Einbau prüfen; geht es so nicht, Plan B: Web Font nur mit Duotone Light.
- **Icon-Picker:** Suche über die Metadaten des Pro-Pakets (Namen und Suchbegriffe), nur Icons, die es in Duotone Light gibt. Liste beim Build erzeugt, damit die App nicht den ganzen Katalog lädt.
- **Kanban:** Ziehen und Ablegen mit SortableJS (zum Beispiel `vue-draggable-plus`), danach `moveTask(id, status, position)`. Bedienbar auch mit Tastatur bzw. über ein Menü „Verschieben nach …“.
- **Tests:** Vitest mit `@nuxt/test-utils` für Composables und Komponenten (Rechte-Anzeige, RichText-Whitelist, Fehlerbehandlung der Sperre). Ende-zu-Ende-Tests (Playwright) gegen das Backend mit Demo-Daten später.
- **Qualität:** `npm run lint` (ESLint, Stil aus dem Template), `npm run typecheck`, beides im CI.

## Seiten
| Route | Inhalt |
|---|---|
| `/login` | E-Mail und Passwort, danach 2FA-Code oder Wiederherstellungscode; „Mit Passkey anmelden“ |
| `/forgot-password`, `/reset-password?token=&email=` | Passwort zurücksetzen (Link aus der Mail) |
| `/invitation?token=&email=` | Einladung annehmen, Passwort setzen (Link aus der Mail) |
| `/setup-security` | Erste Einrichtung: 2FA-App (QR-Code, Bestätigung, Wiederherstellungscodes) oder Passkey |
| `/` | Dashboard: anstehende ToDos, meine offenen Tasks, Projekte mit Fortschritt |
| `/projects` | Projektliste; Admins: Projekt anlegen |
| `/projects/:id` | Übersicht: Milestones mit ToDos und Fortschritt (anlegen, ändern, löschen nach Rechten) |
| `/projects/:id/todos` | ToDo-Liste mit Filtern (Milestone, Status) |
| `/projects/:id/kanban?task=` | Kanban; `?task=` öffnet die Task-Details im Seitenpanel (Link aus den Mails) |
| `/projects/:id/settings` | Name, Beschreibung, Sprache der Mails, Mitglieder und Rollen, Projekt löschen |
| `/my-tasks` | Meine Tasks, filterbar nach Status |
| `/profile` | Name und E-Mail, Passwort, Avatarbild, Mail-Benachrichtigungen, 2FA-App, Passkeys, Sprache |
| `/admin/users` | Benutzer anlegen (Einladung), ändern, 2FA zurücksetzen, löschen (nur Admins) |
| `/admin/settings` | App-Einstellungen: 2FA an/aus (nur Admins, nur ausserhalb Produktion) |

Die Routen `/invitation`, `/reset-password` und `/projects/:id/kanban?task=` sind fest, weil das Backend sie in Mails verschickt (`UserInvited`, `ResetPasswordNotification`, `TaskNotification::taskUrl()`).

Task-Details (Seitenpanel): Titel, Beschreibung (TipTap), Icon, Fälligkeit, Status, Zuweisung, Kommentare (TipTap mit kleinerer Werkzeugleiste, ändern und löschen nach `viewerCanEdit/viewerCanDelete`).

## Backend-Ergänzungen (bestätigt, ändern das Schema)
- `User.passkeys: [Passkey!]` (id, name, createdAt, lastUsedAt), nur für die Person selbst: Die Profilseite muss die eigenen Passkeys anzeigen; heute gibt es nur das Löschen (`DELETE /user/passkeys/{id}`).
- `User.twoFactorConfirmed: Boolean!`, nur für die Person selbst und Admins: Profil und Einrichtung müssen wissen, ob eine 2FA-App bestätigt ist.
- Befehl bzw. Test, der `frontend/graphql/schema.graphql` aus `lighthouse:print-schema` erzeugt und prüft, dass es aktuell ist.

## Reihenfolge beim Bauen
1. **Einrichtung:** npm statt pnpm, Template aufräumen (Demo-Inhalte, GitHub-Links), SPA-Modus, i18n de/en, Layout mit Seitenleiste (Nuxt UI Dashboard), Farb-Modus, `v-html`-Verbot, CI auf npm.
2. **Font Awesome:** `.npmrc` (Token von dir), Pro-Paket, eigene Icon-Sammlung „Duotone Light“, Nuxt-UI-Icons umstellen.
3. **API-Anbindung:** Apollo-Plugin (Cookies, XSRF, Sprache, Upload), Codegen aus dem Schema, Fehlerbehandlung (Sperre, Validierung, „unauthorized“).
4. **Anmeldung:** Login mit 2FA-Code und Wiederherstellungscode, Logout, Passwort vergessen/zurücksetzen, Einladung annehmen, Schutz der Seiten (Middleware), Umgang mit der Sperre.
5. **Dashboard und Projektliste.**
6. **Projekt:** Milestones und ToDos (Übersicht, ToDo-Liste) mit Anlegen, Ändern, Löschen nach Rechten.
7. **RichText-Editor und -Anzeige** (TipTap mit derselben Whitelist wie der Server), **Icon-Picker**.
8. **Kanban** mit Ziehen und Ablegen, **Task-Details** mit Kommentaren.
9. **Meine Tasks, Profil** (Avatar, Benachrichtigungen, Passwort, Sprache).
10. **Sicherheit im Profil:** 2FA-App einrichten und entfernen, Passkeys (Backend-Ergänzungen vorher), erste Einrichtung `/setup-security`, Login mit Passkey.
11. **Projekteinstellungen und Mitglieder.**
12. **Admin:** Benutzer und App-Einstellungen.
13. **Tests und Feinschliff:** Vitest, Barrierefreiheit (Tastatur, Fokus, Kontrast), leere Zustände, Fehlerseiten.

Nicht Teil dieses Plans: Hosting bei Hostpoint (Build-Pipeline, Rewrite, Domains, Cookies über Subdomains), Ende-zu-Ende-Tests.

## Was du beisteuern musst
- **Font-Awesome-Token** für das Pro-Paket (Schritt 2): in `frontend/.npmrc` eintragen, ich lege die Datei mit Platzhalter an und schreibe sie in `.gitignore`.

## Offene Fragen
- Logo (bis dahin Text im Kopf).
