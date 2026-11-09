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
| UTimeline | eigene Komponente `Timeline` | Drag-and-drop bleibt |
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
- **Icons/Assets:** `setBasePath` und die Icon-Libraries folgen in Schritt 2. Bis dahin laden Komponenten mit eingebauten Icons (z. B. Schliessen im Dialog, Pfeil im Select) ihre Icons noch nicht korrekt.
- **Texte der Komponenten:** Deutsch und Englisch sind registriert (`translations/de.js`, `en.js`); die Sprache folgt `<html lang>`.

## Stand
- [x] Vorbereitung (Paket 3.14.0 installiert, Skills in `frontend/.claude/skills/`)
- [x] 1. Einbindung (Hinweise dazu unten)
- [ ] 2. Icons
- [ ] 3. Bausteine
- [ ] 4. Layout
- [ ] 5. Seiten
- [ ] 6. Rich-Text und Icon-Picker
- [ ] 7. Nuxt UI entfernen
- [ ] 8. Tailwind entfernen
- [ ] 9. Abschluss
