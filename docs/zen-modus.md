# Zen-Modus (umschaltbare Darstellung)

Stand: 2026-11-18, Fassung 2 (Fassung 1 vom 2026-11-17 an den Stand nach M6 angepasst). Rolle: Plan der Planung (Stuart), Umsetzung Bob. Auftrag in `next-work/11-zen-modus.md`.

## 1. Entscheidungen (Oliver)

**2026-11-17:**
- **Zwei Stufen:** Stufe 1 „Ruhig“ entschlackt die ganze App, Stufe 2 „Fokus“ zeigt ein einzelnes Element im Vollbild.
- **Speicherung pro Gerät** (Cookie), keine Einstellung am Konto, kein Backend.
- **Alle Seiten** in der ersten Version.

**2026-11-18:**
- **Aktueller Milestone** (Zeitachse in „Ruhig“): der erste nicht erledigte in der Reihenfolge des Projekts; sind alle erledigt, der letzte. Die übrigen eingeklappt darunter.
- **Ohne Dokumente:** Die Dokumentenverwaltung gibt es noch nicht. Zen gilt jetzt für alle vorhandenen Seiten, „Fokus“ für Task und ToDo. Dokumente bekommen Zen mit ihrem eigenen Auftrag (die Mechanik hier muss das ohne Umbau erlauben).

## 2. Grundsatz

Zen ist eine Darstellungsschicht, keine zweite App.

- Gleiche Routen, Komponenten, Abfragen und Rechte (`viewerRights`).
- Zen blendet aus und ordnet um, schaltet aber nichts ab. Immer sichtbar bleiben:
  - Validierung, Warnungen, Fehlermeldungen und 2FA-Hinweise
  - der Hinweis „Das Projekt wurde inzwischen geändert“ (S3f)
  - die Leiste „Archiviert, nur lesbar“
  - Meldungen mit „Rückgängig“
- **Erreichbarkeitsregel:** Jede Funktion, die Zen ausblendet, bleibt über die Befehlspalette oder ein „…“-Menü erreichbar. Diese Regel ist Abnahmekriterium jeder Seite.

## 3. Die drei Zustände

| Zustand | Wert | Rahmen | Inhalt |
|---|---|---|---|
| Normal | `full` | Seitenmenü, Kopfleiste, Brotkrumen | wie heute |
| Zen 1 „Ruhig“ | `calm` | kein Seitenmenü, keine Kopfleiste; Eck-Knopf und Befehlspalette | eine Spalte, begrenzte Breite, Nebeninfos ausgeblendet |
| Zen 2 „Fokus“ | `focus` | nur „Zurück“ und Sichern-Status | ein Element (Task, ToDo) im Vollbild |

- `calm` ist dauerhaft: Der Zustand bleibt über Seitenwechsel und Neuladen erhalten.
- `focus` ist an ein Element gebunden und endet beim Verlassen. Danach gilt wieder der vorherige Zustand.
- Technische Werte sind klein und englisch, die Beschriftungen kommen aus `i18n/locales/`.

## 4. Architektur (Frontend)

### 4.1 Zustand

- **Einzige Quelle:** `composables/useViewMode.ts` liefert `mode`, `setMode()`, `toggleCalm()`, `enterFocus(target)` und `leaveFocus()`.
- **Dauerhaft:** Cookie `view_mode` mit `full` oder `calm` (`useCookie`, ein Jahr, `SameSite=Lax`). Ein Cookie statt `localStorage`, damit Nuxt schon beim SSR richtig rendert und nichts aufblitzt.
- **`focus`** steht nicht im Cookie, sondern in der URL (`?focus=1` am Element). Zurück-Taste, Neuladen und geteilte Links funktionieren damit.
- **Plugin:** setzt `data-view="full|calm|focus"` auf `<html>`, auf Server und Client gleich, ohne Hydration-Abweichung.

### 4.2 Ausblenden per Konvention, nicht per `v-if`

- **Markierungen:**
  - `data-zen="hide"`: in `calm` und `focus` weg.
  - `data-zen="hover"`: erst bei Fokus oder Hover der Zeile sichtbar.
