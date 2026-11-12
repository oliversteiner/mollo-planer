# Frontend: Umstellung von Nuxt UI auf Web Awesome Pro

Stand: 2026-11-09. Plan freigegeben. Gilt für den Branch `feature/webawesome`.
Ergänzt `docs/frontend-plan.md`. Wo sich beide widersprechen, gilt für die Oberfläche dieses Dokument; für alles andere gelten `CLAUDE.md` und `docs/backend-plan.md`.

## Auftrag an Claude Code
- Ersetze im Frontend (`frontend/`) alle Nuxt-UI-Elemente durch Web-Awesome-Pro-Elemente (Phase A), danach entferne Tailwind (Phase B). Die Funktionen der App ändern sich nicht.
- Arbeite nur im Branch `feature/webawesome`. Arbeite die Schritte unten der Reihe nach ab, jeweils ein Commit pro Schritt (Commit-Text auf Englisch).
- Nutze die Skills `webawesome` und `webawesome-design` für Komponenten-APIs, Tokens und Design. Im Zweifel die installierte Version im Paket nachlesen (`node_modules/@web.awesome.me/...`), nicht aus dem Gedächtnis.
- Nach jedem Schritt: `npm run lint`, `npm run typecheck`, `npm test`, `npm run build` grün. Seiten, die der Schritt betrifft, im Browser prüfen (`npm run dev`, hell und dunkel, de und en).
- Nach jedem Schritt den Abschnitt „Stand“ unten abhaken.
- Bei Unklarheiten (fehlendes Gegenstück, Verhalten würde sich ändern) nachfragen statt raten.
- Die Regeln aus `CLAUDE.md` gelten weiter: UI-Texte nur über `i18n/` (de, en), RichText nie mit `v-html`, keine Tokens im Repository.

## Entscheidungen (vom Auftraggeber, 2026-11-09)
1. **Icons: eigene Icon-Library.** Kein Font-Awesome-Kit, kein CDN. Die Duotone-Light-SVGs bleiben auf dem eigenen Server (`/icons/duotone-light/<name>.svg`, siehe `nuxt.config.ts`).
2. **Tailwind wird entfernt**, aber erst in Phase B. Danach Layout über Web-Awesome-Utilities und scoped CSS mit WA-Tokens.
3. **Git-Branch** `feature/webawesome` von `dev` (kein Fork).
4. **Lizenz:** Web Awesome Pro Perpetual License (siehe unten).
5. **Theme: „Matter“** (Pro). Klasse `wa-theme-matter` auf `<html>` plus Theme-Stylesheet; die zum Theme gehörende Palette und Markenfarbe aus dem Paket übernehmen. Schrift bleibt Public Sans, falls sich das über Tokens sauber setzen lässt (sonst nachfragen).
6. **Eigener npm-Token für Web Awesome Pro**, getrennt vom Font-Awesome-Token. Lokal in `frontend/.npmrc` (nicht eingecheckt, der Token kommt aus der Umgebungsvariable `WEBAWESOME_NPM_TOKEN`), in CI als Secret `WEBAWESOME_NPM_AUTH_TOKEN`. Paketname und Scope: `@web.awesome.me/webawesome-pro` (Version 3.14.0).
7. **Wrapper-Tiefe:** `wa-*` direkt in Templates. Eigene Wrapper nur für Formularelemente (wegen `v-model`) und für Overlays/Toasts (Composables).
8. **Skills:** `webawesome` und `webawesome-design` aus dem installierten Paket (`npx skills add ./node_modules/@web.awesome.me/.../dist/skills/<name>`).

## Ausgangslage (Bestandsaufnahme Branch `dev`, 2026-11-09)
- Nuxt 4, `@nuxt/ui` 4.12, Tailwind 4.3, `ssr: false` (SPA, `nuxt generate`) → kein SSR-Problem mit Web Components.
- 54 Vue-Dateien, rund 6400 Zeilen; **50 Dateien nutzen Nuxt UI.**
- Häufigkeit: UButton 85, UFormField 47, UInput 27, UBadge 14, USkeleton 13, UCard 13, USelect 12, UIcon 12, UEmpty 12, UForm 11, UAlert 11, UAvatar 9, UDashboardPanel/Navbar 8, UModal 7, UPageCard 6, UDropdownMenu 6. Einzelstücke: UTimeline (`project/Timeline.vue`, `assistant/PlanPreview.vue`), UEditor/UEditorToolbar (`rich-text/Editor.vue`, `View.vue`), UPinInput (2FA), UNavigationMenu, UDashboardGroup/Sidebar (`layouts/default.vue`), USplitter, USlideover, UPopover, UCollapsible, UUser, UAvatarGroup, ULocaleSelect, UColorModeSelect/Button, UProgress, USwitch, UCheckbox, USelectMenu, ULink, USeparator, UApp, UMain.
- `useToast` an 23 Stellen; 59 benannte Slots; 9 `:ui`-Overrides; `app.config.ts` setzt Farben und `uiIcons`.
- Formulare über `composables/useServerForm.ts` und `useGraphqlForm.ts` (keine Schema-Bibliothek).
- Tailwind: rund 400 `class`-Attribute mit etwa 900 Utility-Klassen; `assets/css/main.css` mit `@theme` und den Utilities `plan-in`, `plan-timeline`; ESLint-Plugin `better-tailwindcss`.
- Icons: SVGs aus `@fortawesome/fontawesome-pro/svgs-full/duotone-light` werden als Public Assets ausgeliefert; UI-Icons über die Iconify-Collection `fadl` (`app/ui-icons.ts`); Icon-Katalog für den Picker unter `/icons/catalog.json`.
- Drag-and-drop mit `vue-draggable-plus`, TipTap 3, Vitest (`tests/unit`, `tests/nuxt`), CI in `.github/workflows/`.

