# Zen-Modus (umschaltbare Darstellung)

Stand: 2026-11-18, Fassung 2 (Fassung 1 vom 2026-11-17 an den Stand nach M6 angepasst). Rolle: Plan der Planung (Stuart), Umsetzung Bob. Auftrag in `next-work/11-zen-modus.md`.

## 1. Entscheidungen (Oliver)

**2026-11-17:**
- **Zwei Stufen:** Stufe 1 „Ruhig“ entschlackt die ganze App, Stufe 2 „Fokus“ zeigt ein einzelnes Element im Vollbild.
- **Speicherung pro Gerät** (Cookie), keine Einstellung am Konto, kein Backend.
- **Alle Seiten** in der ersten Version.

**2026-11-18:**
- **Aktueller Milestone** (Zeitachse in „Ruhig“): der erste nicht erledigte in der Reihenfolge des Projekts; sind alle erledigt, der letzte. Die übrigen eingeklappt darunter.
- **Farbschema „Steingarten“** mit den Reglern Helligkeit und Kontrast und den Vorschlägen Leinen, Sand, Stein und Leder, genau wie in der Vorschau; pro Gerät gespeichert; aufgerufen über das Slide-In „Stimmung“ mit transparentem Hintergrund (§4.6).
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

### 4.6 Farbschema „Steingarten“ und Slide-In „Stimmung“ (Oliver, 2026-11-18)

Zen (`calm` und `focus`) hat ein eigenes Farbschema mit den Farben Leinen, Sand, Stein, Taupe und Leder. Die Person stellt es mit zwei Reglern ein. Vorbild ist die interaktive Vorschau der Planung; Regler und Vorschläge werden **genau so** übernommen.

**Farben aus zwei Werten:**
- **Reine Funktion** `utils/steingarten.ts`: `palette(brightness, contrast)` (beide 0–100) liefert `bg`, `surface`, `border`, `text`, `textMuted`, `accent`, `onAccent`, `warn`. Sie läuft auf Server und Client gleich, ohne DOM.
- **Rampe**, gleichmäßig zwischen diesen Stützpunkten (sRGB):

  | Stelle | Farbe | Wert |
  |---|---|---|
  | 0 | Leinen | `#F4EEE3` |
  | 25 | Sand | `#E2D6C3` |
  | 50 | Stein | `#A39A8C` |
  | 75 | Taupe | `#5E5348` |
  | 100 | Leder | `#2B1E16` |
- `bg` = Rampe(Helligkeit). Über 50 kippt das Schema auf dunklen Grund mit heller Schrift.
- `text` = Rampe(Helligkeit ± (0,42 + 0,58 × Kontrast)).
- `textMuted` und `border` sind Mischungen von `bg` und `text`, abhängig vom Kontrast.
- `accent` ist Moosgrün: hell `#55633C` bis `#343E24`, dunkel `#B3C08F`. `warn` ist Ocker: hell `#966226`, dunkel `#D6AA6E`.
- Die genauen Formeln stehen in der Vorschau (Planung) und werden in Vitest festgehalten.
- **Mindestkontrast:** Die Funktion sorgt immer für Text/Hintergrund ≥ 4,5:1, Nebentext ≥ 3:1 und Knopftext ≥ 4,5:1. Wird ein Wert unterschritten, rückt sie Text bzw. Nebentext weiter vom Hintergrund weg; die Regler bleiben dabei frei. Vitest prüft das über alle Paare 0–100 in Schritten von 5.

**Wirkung in der Oberfläche:**
- Ein Plugin setzt unter `[data-view="calm"]` und `[data-view="focus"]` die Variablen `--zen-*` als Inline-Style auf `<html>`.
- `zen.css` bildet sie auf die `--wa-*`-Tokens von Web Awesome ab (Flächen, Text, Rahmen, Markenfarbe = `accent`, Warnung = `warn`). Komponenten bekommen keine eigenen Farben.
- Im Normalmodus gilt das bisherige Schema unverändert, hell und dunkel nach `ColorModeSwitch`.
- Statusfarben in Zen: Erledigt in Moos, In Arbeit und Hinweise in Ocker, Offen neutral. Gefahr (Löschen, Fehler) bleibt rot und lesbar ≥ 4,5:1 auf `bg`.

**Speichern pro Gerät:**
- Cookies `zen_brightness` und `zen_contrast` (0–100, ganze Zahlen, ein Jahr, `SameSite=Lax`), wie `view_mode`, damit das SSR sofort richtig rendert.
- Ungültige Werte führen zum Standard.
- Standard ist Helligkeit 12 und Kontrast 55 (Leinen).

