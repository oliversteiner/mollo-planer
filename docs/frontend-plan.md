# Frontend-Plan

Stand: 2026-11-08, vom Auftraggeber bestätigt; Oberfläche seit 2026-11-09 auf Web Awesome Pro umgestellt (Einzelheiten und Entscheidungen: `frontend-webawesome-plan.md`). Ergänzt `backend-plan.md` und `graphql-kontext.md`; das GraphQL-Schema bleibt der Vertrag. Wo dieser Plan und `backend-plan.md` sich widersprechen, gilt `backend-plan.md`.

## Bestätigt vom Auftraggeber
1. **Grundlage:** Nuxt 4 als SPA. Ursprünglich das Starter-Template von Nuxt UI (Nuxt UI 4, Tailwind 4); seit 2026-11-09 **Web Awesome Pro** (Theme „Matter“) ohne Nuxt UI und ohne Tailwind (`frontend-webawesome-plan.md`).
2. **Paketmanager npm** statt pnpm: `pnpm-lock.yaml` und `pnpm-workspace.yaml` entfallen, dafür `package-lock.json`; CI-Workflow auf npm umstellen.
3. **Icons überall Font Awesome Pro „Duotone Light“**, auch für Bedienelemente (Buttons, Menü, Hinweise), nicht Lucide.
4. **Farben und Schrift** aus dem Theme „Matter“ von Web Awesome (Palette „Mild“, Markenfarbe Lila, Schrift Wix Madefor Text), gesetzt als Klassen auf `<html>` in `app.vue` und in `app/assets/css/webawesome.css`.
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
  - **Bedienelemente:** `<wa-icon name="…">` von Web Awesome. Die Bibliotheken `default` und `system` sind in `app/utils/wa-icons.ts` auf dieselben Duotone-Light-SVGs umgebogen (`/icons/duotone-light/<name>.svg`), auch die Icons in den Komponenten selbst (Pfeile, Schliessen …). Kein Font-Awesome-CDN, keine Iconify-API.
  - **Wählbare Icons (Milestone, ToDo, Task):** Die Namen kommen aus der Datenbank, sind beim Build also unbekannt. Alle 4804 SVGs werden darum unter `/icons/duotone-light/<name>.svg` mit ausgeliefert (rund 20 MB auf dem Server) und einzeln bei Bedarf geladen. `<ItemIcon :name :fallback>` ist ein `wa-icon` in der Textfarbe, die zweite Ebene mit 40 % Deckkraft; über `--primary-color`/`--secondary-color` lassen sich beide Ebenen getrennt färben. Ungültige oder fehlende Namen (Format wie `FontAwesomeIconRule`) zeigen das Standard-Icon.
  - Kein Web Font, kein Kit.