## Web Awesome: das Wichtigste für die Umsetzung
- Komponenten sind Web Components (Lit, Shadow DOM), Präfix `wa-`. Immer mit schliessendem Tag schreiben (`<wa-icon ...></wa-icon>`).
- Konfiguration über Attribute/Properties, Events mit Präfix `wa-` (`wa-change`, `wa-input`, `wa-show`, `wa-hide` …), Slots über das Attribut `slot`, Styling von aussen nur über `::part()` und CSS-Variablen.
- Tokens: `--wa-color-*`, `--wa-space-*`, `--wa-font-*`, `--wa-border-*`, `--wa-shadow-*`, `--wa-focus-*`. Dark Mode über die Klasse `wa-dark` auf `<html>`.
- Layout-Utilities: `wa-stack`, `wa-cluster`, `wa-grid`, `wa-split`, `wa-flank`, `wa-gap-*`; `wa-cloak` gegen Aufblitzen.
- Formularelemente sind form-associated: native Validierung (`required`, `pattern`, `setCustomValidity()`), Zustände per `:state(invalid)`.
- Vue: `vue.compilerOptions.isCustomElement: tag => tag.startsWith('wa-')` in `nuxt.config.ts`; Komponenten einzeln in einem Client-Plugin importieren; `v-model` auf Custom Elements ist unzuverlässig → Wrapper oder `:value` + `@input`; Objekte/Arrays mit `.prop` binden; Vue-Typen aus `dist/types/vue/index.d.ts` in `tsconfig.json`.
- Versionen ändern sich schnell (in 3.11 wurden CSS-Parts umbenannt). **Version exakt gepinnt** (ohne `^`).

## Bewusst nicht ändern
TipTap (JSON, Whitelist, `utils/rich-text.ts`), `vue-draggable-plus`, Apollo/GraphQL und Codegen, i18n, Fortify-Abläufe (Login, 2FA, Passkeys), Icon-Katalog und Icon-Auslieferung, Middleware.

## Zuordnung Nuxt UI → Web Awesome
| Nuxt UI | Web Awesome | Hinweis |
|---|---|---|
| UApp, UMain | entfällt bzw. `wa-page` | |
| UDashboardGroup/Sidebar/Panel/Navbar | `wa-page` (Slots für Navigation, Header, Inhalt) | eigene Layout-Komponenten |
| UNavigationMenu | eigene Navigation (Liste mit NuxtLink, aktiver Zustand) | |
| UButton, ULink | `wa-button` (mit `href` für Links) bzw. NuxtLink | |
| UInput / UTextarea | `wa-input` / `wa-textarea` | Wrapper mit `v-model` |
| USelect, ULocaleSelect | `wa-select` + `wa-option` | |
| USelectMenu | `wa-combobox` (Pro) | |
| UCheckbox / USwitch | `wa-checkbox` / `wa-switch` | |
| UForm / UFormField | natives `<form>` + Constraint Validation; Label, Hinweis und Fehler über die Slots/Attribute der WA-Felder | `useServerForm`/`useGraphqlForm` anpassen, Serverfehler aus `extensions.validation` per `setCustomValidity` |
| UPinInput (2FA) | `wa-input` mit `inputmode="numeric"`, `autocomplete="one-time-code"` | |
| UModal / USlideover | `wa-dialog` / `wa-drawer` | Composable für Bestätigung |
| UPopover / UTooltip / UDropdownMenu | `wa-popover` / `wa-tooltip` / `wa-dropdown` + `wa-dropdown-item` | |
| UCard, UPageCard | `wa-card` | |
| UCollapsible | `wa-details` | |
| USplitter | `wa-split-panel` | |
| USeparator | `wa-divider` | |
| UBadge | `wa-badge` bzw. `wa-tag` | |
| UAlert | `wa-callout` | |
| UAvatar / UAvatarGroup / UUser | `wa-avatar` + eigene Gruppe und User-Zeile | |
| UProgress | `wa-progress-bar` / `wa-progress-ring` | |
| USkeleton | `wa-skeleton` | |
| UEmpty | eigene Komponente `EmptyState` | |
| UTimeline | eigene Komponente `StepTimeline` auf `wa-stepper` (`orientation="vertical"`) und `wa-step`; Fallback: eigene CSS-Timeline | Drag-and-drop bleibt |
| UEditor / UEditorToolbar | TipTap `EditorContent` + Toolbar aus `wa-button` | Whitelist unverändert |
| UIcon / ItemIcon | `wa-icon` mit eigener Library | |
| useToast | `wa-toast` + eigenes `useToast` | gleiche Aufruf-API wie bisher |
| UColorModeSelect/Button | eigener Umschalter, Klasse `wa-dark` | Einstellung merken |