**Slide-In „Stimmung“:**
- **Aufbau:** `components/zen/MoodDrawer.vue` auf Basis `AppDrawer`/`wa-drawer`, von rechts (auf dem Telefon von unten), Titel „Stimmung“ (EN „Mood“).
- **Transparenter Hintergrund:** Der Drawer dunkelt die Seite nicht ab, die Seite bleibt voll sichtbar und ändert sich live mit. Die Fläche des Drawers ist in `--zen-surface` mit 85 % Deckkraft, ohne Unschärfe und ohne Schatten (nur Rahmen `--zen-border`). Klick neben den Drawer und `Esc` schließen ihn.
- **Inhalt**, genau wie in der Vorschau:
  1. Regler „Helligkeit“ mit Anzeige des Tons und der Prozent (z. B. „Leinen · 12 %“).
  2. Regler „Kontrast“ mit Prozent.
  3. Knöpfe „Leinen“ (6/50), „Sand“ (30/45), „Stein“ (52/70), „Leder“ (88/55).
  4. Farbleiste mit 9 Feldern, die aktuelle Stelle markiert.
  5. Klein darunter: Kontrast Text/Fläche als Zahl mit AA/AAA.
  6. Knopf „Zurücksetzen“ (auf den Standard).
- **Speichern:** Änderungen wirken sofort. Gespeichert wird beim Loslassen des Reglers bzw. beim Klick, mit 300 ms Verzögerung, ohne eigenen Speichern-Knopf.
- **Aufruf:**
  - Eintrag „Stimmung“ im Menü des Eck-Knopfs (`ZenShell`).
  - Befehl „Stimmung“ in der Palette.
  - Im Normalmodus über den Befehl oder das Benutzermenü (neben „Zen“). Ist Zen aus, schaltet „Stimmung“ zuerst auf `calm`, damit die Wirkung sichtbar ist.
- **Tastatur:** Regler mit Pfeiltasten (1) und Bild auf/ab (10). Der Fokus springt beim Öffnen auf den ersten Regler und beim Schließen zurück zum Auslöser. Werte werden per `aria-valuetext` angesagt („Sand, 30 Prozent“).

## 5. Was je Seite ausgeblendet wird (Z1, Erhebung vom 2026-11-18, wartet auf Freigabe)

Entscheidungen je Element: **bleibt** (in `calm` sichtbar), **hide** (`data-zen="hide"`, in `calm` und `focus` weg), **hover** (`data-zen="hover"`, nur bei Hover oder Fokus, auf Touch im „…“-Menü), **Menü** (Aktion wandert ins „…“-Menü der Seite und in die Befehlspalette). „Palette“ heißt: als Aktion der Seite über `useCommands` erreichbar. Alles, was nicht aufgeführt ist, bleibt. Immer sichtbar (§2): Fehler, Validierung, Archiv-Leiste, Hinweis „inzwischen geändert“, Meldungen mit „Rückgängig“.

### Gruppe A (Z4)

| Seite | Bleibt | hide | hover | Menü und Palette |
|---|---|---|---|---|
| Dashboard `/` | „Meine offenen Tasks“ (Titel, Projekt, Fälligkeit, Status) | Untertitel, Karte „Projekte“ mit Fortschrittsbalken, Karte „Anstehende ToDos“, Link „Alle Projekte“ | Fortschrittswerte | „Alle Projekte“, „Meine Tasks“ als Palette-Seiten |
| Meine Tasks `/my-tasks` | Liste: Titel, Projekt, Fälligkeit, Status | Labels, Spalte „ToDo-Status“ | Labels | Schalter „Ideen anzeigen“ ins Menü |
| Projektliste `/projects` | Name, Status, eigener Anteil offener Tasks | Avatare, Zähler, Bereich „Archiv“ (eingeklappt, im Menü „Archiv zeigen“) | Fortschritt | „Projekt anlegen“ ins Menü |
| Projektrahmen `/projects/:id` | Titel, Archiv-Leiste, Hinweis „inzwischen geändert“ | Reiter (Zeitachse, ToDos, Kanban, Mitglieder, Einstellungen, Papierkorb) | — | Reiter als Palette-Seiten des Projekts; „Mit dem Assistenten ergänzen“ und „Milestone anlegen“ ins Menü |
| Zeitachse `/projects/:id` | aktueller Milestone (erster nicht erledigter, sonst der letzte; §1), seine ToDos und offenen Tasks: Titel, Status, Person | Labels, Commits, Release-Angaben, Fälligkeit der ToDos | Fälligkeit der Tasks, Zeilenmenü | übrige Milestones eingeklappt unter „Weitere Milestones“; „Nur Milestones“, „Task anlegen“, „ToDo anlegen“ ins Menü |
| ToDo-Liste `/projects/:id/todos` | ToDos mit Titel, Status, Milestone | Zähler der Tasks, Spalte „Aktionen“ | Fälligkeit, Zeilenmenü | „ToDo anlegen“ ins Menü |
| Kanban `/projects/:id/kanban` | Spalten, Karten mit Titel und Person | Labels auf den Karten, Spaltenzähler | Fälligkeit | Labelfilter und Schalter „Ideen und Geplantes“ ins Menü; „Task anlegen“ ins Menü |
| Task-Detail (Drawer) | Titel, Status, Beschreibung, Zuweisung | technische IDs, Commit, Labels, Verlauf | Fälligkeit | „Fokus“, Nebenaktionen ins „…“; Kommentare eingeklappt |
| ToDo-Detail | Titel, Status, Beschreibung, Tasks | Fälligkeit, Icon-Wahl | — | „Fokus“, „Task anlegen“ ins „…“ |