- **RichText (umgesetzt in Schritt 7, 2026-11-09 ohne Nuxt UI):** TipTap 3 direkt (`useEditor`, `EditorContent`), ohne StarterKit; alle Erweiterungen kommen allein aus `app/utils/rich-text.ts` (gleiche Liste wie `RichTextDocument.php`), damit nichts in das Schema kommt, was der Server ablehnt. Werkzeugleiste aus `wa-button`, Link über ein `wa-popover`, Darstellung in `app/assets/css/rich-text.css`. Ein Test baut den Editor und prüft, dass sein Schema genau die Whitelist hat und eingefügtes HTML (Code, Trennlinie, Bild, Durchgestrichen, `javascript:`-Links, Überschrift 1 und 4) nichts Unerlaubtes erzeugt. Links nur http, https, mailto, eigenes Eingabefeld statt `prompt()`. `RichTextEditor` (Prop `compact` für Kommentare) und `RichTextView` (nur lesen, TipTap rendert, kein `v-html`). Ein Dokument ohne Text wird als `null` gesendet.
- **Icon-Picker (umgesetzt in Schritt 7):** `scripts/icon-catalog.ts` baut aus den Metadaten des Pro-Pakets (115 MB) einmal pro Paketversion einen Katalog aller Duotone-Light-Icons (Name, Bezeichnung, Suchbegriffe, rund 540 KB, gezippt 155 KB), ausgeliefert als `/icons/catalog.json` und erst beim Öffnen des Pickers geladen. Ohne Suchbegriff Vorschläge für Projekte; die Suchbegriffe von Font Awesome sind englisch.
- **Kanban (umgesetzt in Schritt 8):** `vue-draggable-plus` (SortableJS). Nach dem Ablegen `moveTask(id, status, position)`; die Position zählt wie im Backend ohne den Task selbst (`utils/kanban.ts`, getestet). Der Zug ist sofort sichtbar und wird danach durch die Antwort des Servers ersetzt; schlägt er fehl, lädt das Board neu. Ohne Ziehen: Menü auf jeder Karte mit „Nach oben/unten“ und „Verschieben nach …“. Task-Details im Seitenpanel über `?task=` (auch aus den Mails), mit Status, Zuweisung, Bearbeiten, Löschen und Kommentaren.
- **Tests (umgesetzt in Schritt 13):** Vitest mit zwei Projekten (`vitest.config.ts`): `unit` für reine Funktionen (Fehler-Einordnung, Weiterleitungsschutz, Datum, RichText-Whitelist mit echtem Editor, Icon-Suche, Kanban-Reihenfolge, Avatar-Prüfung) und `nuxt` mit `@nuxt/test-utils` für Route-Middleware (Login, Sperre, Einrichtung, Backend weg, Admin) und Bausteine (`ItemIcon`, `DueDate`, `StatusBadge`). Die Testumgebung meldet sich als englischer Browser; Tests mit deutschen Texten setzen die Sprache selbst. Ende-zu-Ende-Tests (Playwright) gegen das Backend mit Demo-Daten später.
- **Qualität:** `npm run lint` (ESLint, Stil aus dem Template), `npm run typecheck`, beides im CI.

## Anmeldung (umgesetzt in Schritt 4)
- Öffentliche Seiten tragen `definePageMeta({ public: true })` (Layout `auth`): `/login`, `/forgot-password`, `/reset-password`, `/invitation`. Alle anderen schützt `middleware/auth.global.ts`: Sie fragt einmal `viewerQuery` (`me`, `appSettings.twoFactorEnabled`) und leitet sonst um (nicht angemeldet → `/login?redirect=…`, kein zweiter Faktor → `/setup-security`, Backend weg → Fehlerseite 503).
- `useViewer()` hält die angemeldete Person für die ganze App, `logout()` beendet die Sitzung und leert den Apollo-Cache.
- Login: E-Mail und Passwort (`POST /login`); antwortet das Backend mit `two_factor: true`, folgt der 6-stellige Code (`wa-otp-input`, sendet bei vollständiger Eingabe) oder ein Wiederherstellungscode (`POST /two-factor-challenge`). Danach zurück zur gewünschten Seite; `safeRedirect()` lässt nur Pfade dieser App zu.
- Fehlermeldungen des Servers erscheinen am Feld (`useServerForm()`); andere Fehler meldet das Plugin.
- `/setup-security` ist bis Schritt 10 ein Platzhalter mit Abmelden.

## Navigation (Anpassung 2026-11-08)
- Seitenleiste: Dashboard und Meine Tasks; darunter die Kategorie „Projekte“ mit einer Projektauswahl und den Links Übersicht, ToDos, Kanban und Einstellungen für das gewählte Projekt (`SidebarProjects`); für Admins „Verwaltung“. Gewählt ist das Projekt der aktuellen Seite, sonst das zuletzt gewählte (im Browser gemerkt), sonst das erste. Ein Wechsel behält die Unterseite (Kanban → Kanban).
- Die Reiter im Projektkopf entfallen; der Kopf zeigt „Projekt · Unterseite“. Die Projektliste (`/projects`, mit „Projekt anlegen“ für Admins) ist nur noch über „Alle Projekte“ auf dem Dashboard erreichbar.