## Lizenz
Web Awesome Pro Perpetual License: Abgedeckt sind die Versionen, die während der Laufzeit erscheinen; diese dürfen nach Ablauf unbefristet weiter genutzt werden, spätere nicht. Downloads aus dem Register sind nur während der Laufzeit zugesichert. Keine Weitergabe der Pro-Dateien an Personen ohne Lizenz-Sitz.
Darum: Version exakt pinnen, Lockfile committen, die verwendete Version zusätzlich mit `npm pack` sichern (privat, nicht ins Repository), Repository privat halten.

## Schritte

### Vorbereitung (lokal durch den Auftraggeber)
- Branch `feature/webawesome` von `dev`.
- `.npmrc`-Zeilen für Web Awesome Pro aus dem Konto ergänzen. Falls WA Pro denselben Registry-Host wie Font Awesome nutzt, geht nur ein Token pro Host → dann nachfragen.
- `npm install --save-exact @web.awesome.me/webawesome-pro`, Skills einrichten, `npm pack` als Sicherung, GitHub-Secret `WEBAWESOME_NPM_AUTH_TOKEN`.

### Phase A — Nuxt UI → Web Awesome (Tailwind bleibt vorerst)
1. **Einbindung:** `isCustomElement`, Client-Plugin mit den benötigten Komponenten, Stylesheet, Theme Matter, Dark Mode (`wa-dark`), Vue-Typen, `wa-cloak`. CI-Workflow um den WA-Token erweitern, `frontend/README.md` um den Abschnitt zum WA-Pro-Token ergänzen. Nuxt UI läuft in diesem Schritt noch parallel.
2. **Icons:** WA-Icon-Libraries `default` und `system` auf `/icons/duotone-light/` registrieren. Prüfen, ob die Zweifarbigkeit (primary/secondary) der Duotone-SVGs im Shadow DOM stimmt und sich über Tokens färben lässt. `ItemIcon` und die UI-Icons (`app/ui-icons.ts`) auf `wa-icon` umstellen.
3. **Bausteine:** Wrapper für Formularelemente (`v-model`), `useToast` mit `wa-toast`, Bestätigungs- und Passwortdialog (`ConfirmModal`, `PasswordPromptModal`), Formular-Helfer für Serverfehler, `EmptyState`, `Timeline`, User-Zeile mit Avatar.
4. **Layout:** `layouts/default.vue` und `layouts/auth.vue` auf `wa-page`, Sidebar mit Projekten (`SidebarProjects.vue`), Navigation, Sprach- und Farbmodus-Umschalter.
5. **Seiten** in dieser Reihenfolge: Login, 2FA, Passkey, Einladung, Passwort vergessen/zurücksetzen → Dashboard → Projekt (Zeitachse) → Kanban, Task-Details, Kommentare → Meine Tasks, Profil, Sicherheit → Admin → Assistent.
6. **Rich-Text und Icon-Picker:** Editor und Ansicht ohne UEditor; Icon-Picker mit `wa-combobox` oder Dialog mit Suche.
7. **Nuxt UI entfernen:** `@nuxt/ui` aus `package.json` und `nuxt.config.ts`, `app.config.ts` aufräumen, Tests anpassen (Komponententests prüfen Attribute statt Nuxt-UI-Klassen, z.B. `DueDate` prüft heute `text-error`).

### Phase B — Tailwind entfernen
8. Klassen Datei für Datei durch WA-Utilities und scoped CSS mit WA-Tokens ersetzen; `main.css` auf das WA-Theme umstellen (Schrift, Animationen `plan-in`/`plan-timeline` als normales CSS, `prefers-reduced-motion` beibehalten); `tailwindcss` und `eslint-plugin-better-tailwindcss` entfernen.

### Abschluss
9. Barrierefreiheit (Tastatur, Fokus, Kontrast), Dark Mode, de/en, Sichtprüfung jeder Seite, Bundle-Grösse. `docs/frontend-plan.md` und `CLAUDE.md` (Stack: „Web Awesome Pro statt Nuxt UI“) nachführen. Merge nach `dev` erst nach Abnahme durch den Auftraggeber.