### Gruppe B (Z5)

| Seite | Bleibt | hide | hover | Menü und Palette |
|---|---|---|---|---|
| Ideen `/ideas` | Titel, Status, „Mit dem Assistenten planen“ | Projektzeile, Datum | Umfang des Entwurfs | Filter eingeklappt, „Neue Idee“, Bearbeiten, Verwerfen, Löschen ins Menü |
| KI-Assistent `/assistant` | Chat, Knöpfe, Vorschau | Erklärtext, Zeile „Ergänzt wird“ | — | „Neu beginnen“, „Anderes Projekt“ |
| Mitglieder | Name, Rolle | Avatare-Zusätze, Hilfetext, Suchhinweis | — | „Mitglied hinzufügen“ ins Menü |
| Einstellungen (Projekt) | Karten in einer Spalte | Hilfetexte, Intro der Karten (nicht Hinweise zu Datenschutz im KI-Kontext, Zähler 20 000) | — | Karten bleiben; Archivieren und Löschen bleiben erreichbar |
| Vorlagen `/templates`, Label-Sets | Namen, Inhalt | Badge „mitgeliefert“, Zähler `projectCount` | — | „Neu“, „Duplizieren“ ins Menü |
| Papierkorb (global und Projekt) | Einträge mit Titel und Art | Zähler „mit 3 ToDos“, Löscher | Wer und wann | „Wiederherstellen“ bleibt sichtbar, „Endgültig löschen“ ins Menü |
| Admin: Benutzer, Rollen, App-Einstellungen | Inhalt in einer Spalte | Hilfetexte, Badges, Zähler | — | Filter eingeklappt, Anlegen ins Menü |
| System: Datenbank, Status, Version | Inhalt | Erklärtexte | — | — |
| Rechte-Übersicht `/system/rechte` | Matrix unverändert | nichts | — | nur ohne Rahmen |
| Profil | Name, Sprache, Sicherheit (2FA, Passkeys) | Hilfetexte, Avatar-Hinweise | — | Sicherheitshinweise und 2FA-Pflicht bleiben immer sichtbar |
| Anmelden, Registrieren, Passwort, 2FA, Einladung, Warten | unverändert | — | — | kein Zen ohne Anmeldung |

### Rahmen

| Teil | `calm` | `focus` |
|---|---|---|
| Seitenmenü, Kopfleiste, Brotkrumen | weg (`hide`) | weg |
| Eck-Knopf (öffnet Befehlspalette), Projektname | sichtbar | weg |
| Sichern-Status, „Zurück“ | sichtbar | sichtbar |
| Benutzermenü | über die Befehlspalette (Profil, Abmelden, „Zen verlassen“) | — |

Offen für Oliver: (1) Reiter des Projekts in `calm` ganz weg (nur Palette) oder als schmale Zeile? Vorschlag: weg. (2) Kommentare im Task-Detail in `calm` eingeklappt (Vorschlag) oder weg? (3) Das Dashboard in `calm` ohne Projektkarten: einverstanden?

## 6. Risiken

1. **Umfang:** Je Seitengruppe ein Commit, damit die Abnahme prüfbar bleibt.
2. **Zeitachse:** `Timeline.vue` hat 713 Zeilen. Die Sicht „aktueller Milestone“ (entschieden: erster nicht erledigter in der Reihenfolge des Projekts, sonst der letzte) ist der Anlass, sie in Teilkomponenten zu zerlegen (Milestone, ToDo-Zeile, Task-Zeile). Das Verhalten im Normalmodus bleibt unverändert, die bestehenden Tests müssen grün bleiben.
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

**Z2b Farbschema „Steingarten“ und „Stimmung“ (§4.6)**
- **Bauen:**
  - `utils/steingarten.ts` mit Mindestkontrast
  - Plugin für `--zen-*`, Abbildung auf `--wa-*` in `zen.css`
  - Cookies `zen_brightness` und `zen_contrast`
  - `MoodDrawer` mit Reglern, Vorschlägen, Farbleiste und Kontrastanzeige
  - Einstieg über Eck-Knopf und Benutzermenü (die Palette folgt mit Z3)
- **Abnahme:**
  - Neuladen in `calm` zeigt sofort die gespeicherte Stimmung, ohne Aufblitzen des Normal-Schemas.
  - Vitest für `palette()`: Stützpunkte, Kippen bei 50 und Mindestkontrast über das ganze Raster.
  - Der Drawer dunkelt nicht ab, und die Seite ändert sich live.
  - Mit der Tastatur bedienbar.

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
