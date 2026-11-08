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
- **GraphQL (umgesetzt in Schritt 3):** Apollo Client **3.14** mit `@vue/apollo-composable` 4.2, `apollo-upload-client` 18 und `graphql` 16, als eigenes Nuxt-Plugin `app/plugins/api.ts` (kein `@nuxtjs/apollo`). Apollo Client 4 bewusst nicht: Die Vue-Anbindung dafür gibt es erst als Beta (5.0.0-beta.1, November 2026); umstellen, wenn sie stabil ist. Requests mit `credentials: 'include'`, Header `X-XSRF-TOKEN` (aus dem Cookie, dekodiert), `Accept-Language` und `Accept: application/json`; vor der ersten Anfrage holt das Plugin `/sanctum/csrf-cookie`. Uploads über den Upload-Link (multipart).
- **Typen aus dem Schema (umgesetzt):** GraphQL Code Generator (Preset `client`, `codegen.ts`). Quelle ist das kompilierte Schema in `frontend/graphql/schema.graphql`, geschrieben mit `composer schema:frontend` im Backend; `FrontendSchemaTest` schlägt fehl, wenn es veraltet ist. Abfragen stehen in TypeScript mit `graphql(\`...\`)` aus `~/graphql/generated` (zum Beispiel `app/graphql/viewer.ts`). Der erzeugte Ordner ist eingecheckt; die CI prüft, dass `npm run codegen` nichts ändert.
- **Login (REST über Fortify):** `$backend` aus dem Plugin (`$fetch` mit Cookies, XSRF, Sprache) für `/sanctum/csrf-cookie`, `/login`, `/two-factor-challenge`, `/logout`, `/forgot-password`, `/reset-password`, `/invitation` und die 2FA-/Passkey-Routen unter `/user/…`. Passkeys im Browser mit WebAuthn (Bibliothek beim Einbau prüfen, zum Beispiel `@simplewebauthn/browser`).
- **Fehler und Sperre (umgesetzt):** `app/utils/api.ts` ordnet jede fehlgeschlagene Anfrage ein (GraphQL wie REST): Sperre (`SECOND_FACTOR_SETUP_REQUIRED` → `/setup-security`, `STRONG_AUTHENTICATION_REQUIRED` → `/login`), nicht angemeldet → `/login?redirect=…`, keine Berechtigung, Validierung (Meldungen je Feld), CSRF abgelaufen (419), Netzwerk. `app/plugins/api-errors.ts` reagiert darauf einmal für die ganze App (Umleitung oder Meldung); Validierungsfehler bleiben bei den Formularen. Ist die 2FA per Admin-Schalter aus (`appSettings.twoFactorEnabled`), fragt die App nicht nach der Einrichtung.
- **Icons (umgesetzt in Schritt 2):** `@fortawesome/fontawesome-pro` 7 aus dem privaten npm-Register. Das Token steht nur in `frontend/.npmrc` (lokal, in `.gitignore`); die CI schreibt die Datei aus dem GitHub-Secret `FONTAWESOME_NPM_AUTH_TOKEN`.
  - **Bedienelemente:** eigene Icon-Sammlung `fadl` in Nuxt Icon aus `svgs-full/duotone-light` (gleiches 640er-Raster für alle). Namen wie `i-fadl-rocket`. Ins Bundle kommen nur Icons, die im Code stehen (`clientBundle.scan`) und die Nuxt-UI-Icons aus `app/ui-icons.ts` (von `app.config.ts` und `nuxt.config.ts` gemeinsam genutzt, weil der Scan `app.config` nicht sieht). `provider: 'none'`: zur Laufzeit keine Anfragen an die Iconify-API.
  - **Wählbare Icons (Milestone, ToDo, Task):** Die Namen kommen aus der Datenbank, sind beim Build also unbekannt. Alle 4804 SVGs werden darum unter `/icons/duotone-light/<name>.svg` mit ausgeliefert (rund 20 MB auf dem Server) und einzeln bei Bedarf geladen. `<ItemIcon :name :fallback>` zeigt sie als CSS-Maske in der Textfarbe; die zweite Ebene hat im SVG 40 % Deckkraft, der Duotone-Look bleibt. Ungültige Namen (Format wie `FontAwesomeIconRule`) zeigen das Standard-Icon. Eigene Farben für die zweite Ebene sind so nicht möglich.
  - Kein Web Font, kein Kit.
- **Icon-Picker:** Suche über die Metadaten des Pro-Pakets (Namen und Suchbegriffe), nur Icons, die es in Duotone Light gibt. Liste beim Build erzeugt, damit die App nicht den ganzen Katalog lädt.
- **Kanban:** Ziehen und Ablegen mit SortableJS (zum Beispiel `vue-draggable-plus`), danach `moveTask(id, status, position)`. Bedienbar auch mit Tastatur bzw. über ein Menü „Verschieben nach …“.
- **Tests:** Vitest mit `@nuxt/test-utils` für Composables und Komponenten (Rechte-Anzeige, RichText-Whitelist, Fehlerbehandlung der Sperre). Ende-zu-Ende-Tests (Playwright) gegen das Backend mit Demo-Daten später.
- **Qualität:** `npm run lint` (ESLint, Stil aus dem Template), `npm run typecheck`, beides im CI.

## Anmeldung (umgesetzt in Schritt 4)
- Öffentliche Seiten tragen `definePageMeta({ public: true })` (Layout `auth`): `/login`, `/forgot-password`, `/reset-password`, `/invitation`. Alle anderen schützt `middleware/auth.global.ts`: Sie fragt einmal `viewerQuery` (`me`, `appSettings.twoFactorEnabled`) und leitet sonst um (nicht angemeldet → `/login?redirect=…`, kein zweiter Faktor → `/setup-security`, Backend weg → Fehlerseite 503).
- `useViewer()` hält die angemeldete Person für die ganze App, `logout()` beendet die Sitzung und leert den Apollo-Cache.
- Login: E-Mail und Passwort (`POST /login`); antwortet das Backend mit `two_factor: true`, folgt der 6-stellige Code (`UPinInput` mit `otp`) oder ein Wiederherstellungscode (`POST /two-factor-challenge`). Danach zurück zur gewünschten Seite; `safeRedirect()` lässt nur Pfade dieser App zu.
- Fehlermeldungen des Servers erscheinen am Feld (`useServerForm()`); andere Fehler meldet das Plugin.
- `/setup-security` ist bis Schritt 10 ein Platzhalter mit Abmelden.

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