## Hinweise aus Schritt 1
- **Schriften:** Die App nutzt die Schriften des Themes Matter (Wix Madefor Text, Roboto Mono, Roboto Serif) statt Public Sans; Tailwind und Nuxt UI folgen über `--font-sans` und `--font-mono` in `main.css` den Tokens `--wa-font-family-body/-code`. Das Theme lädt sie per `@import` von `fonts.bunny.net`, was ein Aufruf bei Dritten wäre. Ein PostCSS-Plugin im Hook `vite:extendConfig` (`nuxt.config.ts`) entfernt diesen Import; stattdessen lädt `@nuxt/fonts` (Schlüssel `fonts` in `nuxt.config.ts`, `global: true`) die Dateien beim Build und liefert sie selbst aus (`/_fonts`, rund 800 KB). Der Browser fragt keinen Schriftanbieter an. **In Schritt 7:** `@nuxt/fonts` als eigenes Modul in `modules` eintragen, wenn `@nuxt/ui` entfällt.
- **Dark Mode:** In Schritt 1 spiegelt das Plugin `webawesome.client.ts` den Farbmodus von Nuxt UI (`dark`) auf `wa-dark` / `wa-light`. In Schritt 4 durch den eigenen Umschalter ersetzen (Schritt 7: `useColorMode` entfällt mit Nuxt UI).
- **Typen:** `app/types/webawesome.d.ts` bindet die Vue-Typen ein (die Paket-`exports` geben `dist/types` nicht frei, darum ein Pfad-Verweis). Wegen `isCustomElement` prüft `vue-tsc` Attribute von `wa-*` im Template **nicht**; es hilft nur die Editor-Vervollständigung. Tests und Sichtprüfung sind darum wichtiger.
- **Native-Reset** (`native.css`) ist noch nicht eingebunden, solange Tailwind-Preflight läuft; in Phase B neu entscheiden.
- **Texte der Komponenten:** Deutsch und Englisch sind registriert (`translations/de.js`, `en.js`); die Sprache folgt `<html lang>`.

## Hinweise aus Schritt 2
- **Bibliotheken:** `app/utils/wa-icons.ts` ersetzt die Bibliotheken `default` (Icons der App) und `system` (Icons in den Komponenten: Pfeile, Haken, Schliessen …) durch Duotone Light von der eigenen Auslieferung (`/icons/duotone-light/<name>.svg`, Auflöser ohne Font-Awesome-CDN). `system` kennt zwei Namen anders: `indeterminate` → `minus`, `play-circle` → `circle-play`. Ungültige Namen zeigen `circle-question`. Geprüft per Test.
- **Zweifarbigkeit:** Die Duotone-SVGs tragen `opacity=".4"` auf der zweiten Ebene. Der Mutator markiert beide Ebenen (`data-duotone-primary` / `-secondary`, wie Web Awesome selbst), dann färbt das Stylesheet von `wa-icon` sie im Shadow DOM über die Tokens `--primary-color`, `--secondary-color`, `--primary-opacity`, `--secondary-opacity` (Standard 1 und 0.4, also wie bisher) und `swap-opacity`. Damit sind nun **eigene Farben für die zweite Ebene möglich** (im alten Plan ausgeschlossen). Die Tokens erben nicht, sie müssen am Icon selbst stehen.
- **`ItemIcon`** ist jetzt ein `wa-icon` (`canvas="auto"`, quadratisch wie vorher). Ist ein gewählter Name auf dem Server nicht vorhanden (`wa-error`), zeigt es das Standard-Icon.
- **Vue setzt bei `wa-*` Eigenschaften, keine Attribute.** Tests prüfen darum `element.name` statt `attributes('name')`.
- **ESLint:** `vue/html-self-closing` erlaubt für Komponenten beide Schreibweisen, weil `wa-*` ein schliessendes Tag braucht.
- **Nicht in Schritt 2:** Die `UIcon`/`i-fadl-*`-Stellen und `app/ui-icons.ts` bleiben, solange Nuxt UI läuft (seine Komponenten brauchen sie). Jede Seite stellt ihre Icons auf `wa-icon` um, wenn sie in Schritt 3 bis 6 dran ist; `ui-icons.ts`, die Sammlung `fadl` und `app.config.ts` entfallen in Schritt 7.