## Seiten
| Route | Inhalt |
|---|---|
| `/login` | E-Mail und Passwort, danach 2FA-Code oder Wiederherstellungscode; „Mit Passkey anmelden“ |
| `/forgot-password`, `/reset-password?token=&email=` | Passwort zurücksetzen (Link aus der Mail) |
| `/invitation?token=&email=` | Einladung annehmen, Passwort setzen (Link aus der Mail) |
| `/setup-security` | Erste Einrichtung: 2FA-App (QR-Code, Bestätigung, Wiederherstellungscodes) oder Passkey |
| `/` | Dashboard: anstehende ToDos, meine offenen Tasks, Projekte mit Fortschritt |
| `/projects` | Projektliste; Admins: Projekt anlegen |
| `/projects/:id?milestones=&task=` | Übersicht als Zeitstrahl: Milestones nach Fälligkeit (Nummer M1, M2 …), darin ToDos zum Aufklappen mit ihren Tasks, Darstellung wie die Vorschau des KI-Assistenten. Anlegen, Ändern und Löschen von Milestones, ToDos und Tasks nach Rechten. Tasks wie im Kanban: Klick öffnet die Details im Seitenpanel (`?task=`), Doppelklick das Bearbeiten. Tasks per Drag & Drop in ein anderes ToDo ziehen (`moveTaskToTodo`, mit Recht „Task ändern“); ein zugeklapptes ToDo öffnet sich, wenn man einen Task darüber hält. Innerhalb eines ToDos bleibt die Reihenfolge nach Fälligkeit. „Nur Milestones anzeigen“ blendet Beschreibungen und ToDos aus (`?milestones=only`) |
| `/projects/:id/todos` | ToDo-Liste mit Filtern (Milestone, Status) |
| `/projects/:id/kanban?task=` | Kanban; `?task=` öffnet die Task-Details im Seitenpanel (Link aus den Mails) |
| `/projects/:id/settings` | Name, Beschreibung, Sprache der Mails, Mitglieder und Rollen, Projekt löschen |
| `/my-tasks` | Meine Tasks, filterbar nach Status |
| `/profile` | Name und E-Mail, Passwort, Avatarbild, Mail-Benachrichtigungen, 2FA-App, Passkeys, Sprache |
| `/assistant` | KI-Assistent (nur Admins): links Chat mit Spracheingabe, rechts Vorschau des Entwurfs (Name, Beschreibung, Timeline mit Milestones, aufklappbaren ToDos und Tasks), „Jetzt eintragen“ |
| `/admin/users` | Benutzer anlegen (Einladung), ändern, 2FA zurücksetzen, löschen (nur Admins) |
| `/admin/settings` | App-Einstellungen: 2FA an/aus (nur Admins, nur ausserhalb Produktion) |

Die Routen `/invitation`, `/reset-password` und `/projects/:id/kanban?task=` sind fest, weil das Backend sie in Mails verschickt (`UserInvited`, `ResetPasswordNotification`, `TaskNotification::taskUrl()`).

Task-Details (Seitenpanel): Titel, Beschreibung (TipTap), Icon, Fälligkeit, Status, Zuweisung, Kommentare (TipTap mit kleinerer Werkzeugleiste, ändern und löschen nach `viewerCanEdit/viewerCanDelete`).

## KI-Assistent (2026-11-08)
- Seite `/assistant`, in der Seitenleiste unter „KI-Assistent“ (nur Admins). `wa-split-panel` mit zwei Bereichen (Anteil des Chats im Browser gemerkt, ohne Entwurf ist die Vorschau ein schmaler Streifen), ab 1024 px nebeneinander, darunter übereinander.
- **Chat** (`assistant/Chat.vue`): Enter sendet, Shift+Enter neue Zeile. **Spracheingabe** über die Web Speech API des Browsers (`useSpeechInput`, Sprache = UI-Sprache, ohne Zusatzpaket). Chrome und Edge schicken die Aufnahme zur Erkennung an Google bzw. Microsoft (Hinweis am Knopf); Firefox kennt sie nicht, dort fehlt der Knopf.
- **Vorschau** (`assistant/PlanPreview.vue`): 1. Projektname mit Zahlen, 2. Beschreibung und Mitglieder (Name, Avatar, Rolle), 3. Zeitachse der Milestones (`StepTimeline` auf `wa-stepper`) mit Fälligkeit und Beschreibung, darin die ToDos zum Aufklappen (einzeln oder alle), darin die Tasks mit Beschreibung, Fälligkeit und zugewiesener Person. Beschreibungen sind Klartext und werden als Text gezeigt (`AssistantPlainText`), nie als HTML.
- Jede Nachricht schickt den ganzen Chat und den aktuellen Entwurf (`draftProjectPlan`); Claude ändert den Entwurf, statt neu zu beginnen. „Jetzt eintragen“ (`importProjectPlan`, wahlweise mit Zuweisungsmails) legt alles an und öffnet das neue Projekt. Chat und Entwurf überstehen ein Neuladen (sessionStorage dieses Tabs).