- **Stylesheet:** Eine Datei `assets/css/zen.css` enthält alle Regeln über `[data-view]` und `--wa-*`. Keine verstreuten Abfragen des Modus in den Komponenten.
- **Touch:** `data-zen="hover"` gilt nur bei `@media (hover: hover)`. Auf Touch-Geräten kommen diese Elemente ins „…“-Menü (`ActionMenu`).
- **Web Awesome:** nur Host-Elemente und eigene Wrapper markieren, nicht in den Shadow DOM greifen. `display: none` am Host, damit Screenreader und Tab-Reihenfolge stimmen.
- **Ausnahme `v-if`:** nur für Teile mit teuren Abfragen, die dann gar nicht erst laden. Jede Ausnahme im Commit begründen.

### 4.3 Rahmen

- `layouts/default.vue` rendert je nach Zustand Seitenmenü und Kopfleiste oder den Zen-Rahmen `components/zen/ZenShell.vue`: Eck-Knopf, Projektname, Sichern-Status.
- Der Eck-Knopf öffnet die Befehlspalette. Ein Eintrag darin heißt „Zen verlassen“.
- Telefonbreite: Der Eck-Knopf überdeckt keine Eingabefelder, keine Sichern-Leiste und keine Meldungen.

### 4.4 Befehlspalette (Voraussetzung, nicht Zugabe)

- **Baustein:** `components/command/CommandPalette.vue` auf Basis `AppDialog`/`wa-dialog`, mit Suchfeld und Ergebnisliste. Bedienung mit Pfeilen, Enter und Esc. Die Suche ignoriert Akzente (`utils/text-search.ts`).
- **Quellen:**
  - Seiten aus `utils/route-rights.ts` und dem Menü (RF5)
  - Projekte (`SidebarProjectList`)
  - Aktionen der aktuellen Seite
- **Aktionen:** Seiten melden sie über `useCommands([{ id, label, icon, right, run }])` an. Einträge ohne Recht erscheinen nicht. Beim Verlassen der Seite verschwinden sie.
- Die Palette gibt es auch im Normalmodus.
- **Kürzel:**
  - `Ctrl/Cmd+K`: Palette
  - `Ctrl/Cmd+.`: „Ruhig“ umschalten
  - `Esc`: verlässt „Fokus“
- Die Kürzel greifen nicht, solange ein Dialog oder Drawer offen ist oder ein Eingabefeld bzw. der TipTap-Editor das Kürzel selbst braucht.

### 4.5 Stufe 2 „Fokus“

- **Ziele:** Task und ToDo, lesen und bearbeiten. Die Mechanik ist allgemein (`enterFocus(target)` mit Art und ID), damit Dokumente später ohne Umbau dazukommen.
- **Einstieg:** Knopf „Fokus“ im Task-Detail und im ToDo, dazu die Palette.
- **Darstellung:** das Element allein, zentriert, in Lesebreite. Die Kommentare sind unter dem Inhalt eingeklappt.
- Es gibt keinen eigenen Datenweg: dieselbe Detail-Komponente, nur ein anderer Rahmen.
- Bei ungesicherten Änderungen kommt dieselbe Warnung beim Verlassen wie heute.

## 5. Was je Seitentyp ausgeblendet wird (Leitlinie, die genaue Liste entsteht in Z1)

| Seitentyp | Bleibt in `calm` | Geht ins „…“-Menü oder verschwindet |
|---|---|---|
| Dashboard, Meine Tasks | eigene offene Tasks: Titel, Projekt, Fälligkeit, Status | Zähler, Diagramme, Schalter „Ideen anzeigen“ (ins Menü) |
| Projektliste | Name, Status, eigener Anteil offener Tasks | Zähler, Avatare, Filterleiste (eingeklappt), Bereich „Archiv“ |
| Projekt / Zeitachse | aktueller Milestone (§1), seine ToDos und offenen Tasks: Titel, Status, zuständige Person | übrige Milestones eingeklappt, Labels, Commits, Release-Angaben, „Mit dem Assistenten ergänzen“ und „Milestone anlegen“ ins Menü |
| Kanban | Spalten und Karten mit Titel und zuständiger Person | Labels, Fälligkeit (`hover`), Schalter „Ideen und Geplantes“ ins Menü |
| Task / ToDo | Titel, Status, Beschreibung, Zuweisung | Verlauf, technische IDs, Commit, Nebenaktionen |
| Ideen | Titel, Status | Projekt, Umfang des Entwurfs (`hover`), Filter eingeklappt |
| KI-Assistent | Chat und Vorschau | Erklärtexte, Projektzeile |
| Einstellungen, Vorlagen, Mitglieder, Papierkorb, Admin | Inhalt in einer Spalte, Filter eingeklappt | Hilfetexte, Zähler, Badges „mitgeliefert“ |
| Rechte-Übersicht (Matrix) | unverändert, nur ohne Rahmen | nichts, eine Matrix lässt sich nicht sinnvoll kürzen |
| Anmelden, Registrieren, 2FA, Einladung | unverändert (kein Zen ohne Anmeldung) | — |