## Hinweise zu Schritt 3 (Bausteine)
- **`app/components/StepTimeline.vue`** ersetzt `UTimeline` in `project/Timeline.vue` und `assistant/PlanPreview.vue`. Es gibt in Web Awesome keine Timeline; `wa-stepper` (vertikal, „experimental“ seit 3.14) liefert Marker, Verbindungslinie und Barrierefreiheit. Slots: `indicator` → Slot `icon` des Schritts, `title` → Label, `description` → Slot `description`. `clickable` bleibt aus, weil der Inhalt eigene Knöpfe hat. Der Dateiname ist nicht `Timeline.vue`, weil `<Timeline>` in `project/Timeline.vue` sonst die Komponente selbst meint.
- **Verhalten wie vorher:** Der aktuelle Milestone ist `active`, die davor sind `completed`. In der Assistent-Vorschau gibt es keinen aktuellen Eintrag; `wa-stepper` hebt dann den ersten hervor (kleiner optischer Unterschied zu vorher).
- **Noch nicht im Browser gesehen** (der Prototyp mit Headless-Chrome wurde in dieser Sitzung nicht freigegeben). Zu prüfen: wächst die Linie bei sehr hohen Schritten sauber mit (ToDos aufgeklappt, viele Tasks), Abstand `--gap`, Marker mit `ItemIcon`, Animation `plan-in` auf `wa-step`, schmale Breite, Dark Mode. Zeigt sich ein Problem, gilt der Fallback: eigene CSS-Timeline (vertikale Linie plus Marker) mit derselben Schnittstelle (`items`, `active`, Slots), die Verwender ändern sich dann nicht.
- **ESLint:** `vue/no-deprecated-slot-attribute` ist aus (`wa-*` braucht das native `slot`-Attribut); eigene Klassen `timeline-*` sind für die Tailwind-Regel ausgenommen (bis Phase B).

### Weitere Bausteine
- **Formularfelder** (`app/components/form/`): `FormInput`, `FormTextarea`, `FormSelect` (Prop `items` mit `label`/`value`/`disabled`), `FormCheckbox`, `FormSwitch`, je mit `v-model`, `label`, `hint` und (außer Checkbox/Switch) `error`. Alle weiteren Attribute (`type`, `name`, `autocomplete`, `required`, `autofocus`, `rows` …) gehen an das `wa-*`-Element. **Abweichung vom Plan:** Der Serverfehler wird nicht per `setCustomValidity` gesetzt (das würde das erneute Absenden mit demselben Wert vom Browser blockieren lassen), sondern als Text unter dem Feld anstelle des Hinweises gezeigt (Slot `hint`, `data-form-error`), das Feld trägt `aria-invalid` und `data-invalid` (roter Rahmen, siehe `webawesome.css`). Der Text wird nie als HTML gesetzt (Test). `useServerForm` und `useGraphqlForm` bleiben unverändert: ihr `errors`-Objekt passt direkt auf `:error`.
- **`useToast`** (`app/composables/useToast.ts`) hat dieselbe Aufruf-Form wie der von Nuxt UI (`toast.add({ title, description?, color, duration? })`), baut einen Stapel `wa-toast` mit `wa-toast-item` (Farben: success, error → danger, warning, info/primary → brand, neutral; Icons Duotone Light) und setzt die Texte als Text. Weil der Name mit dem von Nuxt UI kollidiert, trägt `nuxt.config.ts` ihn über den Hook `imports:extend` mit höherer Priorität ein; die 23 Aufrufstellen blieben unverändert. Der Toaster von Nuxt UI (`UApp`) bleibt leer und entfällt in Schritt 7.
- **`AppDialog`** (`wa-dialog`, `v-model:open`, Props `label`, `description`, `lightDismiss`, Slot `footer` rechtsbündig) schließt über Kreuz, Escape und Klick daneben; Ereignisse aus Inhalten (z. B. `wa-hide` eines Selects) werden ignoriert. `ConfirmModal` und `PasswordPromptModal` sind darauf umgestellt (Schnittstelle unverändert, der Passwortdialog sendet mit Enter oder Knopf). Die übrigen Dialoge (`UModal`, `USlideover`) folgen mit ihren Seiten in Schritt 5; ein `AppDrawer` entsteht dann.
- **`EmptyState`** (Icon als Font-Awesome-Name, `title`, `description`, `size` sm/md, `bare` ohne gestrichelten Rahmen, Slot für Aktionen), **`UserAvatar`** (`wa-avatar`: Bild, sonst Initialen aus `utils/names.ts`, sonst Personen-Icon; Größen 2xs bis 3xl) und **`UserLine`** (Avatar, Name, optionale zweite Zeile). Eine Avatar-Gruppe (Projektliste) kommt mit der Seite in Schritt 5.
- **ESLint:** Klassen mit den Präfixen `timeline`, `empty-state`, `user-line` sind für die Tailwind-Regel ausgenommen.