## Sicherheit (umgesetzt in Schritt 10)
- Profil: Authenticator-App einrichten (QR-Code als Bild, nicht als eingefügtes HTML; Schlüssel zum Abtippen; Bestätigung mit Code; Wiederherstellungscodes zum Kopieren oder Herunterladen), Codes anzeigen und neu erzeugen, App entfernen; Passkeys hinzufügen (Name, WebAuthn mit `@simplewebauthn/browser`) und entfernen. Vor jeder Änderung fragt `useSecurity()` den Bestätigungsstatus ab und bittet bei Bedarf um das Passwort (`PasswordPromptModal`), weil Fortify und die Passkey-Routen eine frische Passwort-Bestätigung verlangen. Beim Entfernen des letzten Faktors warnt die Rückfrage, solange die 2FA Pflicht ist.
- `/setup-security`: erste Einrichtung (Passkey empfohlen oder App); danach öffnet sich die App, weil der erste Faktor die Sitzung als stark markiert (Backend).
- Login: „Mit Passkey anmelden“ (ein Schritt, kein Code), wenn der Browser WebAuthn kann.
- Passkeys verlangen, dass die App unter `http://localhost:3000` läuft (Relying-Party-ID `localhost`, erlaubter Ursprung im Backend), nicht unter `127.0.0.1`.

## Backend-Ergänzungen (bestätigt, ändern das Schema)
- `User.passkeys: [Passkey!]` (id, name, createdAt, lastUsedAt), nur für die Person selbst, sonst null (umgesetzt in Schritt 10).
- `User.twoFactorConfirmed: Boolean` und `User.hasPasskey: Boolean`, nur für die Person selbst und Admins, sonst null (deshalb nullbar statt `Boolean!`; `hasPasskey` zusätzlich für die Benutzerverwaltung der Admins). Geregelt in `UserPolicy::viewSecurity` (umgesetzt in Schritt 10).
- Befehl bzw. Test, der `frontend/graphql/schema.graphql` aus `lighthouse:print-schema` erzeugt und prüft, dass es aktuell ist (umgesetzt in Schritt 3).

## Reihenfolge beim Bauen
1. **Einrichtung:** npm statt pnpm, Template aufräumen (Demo-Inhalte, GitHub-Links), SPA-Modus, i18n de/en, Layout mit Seitenleiste (damals Nuxt UI Dashboard, heute `wa-page`), Farb-Modus, `v-html`-Verbot, CI auf npm.
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

Feinschliff (Schritt 13): Link „Zum Inhalt springen“ für die Tastatur; ein Prüflauf fand keine Icon-Knöpfe ohne zugänglichen Namen; die Benutzerliste der Admins lädt die Passkeys gebündelt (`@with`, Test gegen N+1 im Backend).

Nicht Teil dieses Plans: Hosting bei Hostpoint (Build-Pipeline, Rewrite, Domains, Cookies über Subdomains), Ende-zu-Ende-Tests.

## Was du beisteuern musst
- **Font-Awesome-Token** für das Pro-Paket (Schritt 2): in `frontend/.npmrc` eintragen, ich lege die Datei mit Platzhalter an und schreibe sie in `.gitignore`.

## Offene Fragen
- Logo (bis dahin Text im Kopf).