## 6. Risiken

1. **Umfang:** Je Seitengruppe ein Commit, damit die Abnahme prüfbar bleibt.
2. **Zeitachse:** `Timeline.vue` hat 713 Zeilen. Die Sicht „aktueller Milestone“ ist der Anlass, sie in Teilkomponenten zu zerlegen (Milestone, ToDo-Zeile, Task-Zeile). Das Verhalten im Normalmodus bleibt unverändert, die bestehenden Tests müssen grün bleiben.
3. **Barrierefreiheit:**
   - Moduswechsel per `aria-live` ansagen.
   - Der Fokus bleibt auf dem auslösenden Element.
   - Ausgeblendetes darf nicht in der Tab-Reihenfolge bleiben.
4. **SSR:** Das Cookie muss beim ersten Server-Render gelesen werden. Neuladen in `calm` darf keine Hydration-Warnung erzeugen.

## 7. Tickets für Bob

Die Reihenfolge ist verbindlich. Je Schritt ein Commit, `lint`, `typecheck` und Vitest grün, den Stand in `docs/umsetzung.md` nachführen.

**Z1 Erhebung (kein Verhalten ändern)**
- Alle Seiten und ihre Aktionen auflisten und je Element entscheiden: bleibt, `hide`, `hover` oder „…“-Menü.
- Das Ergebnis als Tabelle in §5 ergänzen und auf Freigabe durch Oliver warten. Z4 beginnt erst danach; Z2 und Z3 dürfen schon laufen.

**Z2 Zustand und Rahmen**
- **Bauen:**
  - `useViewMode`, Cookie und Plugin für `data-view`
  - `zen.css` mit den Grundregeln und `ZenShell`
  - Umschalter im Benutzermenü, Kürzel, i18n DE/EN
- **Abnahme:**
  - Neuladen in `calm` zeigt serverseitig sofort den Zen-Rahmen, ohne Aufblitzen und ohne Hydration-Warnung.
  - Vitest für `useViewMode`: Übergänge und Rückkehr aus `focus`.

**Z3 Befehlspalette**
- **Bauen:** `CommandPalette` und `useCommands`, mit Seiten, Projekten und Seitenaktionen als Quellen und Rechteprüfung über `viewerRights`.
- **Abnahme:**
  - Einträge ohne Recht fehlen (Test je Systemrolle).
  - Vollständig per Tastatur bedienbar, auch im Normalmodus.

**Z4 Seiten markieren, Gruppe A: Dashboard, Meine Tasks, Projektliste, Projekt/Zeitachse, Kanban, Task, ToDo**
- **Bauen:**
  - Markierungen nach Z1, die „…“-Menüs ergänzen
  - Zeitachse zerlegen, Sicht „aktueller Milestone“
- **Abnahme:** Erreichbarkeitsregel je Seite (Liste aus Z1 abhaken), Telefonbreite ohne seitliches Scrollen.

**Z5 Seiten markieren, Gruppe B: Ideen, KI-Assistent, Einstellungen, Mitglieder, Vorlagen, Papierkorb, Admin, Profil**
- Wie Z4. Die Rechte-Matrix nur ohne Rahmen.

**Z6 Stufe 2 „Fokus“**
- **Bauen:** `?focus=1` an Task und ToDo, Einstieg über Knopf und Palette. `Esc` und „Zurück“ stellen den vorherigen Zustand her.
- **Abnahme:**
  - Neuladen und geteilter Link öffnen den Fokus.
  - Bei ungesicherten Änderungen kommt die Warnung.
  - `Esc` im Editor schließt zuerst die Editor-Menüs, erst dann den Fokus.

**Z7 Abschluss**
- Browser-Prüfung: Desktop und Telefonbreite, hell und dunkel, DE und EN, ein Durchgang nur mit der Tastatur.
- Dokumentation nachführen: `docs/README.md`, `docs/claude-desktop-kontext.md`.