## Hinweise zu Schritt 4 (Layout)
- **`layouts/default.vue`** ist ein `<wa-page mobile-breakpoint="1024px">` (wie vorher ab `lg`): Seitenleiste mit den Slots `navigation-header` (App-Name), `navigation` (Hauptlinks, Projekte, Verwaltung) und `navigation-footer` (Benutzermenü als `wa-dropdown` mit Profil und Abmelden); unter 1024 px wird sie zur Schublade mit dem eingebauten Hamburger-Knopf von `wa-page`. Der Inhalt steht in `<main id="main-content" tabindex="-1">`; der Link „Zum Inhalt springen“ sitzt im Slot `skip-to-content` und fokussiert dieses Element.
- **Weggefallen:** die einklappbare und in der Breite verstellbare Seitenleiste von Nuxt UI (`collapsible`, `resizable`); `wa-page` kennt sie nicht, auf schmalen Schirmen ersetzt die Schublade das Einklappen. Breite fest `--menu-width: 16rem`.
- **`NavList`** (Links mit Icon, aktive Seite: Klasse und `aria-current="page"`, bei `exact` nur der genaue Pfad) ersetzt `UNavigationMenu`. `NuxtLink` setzt `aria-current` nicht selbst, darum rechnet `NavList` es aus der Route.
- **`SidebarProjects`** nutzt `FormSelect` (neu: Prop `searchable`, dann `wa-combobox` mit Suchfeld; wie vorher ab sechs Projekten) und `NavList`.
- **`layouts/auth.vue`** ist ein `wa-page` ohne Navigation mit Kopf (App-Name, `LocaleSwitch`, `ColorModeSwitch`) und einer zentrierten Spalte (max. 24 rem).
- **`LocaleSwitch`** (Auswahl der Sprache, `setLocale`) und **`ColorModeSwitch`** (Knopf hell/dunkel). Letzterer setzt vorerst die Einstellung des Farbmodus-Moduls von Nuxt UI; das Plugin spiegelt sie auf `wa-dark`/`wa-light`. Den eigenen Umschalter ohne Nuxt UI (gemerkte Einstellung, Systemvorgabe) baue ich in Schritt 7 und stelle dann auch die Profilseite (`UColorModeSelect`) um.
- **Übergang:** Die Seiten stecken noch in `UDashboardPanel`/`UDashboardNavbar`. Deren Seitenleisten-Knopf ist in `app.config.ts` ausgeblendet (`dashboardNavbar.slots.toggle`), bis die Seiten in Schritt 5 umgestellt sind.
- **Noch nicht im Browser gesehen** (nur Tests): Abstände, Hamburger-Knopf ohne Kopfzeile, Schublade, Benutzermenü, `wa-page` mit den noch bestehenden `UDashboardPanel`-Seiten.

## Hinweise zu Schritt 5 (Seiten)
- **Umgestellt** sind alle Seiten, die Zusammenstellung der Karten, Dialoge und Formulare sowie die Fehlerseite. In `app/` steht kein `U…`-Bauteil von Nuxt UI mehr außer `UApp` (`app.vue`), dem Rich-Text-Editor (`rich-text/Editor.vue`, `LinkPopover.vue`, `View.vue`) und dem Icon-Picker (`icon/Picker.vue`); diese folgen in Schritt 6.
- **Neue Bausteine:** `PageFrame` (Titelzeile und Inhalt, Prop `fill` für Seiten mit voller Höhe), `ActionMenu` (`wa-dropdown` aus Daten, auch mit Gruppen), `AppDrawer` (`wa-drawer`), `AppCallout`, `UserAvatarGroup`, `ColorModeSelect`, `FormOtp` (`wa-otp-input`), `FormField` (Beschriftung, Hinweis und Meldung für Bedienelemente, die kein `wa-*`-Feld sind). `FormInput` hat einen Slot `start` für ein Icon.
- **Abweichungen vom Plan:** (1) Der 2FA-Code nutzt `wa-otp-input` (steht in Web Awesome als eigenes Feld bereit) statt `wa-input` mit numerischer Eingabe; `autosubmit` ersetzt `@complete`. (2) Die aufklappbaren ToDos in der Zeitachse und in der Assistent-Vorschau sind eigene Auf-/Zuklapp-Knöpfe statt `wa-details`, weil das Aktionsmenü neben dem Knopf sitzt und kein Knopf in einem `summary` stecken darf. (3) Die Teilung im Assistenten (`wa-split-panel`) kennt kein „einklappbar“: ohne Entwurf steht die Position fest bei 95 % (schmaler Streifen für die Vorschau), mit Entwurf gilt der gemerkte Anteil des Chats (`localStorage`, Schlüssel `assistant-splitter`); der Übergang ist nicht animiert. (4) Die Fehlerseite setzt das Theme selbst, weil `app.vue` dort nicht gerendert wird.
- **Seitenaufbau:** Layout über die Hilfsklassen `wa-stack`, `wa-cluster`, `wa-split`, `wa-gap-*`, `wa-heading-*`, `wa-body-*`, `wa-list-plain` und eigene `<style scoped>`-Blöcke mit Tokens. Das ESLint-Plugin für Tailwind kennt diese Klassen nicht; `eslint.config.mjs` führt dafür eine Ausnahmeliste (`^wa-` und die Präfixe der eigenen Klassen), die in Phase B mit dem Plugin entfällt.
- **Tests:** Neue Tests für `ActionMenu`, `FormField`, `UserAvatarGroup`, `PageFrame`, die Anmelde-Formulare (mit nachgebildetem `$backend`) und angepasste Zeitachse. Merke: `wa-button` ist kein `<button>` (Tests suchen `wa-button`), und Vue setzt bei `wa-*` Eigenschaften, keine Attribute (`name`, `value`, `label` mit `element.name` prüfen).
- **Lehre:** `npm run build` fängt Fehler, die Lint, Typecheck und Tests nicht sehen (`v-model="x as string"` ist kein gültiger Ausdruck). Vor jedem Commit alle vier laufen lassen.
- **Noch nicht im Browser gesehen:** alle Seiten dieses Schritts. Besonders prüfen: Abstände und Höhen im `wa-page` (Kanban, Assistent), die geschachtelte Bestätigung in der Schublade der Task-Details, Karten mit Kopf- und Fußzeile, das Eingabefeld mit Knopf daneben (`margin-block-start: 1.6em` ist geschätzt), Formulare mit Pflichtfeldern, Dark Mode.

## Hinweise zu Schritt 6 (Rich-Text und Icon-Picker)
- **Editor ohne `UEditor`:** `rich-text/Editor.vue` nutzt `useEditor` und `EditorContent` von `@tiptap/vue-3`. `richTextExtensions()` (`utils/rich-text.ts`) liefert jetzt alle Erweiterungen selbst (Dokument, Absatz, Text, Zeilenumbruch, Rückgängig/Wiederholen, dazu die erlaubten Elemente); die Whitelist-Tests bauen den Editor nur noch daraus. Der Platzhalter kommt aus `Placeholder` (`@tiptap/extensions`). Der Wert `null` leert den Editor (wie vorher nach dem Absenden eines Kommentars).
- **Werkzeugleiste** aus `wa-button` (Gruppen: Rückgängig/Wiederholen, Überschriften, fett/kursiv, Listen/Zitat, Link; `compact` für Kommentare nur fett/kursiv, Listen, Link). Der Zustand `aria-pressed` kommt aus `editor.isActive(...)`; die Namen stehen als `aria-label` und `title`. `LinkPopover` ist ein `wa-popover` mit `FormInput`; Prüfung und Normalisierung der Adresse sind unverändert.
- **`View.vue`** zeigt den Text mit einem nicht bearbeitbaren TipTap-Editor (kein `v-html`). Die Darstellung der erlaubten Elemente steht in `app/assets/css/rich-text.css` (Klasse `rich-text`, Tokens von Web Awesome); sie ersetzt die Prose-Stile von Nuxt UI.
- **Icon-Picker** ist ein Dialog mit Suchfeld (`AppDialog`, `FormInput`) und einem Raster aus `wa-button`; jeder Eintrag hat ein `wa-tooltip`. Ein `wa-combobox` passt für ein Raster aus Icons nicht. Katalog und Suche (`utils/icon-search.ts`) sind unverändert.
- **Zu prüfen im Browser:** Aussehen und Rahmen des Editors (Fokus, Mindesthöhe), Platzhalter, Link-Popover (Fokus, Schließen mit Escape), Raster des Icon-Pickers (viele Tooltips).

## Hinweise zu Schritt 7 und 8 (Nuxt UI und Tailwind entfernt)
- **Zusammengelegt:** Tailwind lief nur über das Modul von Nuxt UI. Nach Schritt 6 nutzte keine Vorlage mehr Tailwind-Klassen (geprüft per Skript: alle Klassen sind `wa-*` oder in eigenem CSS definiert); übrig waren `plan-in`/`plan-timeline` und das Preflight. Darum sind beide Schritte in einem Zug erledigt.
- **Entfernt:** `@nuxt/ui`, `tailwindcss`, `eslint-plugin-better-tailwindcss` (samt Ausnahmeliste in `eslint.config.mjs`), `app.config.ts`, `app/ui-icons.ts`, `assets/css/main.css`, `UApp` in `app.vue`, die Icon-Sammlung `fadl` (Nuxt Icon) und der Hook, der `useToast` den Vorrang gab.
- **Neu:** `@nuxt/fonts` als eigenes Modul (die Schriften von Matter, wie bisher selbst ausgeliefert). `assets/css/animations.css` mit `plan-in`/`plan-timeline` als normales CSS (`prefers-reduced-motion` bleibt). Statt Preflight lädt `webawesome.css` die **Native Styles** von Web Awesome (`native.css`, Ebene `wa-native`, also schwächer als jedes eigene CSS).
- **Farbmodus ohne Nuxt UI:** `useColorScheme()` (System, Hell, Dunkel; gemerkt unter `color-mode`, die frühere Einstellung `nuxt-color-mode` wird einmal übernommen). Das Plugin setzt `wa-dark`/`wa-light`, ein kleines Skript im Kopf der Seite (`nuxt.config.ts`, `app.head.script`) schon vor dem Start, damit eine dunkle Seite nicht weiß aufblitzt. „System“ folgt einer Änderung am Gerät auch bei offener App.
- **Typen:** `$backend` ist jetzt als einfaches `$Fetch` von ofetch getippt. Ohne Nuxt UI lief TypeScript bei der Ableitung der Nitro-Routen in einen Stapelüberlauf (TS2321); das Backend ist ohnehin ein anderer Ursprung.
- **Größen:** `size="small|medium|large"` ist in Web Awesome 3.14 veraltet (Warnung im Dev-Server); überall auf `s|m|l` umgestellt.
- **Tests:** Zeitlimit der Nuxt-Tests auf 15 s (der erste Mount startet Nuxt und die Komponenten; auf einem ausgelasteten Rechner, z. B. mit laufendem Dev-Server, dauert das über 5 s).
- **Bundle:** `_nuxt` rund 2,1 MB statt 3,5 MB vorher.

## Hinweise zu Schritt 9 (Abschluss)
- **Erledigt:**
  - Statische Prüfung aller Vorlagen: Jeder Knopf, der nur ein Icon zeigt, hat einen Namen (`aria-label`). Jedes Formularfeld hat eine echte Beschriftung; wo sie nicht zu sehen sein soll (Suche, Filter, Projektwahl, Chat, Token), ist sie nur optisch versteckt (`hideLabel`, Klasse `wa-visually-hidden-label`), statt `aria-label` am Host, das Web Awesome nicht sicher an das innere Feld weitergibt.
  - Bilder haben `alt`, Icons ohne Beschriftung sind für Screenreader ausgeblendet (Standard von `wa-icon`).
  - de/en: Beide Sprachdateien haben dieselben Schlüssel, jeder verwendete Schlüssel existiert. Zwei nicht mehr verwendete Texte sind entfernt.
  - Dokumentation: `docs/frontend-plan.md`, `CLAUDE.md` (Stack, Regeln, Verweis auf diesen Plan) und `frontend/README.md` sind nachgeführt.
  - Bundle: `_nuxt` rund 2,1 MB (vorher 3,5 MB), Schriften rund 800 KB in `/_fonts`.
- **Browserprüfung (Stand 2026-11-12, lokal, Dashboard, Kanban, Projektseiten, Profil, Admin, Label-Sets, Systemstatus, Konto absichern):**
  - Hell und dunkel: alle genannten Seiten sichtbar in Ordnung.
  - Kontrast (Messung per Skript, nur Text im Light-DOM): hell mindestens 5,59:1, dunkel mindestens 5,70:1. Texte in Shadow-DOMs von `wa-*` sind nicht erfasst, dafür bleibt Sichtprüfung nötig. Der Knopf „Passkey einrichten“ im Dunkelmodus kommt rechnerisch auf rund 7,6:1, wirkt im Zoom aber heller als erwartet.
  - Fokus: Fokusring sichtbar. Im Dialog „Task anlegen“ bleibt der Fokus im Dialog, nach Escape kehrt er auf den Auslöser zurück.
  - Englisch: Dashboard, Kanban und Profil ohne deutsche Oberflächentexte. Projektnamen und Beschreibungen bleiben deutsch, weil es Daten sind.
  - **Behoben:** Auf `/setup-security` liefen die Labels der Knöpfe „Passkey einrichten (empfohlen)“, „Authenticator-App einrichten“ (und in Englisch „Set up a passkey (recommended)“) über den Rand. Die Knöpfe haben jetzt `class="setup-choice"`, das Label umbricht (`::part(label)`, `white-space: normal`).
  - Telefonbreite (388 px, per Iframe auf derselben Herkunft gemessen): Login, Passwort vergessen, Registrierung, Passwort zurücksetzen, Einladung, Wartesseite und Bestätigung ohne horizontalen Überlauf. Login visuell geprüft. Hinweis: Im Browser überlappt das Label „E-Mail“ den Autofill-Text; das ist der Browser, nicht die App.
  - **Offen:** Dashboard, Kanban und Seitenleiste als Schublade bei Telefonbreite (brauchen eine Anmeldung; die Testkonten sind gelöscht), vollständige Tab-Reihenfolge, Admin- und System-Seiten auf Englisch. Die Punkte aus den Hinweisen zu Schritt 3 bis 7 (Linie der Zeitachse, Abstände neben Eingabefeldern, Dialog in der Schublade, Native Styles) sind weiterhin offen.
- **Merge nach `dev`** erst nach Abnahme durch den Auftraggeber.

## Stand
- [x] Vorbereitung (Paket 3.14.0 installiert, Skills in `frontend/.claude/skills/`)
- [x] 1. Einbindung (Hinweise dazu unten)
- [x] 2. Icons (Hinweise dazu unten)
- [x] 3. Bausteine (Hinweise dazu unten)
- [x] 4. Layout (Hinweise dazu unten)
- [x] 5. Seiten (Hinweise dazu unten)
- [x] 6. Rich-Text und Icon-Picker (Hinweise dazu unten)
- [x] 7. Nuxt UI entfernen (zusammen mit 8, Hinweise unten)
- [x] 8. Tailwind entfernen (zusammen mit 7)
- [ ] 9. Abschluss (erledigt bis auf die Prüfung im Browser und die Abnahme, siehe unten)
