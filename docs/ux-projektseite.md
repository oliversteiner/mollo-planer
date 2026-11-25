# UX-Review und Spezifikation: Projektseite (Milestones)

Auftrag 61 · ToDo `t-523` (Tasks `k-2003` Review, `k-2004` Spezifikation) · Laura, 25.11.2026 · Stand: **entschieden** (Oliver, 25.11.2026, Abschnitt 6); bereit für den Bau-Auftrag.

Seite: `/projects/:id` (`frontend/app/pages/projects/[id]/index.vue`), Zeitachse `components/project/Timeline.vue` mit `StepTimeline.vue` und `TimelineTask.vue`. `components/planer/Tree.vue`, das der Auftrag nennt, ist **nicht** diese Seite: Es zeigt die Vorschau eines Imports und des Assistenten und bleibt unverändert.

Begriffe: In der Oberfläche heisst es überall «Milestone» (Reiter, Knöpfe, `lang`). Diese Spezifikation bleibt dabei; «Meilenstein» nur im Fliesstext für Menschen.

---

## 1. Vorgehen (Teil A)

* Lokal, eigene Wegwerf-Datenbank der Browser-Tests (SQLite, eigener Port), nie TEST oder PROD. Ein Projekt «Kundenportal Relaunch» mit 9 Milestones (drei erledigt und veröffentlicht, einer in Arbeit, vier offen, ein «Backlog» mit Ideen), je drei ToDos mit drei Tasks, Preise an sechs Milestones, eine ausgestellte Rechnung (M1) und ein Entwurf (M2). Dazu ein leeres Projekt.
* Angemeldet als **Admin** (mit `billing.manage`) und als **Mitglied** ohne Abrechnungsrecht. 1440 px und 390 px breit, volle und ruhige Ansicht, Tastatur (25 × Tab).
* Bildschirmfotos mit Playwright, nicht im Repo, verlinkt im PR.
* Bewertung nach den Heuristiken von NN/g (H1 bis H10) und Apple HIG. Schwere: **hoch** (verhindert eine Aufgabe oder führt in die Irre), **mittel** (kostet Zeit, verwirrt), **gering** (Feinschliff).

## 2. Befunde

### Hoch

**B1 · Der Preis eines Milestones fehlt.** Die Abfrage `ProjectTimeline` lädt `priceCents` schon, die Zeitachse zeigt es nirgends. Wer abrechnet, muss den Milestone bearbeiten oder auf «Verrechenbar» wechseln, um den Preis zu sehen. *H1 Sichtbarkeit, H6 Wiedererkennen statt Erinnern.*

**B2 · Einzelnes Zuklappen gibt es, man findet es aber nicht.** Der Knopf «Zuklappen» steht **mittig unter dem Fortschrittsbalken**, weit weg vom Titel, ohne Objekt im Text («Zuklappen» was?). Die ToDos darunter klappen dagegen mit einem Pfeil **links vom Titel** auf: zwei Muster für dasselbe auf einer Seite. Darum hat Oliver das Zuklappen nicht gefunden. *H4 Konsistenz, Fitts (kleines Ziel, weit vom Blickpunkt), HIG Disclosure (Pfeil vor dem Titel).*

**B3 · «Nur Milestones anzeigen» ist eine Sackgasse.** Der Schalter blendet Beschreibung, ToDos, «Zuklappen» und «Alle ToDos zeigen» aus. Einen einzelnen Milestone kann man dann nicht mehr öffnen, nur alles zurückschalten. Der Zustand steht in der URL (`?milestones=only`), der Rest nicht: zwei Arten Zustand. *H3 Kontrolle und Freiheit, H7 Flexibilität.*

**B4 · Ein Fehler beim Laden sieht aus wie ein leeres Projekt.** `Timeline.vue` kennt nur «lädt» und «keine Milestones». Schlägt die Abfrage fehl, steht «Noch keine Milestones» da und lädt sogar zum Anlegen ein. *H1, H9 Fehler erkennen.* (Aus dem Code, im Browser nicht ausgelöst.)

### Mittel

**B5 · Alles offen, die Seite ist sehr lang.** Standard: jeder Milestone offen, mit Beschreibung, Fortschritt, ToDo-Zeilen und «ToDo anlegen». 9 Milestones ergeben 3750 px, der aktuelle Milestone (M4) liegt beim Öffnen unter dem Rand. Den Zustand merkt sich die Seite nicht: Nach jedem Neuladen ist wieder alles offen. *Progressive Disclosure, H8 ästhetisch und minimal.*

**B6 · Zwei Pfeile in einer Zeile.** Neben jedem «···» (Menü) steht ein Pfeil nach unten. Er gehört zum Menü, sieht aber aus wie Auf- und Zuklappen; bei ToDos steht links schon der Pfeil zum Aufklappen. Ursache prüfen: `ActionMenu` setzt `with-caret` nur mit Text, trotzdem erscheint ein Pfeil (Attribut kommt als `false` an?). *H4.*

**B7 · «Im Entwurf» liest sich als Zustand des Milestones.** Das Abzeichen der Rechnung steht neben dem Status «Erledigt» und sagt nicht, *was* im Entwurf ist. *H2 Sprache der Benutzer.*

**B8 · Die Kopfzeile ist unruhig.** Reihenfolge: Nummer, Titel, «1.0 · Veröffentlicht · 15. September 2026», Status, Rechnung, Menü, Fälligkeit. Bei veröffentlichten Milestones steht das Datum zweimal. Die Fälligkeit klebt hinter dem Menü statt in einer festen Spalte, darum springt sie von Zeile zu Zeile. *HIG Hierarchie, Gestaltgesetz der Nähe.*

**B9 · Ruhige Ansicht: ein ToDo ohne offene Tasks öffnet sich als leere Box.** Im aktuellen Milestone ist «Planung» erledigt: Der Rahmen geht auf, darin ist nichts. *H1.*

**B10 · Der Fortschrittsbalken bei 0 % ist kaum zu sehen.** Die Spur hebt sich vom Hintergrund kaum ab (von Auge deutlich unter 3 : 1, WCAG 1.4.11; nicht gemessen). Der Text «0 von 9 Tasks erledigt» schwebt allein. *Barrierefreiheit.*

### Gering

**B11 · Schmales Fenster (390 px).** Die beiden Knöpfe der Werkzeugleiste stehen untereinander, die Kopfzeile eines Milestones bricht in vier Zeilen um (Titel, Version, Abzeichen, Datum), «9 von 9 Tasks erledigt» wird abgeschnitten.

**B12 · «Backlog: 0 von 0 Tasks erledigt»**, obwohl drei Ideen darin liegen. Ideen zählen im Fortschritt nicht mit; die Zeile sagt dann nichts. Besser: «3 Ideen» statt Fortschritt, wenn nur Ideen da sind.

**B13 · Viele Halte der Tab-Taste bis zum Inhalt** (18 in der Seitenleiste). «Zum Inhalt springen» ist da, das ist gut. In der Zeitachse selbst ist die Reihenfolge logisch.

**Was gut ist:** Der aktuelle Milestone ist auf der Zeitachse hervorgehoben; Rechte steuern sauber, was erscheint (Mitglied ohne Abrechnung: kein Abzeichen, kein Preis); der Anfasser der Tasks (Auftrag 51) ist eindeutig; der leere Zustand hat einen Hinweis zum Anlegen; die ruhige Ansicht zeigt genau den aktuellen Milestone offen.

---

## 3. Konzept

**Die Zeitachse wird eine Gliederung.** Jeder Milestone ist eine Zeile, die man auf- und zuklappt, gleich wie heute die ToDos. Zugeklappt zeigt die Zeile alles, was man zum Entscheiden braucht (Abschnitt 4.2). «Nur Milestones» ist damit dasselbe wie «alle zugeklappt» und **fällt weg** (B3).

Drei Stufen, ein Muster:

1. Milestone zu: eine Zeile mit Kopf und Fortschritt.
2. Milestone offen: Beschreibung, ToDo-Zeilen (zu), «ToDo anlegen».
3. ToDo offen: Tasks, «Task anlegen» (wie heute).

Warum nicht `wa-tree` oder `wa-details`: `wa-tree` verlangt die Tastaturführung eines Baums (eine Fokusstelle, Pfeile wandern) und verträgt keine Menüs, Links und Anfasser in den Zeilen; es bleibt der Import-Vorschau. Die Zusammenfassung von `wa-details` darf keine Bedienelemente enthalten (Menü, Link zur Rechnung). Die Seite hat das passende Muster schon: der Knopf `todo-toggle` mit Pfeil, daneben das `ActionMenu`. Dasselbe für Milestones (Disclosure-Muster nach WAI-ARIA).

---

## 4. Spezifikation für Bob

### 4.1 Aufbau

Neue Komponente `components/project/TimelineMilestone.vue` (der Schritt, den `docs/zen-modus.md` §6 schon vorsieht): Kopf, zugeklappter Inhalt, offener Inhalt. `Timeline.vue` behält Abfrage, Ziehen und Zustand und reicht weiter. `StepTimeline` bleibt (Marker, Linie, aktueller Milestone).

Werkzeugleiste über der Zeitachse, rechts (heute `wa-cluster wa-justify-content-end`):

```
[Summe der Preise, nur billing.manage]            [⌄⌄ Alle aufklappen] [⌃⌃ Alle zuklappen] [Platz für 47: Nach Version ordnen]
```

### 4.2 Kopf eines Milestones (zu und offen gleich)

Zeile 1, ein `<button type="button" class="milestone-toggle">` über die ganze Breite bis vor das Menü (wie `todo-toggle`):

| Reihenfolge | Inhalt | Komponente | Bedingung |
|---|---|---|---|
| 1 | Pfeil `chevron-right` (zu) / `chevron-down` (offen), Farbe `--wa-color-text-quiet` | `wa-icon` | immer |
| 2 | Nummer `M4` | `MilestoneNumber` | immer |
| 3 | Titel, halbfett, kürzt mit «…» in einer Zeile, `title`-Attribut mit dem ganzen Titel | Text | immer |
| 4 | Status | `StatusBadge` | immer |
| — | Abstand, Rest rechtsbündig in fester Reihenfolge: | | |
| 5 | Version `1.3` und Release-Status, ohne Datum («1.3 · Geplant») | `span.wa-caption-s` | mit Version |
| 6 | Preis (4.4) | `wa-badge` | `priceCents != null` (nur mit `billing.manage` gesetzt) |
| 7 | Fälligkeit; bei veröffentlichtem Release das Release-Datum statt der Fälligkeit (nie beide, B8) | `DueDate` | mit Datum, sonst «Ohne Datum» leise |

Ausserhalb des Knopfs, rechts: `ActionMenu` (wie heute, ohne Pfeil, B6). Die Fälligkeit wandert aus dem Slot `date` von `StepTimeline` in die Kopfzeile, damit sie in einer Spalte steht.

Zeile 2 (zu **und** offen): Fortschritt wie heute (`wa-progress-bar` + «3 von 9 Tasks erledigt · 6 offen»), Spur mit `--wa-color-neutral-fill-normal` statt `-quiet` (B10). Nur Ideen: «3 Ideen» statt Balken (B12).

Offen zusätzlich: Beschreibung (`RichTextView`), ToDo-Liste, «ToDo anlegen». Der heutige Knopf «Zuklappen» unter dem Balken **fällt weg**.

### 4.3 Auf- und Zuklappen

**Zustand.** Ein `Set` offener Milestone-IDs (`openMilestones`), wie heute `openTodos`. Ersetzt `collapsedMilestones`, `zenOpened` und `milestonesOnly`.

**Standard beim ersten Öffnen eines Projekts (E1):** alle Milestones zu, **ausser dem aktuellen** (erster nicht erledigter in der Reihenfolge des Projekts, sonst der letzte; dieselbe Regel wie in der ruhigen Ansicht, `current` in `Timeline.vue`). Das gilt nur, solange für das Projekt kein Zustand gemerkt ist; danach gilt der gemerkte Zustand, auch wenn inzwischen ein anderer Milestone der aktuelle ist.

**Merken.** Pro Projekt und Browser in `localStorage`, Schlüssel `mollo.timeline.<projectId>.open`, Wert: JSON-Liste der offenen Milestone-IDs. Lesen und Schreiben wie `useStoredSwitch` (ohne Speicher gilt der Zustand nur für den Besuch). Unbekannte IDs (gelöscht, verschoben) beim Lesen still verwerfen. ToDos werden **nicht** gemerkt (wie heute; E2).

**Was einen Milestone von selbst öffnet** (und gemerkt wird):

* `#milestone-<id>` in der URL (Anker gibt es schon): öffnen, hinscrollen.
* `?task=<id>` (Details offen): der Milestone und das ToDo des Tasks öffnen sich, damit die Zeile hinter dem Seitenpanel sichtbar ist.
* Ein neuer Milestone (nach «Milestone anlegen»): offen, hinscrollen, Fokus auf seinen Pfeil.
* «ToDo anlegen» und «Task anlegen» liegen nur in offenen Milestones, also nichts zu tun.
* Ziehen eines Tasks: über einem zugeklappten Milestone 600 ms halten öffnet ihn (wie heute bei ToDos, «Spring-Loading» aus macOS). Nach dem Ablegen bleibt er offen.
* Verschieben (Nach oben/unten, Auftrag 47) und Neuladen durch das Polling ändern nichts: der Zustand hängt an der ID, nicht am Platz.

**Alle auf einmal.**

* «Alle aufklappen» (`chevrons-down`): öffnet alle Milestones, ToDos bleiben, wie sie sind. Deaktiviert, wenn alle offen sind.
* «Alle zuklappen» (`chevrons-up`): schliesst alle Milestones **und** alle ToDos. Deaktiviert, wenn alle zu sind.
* **Wahltaste**-Klick (Alt) auf den Pfeil eines Milestones öffnet oder schliesst ihn mit allen seinen ToDos (Finder-Muster, für Kenner; steht im Tooltip und im Handbuch). Ersetzt das heutige «Alle ToDos zeigen».
* Beide Knöpfe auch in der Befehlspalette (`useCommands`), anstelle von `milestones-only`.

**«Nur Milestones anzeigen»: streichen.** Begründung: «alle zugeklappt» zeigt dieselbe Übersicht, lässt aber einzelne Milestones öffnen (B3), und es gibt einen Zustand statt zwei. Alte Links mit `?milestones=only` klappen beim Öffnen alle zu und entfernen den Parameter (`router.replace`).

**Tastatur und Fokus** (Disclosure-Muster):

* Tab: Pfeil-Knopf des Milestones → sein Menü → (wenn offen) Inhalt → nächster Milestone. Eine Fokusstelle je Kopf, kein Roving.
* Enter oder Leertaste: auf- oder zuklappen. Pfeil rechts: aufklappen, Pfeil links: zuklappen (nur wenn der Kopf den Fokus hat; nichts anderes, kein Springen zwischen Zeilen).
* `aria-expanded` am Knopf, `aria-controls` auf den Inhalt (`id="milestone-<id>-content"`), sichtbarer Name: «Milestone «{title}» auf- oder zuklappen» (wie `toggleTodo`).
* Nach «Alle zuklappen» bleibt der Fokus auf dem Knopf. Wird ein Milestone mit Fokus im Inhalt zugeklappt (per «Alle zuklappen»), geht der Fokus auf dessen Pfeil.
* Fokusring: `--wa-focus-ring` wie `todo-toggle`.

**Bewegung.** Aufklappen ohne Animation der Höhe (wie die ToDos heute). Pfeil dreht 90° in 150 ms, aus bei `prefers-reduced-motion`.

### 4.4 Preis am Milestone

Nur wer `billing.manage` hat; die API liefert `priceCents` und `billing` sonst `null`, das Frontend prüft nichts selbst. Agent-Konten haben das Recht nie.

| Fall | Anzeige (Abzeichen `wa-badge appearance="outlined"`) | Symbol | Variante |
|---|---|---|---|
| Preis, keine Rechnung | «CHF 7’800.00» | `coins` | `neutral` |
| Preis, Rechnung im Entwurf | «CHF 6’200.00 · Rechnung im Entwurf» | `file-pen` | `neutral` |
| Preis, Rechnung ausgestellt oder bezahlt | «CHF 4’800.00 · Verrechnet 2026-0007» | `file-invoice` | `success` |
| kein Preis, Milestone erledigt, nicht verrechnet | «Ohne Preis» | `coins` | `warning`, `appearance="plain"` |
| kein Preis, nicht erledigt | nichts | | |

* Das Abzeichen mit Rechnung ist ein Link auf die Rechnung (heute `NuxtLink` um das Abzeichen); ein Abzeichen ohne Rechnung ist kein Link.
* Betrag mit `formatMoney` aus `utils/money.ts`, Währung «CHF» davor (die Rechnungen kennen nur CHF; kommt später eine andere Währung, aus `BillingSettings`).
* Das bisherige Abzeichen «Verrechnet · …» / «Im Entwurf» geht in diesem einen Abzeichen auf (B7).
* **Summe je Projekt** links in der Werkzeugleiste, leise (`wa-caption-m`, `--wa-color-text-quiet`): «Preise: CHF 25’900.00 · verrechnet 4’800.00 · im Entwurf 6’200.00». Nur Milestones mit Preis zählen; ohne einen einzigen Preis keine Zeile. Gerechnet im Frontend aus der Liste (keine neue API). Entscheid E3.
* Ruhige Ansicht: Preis und Summe ausgeblendet (`data-zen="hide"`), wie heute das Rechnungsabzeichen.

### 4.5 Ruhige Ansicht (Zen)

* Gleicher Zustand, gleiche Bedienung. Beim Wechsel in «Ruhig» öffnet sich zusätzlich der aktuelle Milestone (wie heute), die übrigen behalten ihren Zustand.
* Ausgeblendet wie heute (`docs/zen-modus.md` §5) plus Werkzeugleiste, Preis, Summe, Menü.
* Ein ToDo ohne offene Tasks zeigt in «Ruhig» statt der leeren Box den Satz «Alle Tasks erledigt.» (B9).

### 4.6 Zustände

| Zustand | Anzeige |
|---|---|
| Lädt (erstes Mal) | Drei Platzhalter in Zeilenhöhe eines zugeklappten Milestones (`wa-skeleton effect="sheen"`, je Kreis + zwei Balken) statt eines Blocks; Werkzeugleiste verborgen |
| Lädt (Polling, Neuladen) | nichts, Inhalt bleibt stehen |
| Fehler | `wa-callout variant="danger"` mit Symbol `triangle-exclamation`: «Die Milestones konnten nicht geladen werden.» + Knopf «Erneut versuchen» (`refetch`). Kein leerer Zustand, keine Werkzeugleiste (B4). Neues Muster; danach auch für andere Listen nutzbar |
| Leer | wie heute (`EmptyState`, `flag-checkered`, Hinweis nur mit `MILESTONE_CREATE`) |
| Milestone ohne ToDos (offen) | wie heute «Noch keine ToDos» + «ToDo anlegen» |
| Archiviertes Projekt | Auf- und Zuklappen geht, Bearbeiten nicht (die Rechte regeln es schon) |

### 4.7 Schmales Fenster (unter 40em)

* Kopf in zwei Zeilen: Pfeil, Nummer, Titel (kürzt), Menü / darunter Status, Version, Preis, Datum (umbrechen erlaubt).
* Werkzeugleiste: Knöpfe nur mit Symbol, `aria-label` und `wa-tooltip` mit dem Text; Summe in eine eigene Zeile darüber.
* Fortschritt: Balken volle Breite, Text darunter.
* Trefferfläche des Pfeils mindestens 44 × 44 px (HIG), am Desktop mindestens 24 × 24 px.

### 4.8 Texte

Neue Schlüssel unter `project.timeline` (de / en). Wegfallende: `milestonesOnly`, `showAll`, `expandAll`, `collapseAll` (in `project.timeline`), `zen.milestonesOnly`, `zen.expandMilestone`, `zen.collapseMilestone`; vorher mit `grep` prüfen, ob sie anderswo gebraucht werden.

| Schlüssel | Deutsch | Englisch |
|---|---|---|
| `expandAllMilestones` | Alle aufklappen | Expand all |
| `collapseAllMilestones` | Alle zuklappen | Collapse all |
| `toggleMilestone` | Milestone «{title}» auf- oder zuklappen | Expand or collapse milestone “{title}” |
| `toggleMilestoneHint` (Tooltip) | Mit gedrückter Wahltaste auch alle ToDos | Hold Option to include all to-dos |
| `openTasks` | {count} offen | {count} open |
| `ideasOnly` | {count} Idee \| {count} Ideen | {count} idea \| {count} ideas |
| `allTasksDone` | Alle Tasks erledigt. | All tasks done. |
| `loadError` | Die Milestones konnten nicht geladen werden. | The milestones could not be loaded. |
| `retry` | Erneut versuchen | Try again |
| `priceSum` | Preise: {total} · verrechnet {billed} · im Entwurf {draft} | Prices: {total} · billed {billed} · in draft {draft} |
| `price` | CHF {amount} | CHF {amount} |
| `noPrice` | Ohne Preis | No price |

Unter `billing.milestone`: `billed` → «Verrechnet {number}» / “Billed {number}”, `billedDraft` → «Rechnung im Entwurf» / “Invoice in draft”.

Kein neuer Dialog; nichts davon öffnet einen Dialog (R11).

### 4.9 Tests (für den Bau)

* Vitest (`tests/nuxt`): Standard zu; ein Milestone auf und zu; «Alle auf-/zuklappen» und deaktivierte Zustände; Speichern und Lesen in `localStorage` je Projekt; unbekannte IDs verworfen; `?milestones=only` klappt zu und verschwindet; `#milestone-…` und `?task=…` öffnen; Wahltaste öffnet ToDos.
* Preis: alle fünf Fälle aus 4.4; ohne `billing.manage` (API `null`) kein Abzeichen, keine Summe.
* Fehlerzustand statt leerem Zustand.
* Playwright: Tastatur (Tab, Enter, Pfeile), Ziehen eines Tasks auf einen zugeklappten Milestone, schmales Fenster; Bildschirmfotos an den PR (R1).

---

## 5. Verhältnis zu Auftrag 47 (Nach Version ordnen)

* Gleiche Seite, gleiche Werkzeugleiste: 47 setzt «Nach Version ordnen» rechts neben «Alle zuklappen» (Platz in 4.1). Das ist die Stelle, die 47 C offenlässt.
* Die Vorschau von 47 zeigt die Milestones als **zugeklappte Köpfe** (4.2, ohne Menü und Pfeil, verschobene markiert). Darum zuerst `TimelineMilestone` bauen.
* Version im Kopf (4.2, Spalte 5) macht sichtbar, warum ein Milestone verschoben würde.
* «Nach oben / Nach unten» im Menü bleibt; der Zustand hängt an der ID (4.3).
* **Empfehlung:** ein Auftrag, zwei PRs in dieser Reihenfolge: (1) diese Spezifikation (Frontend, kein Backend nötig), (2) 47 (Backend, MCP, Knopf und Vorschau mit der neuen Komponente). Die Teile A, B, D von 47 (Backend und MCP) können parallel laufen.

## 6. Entscheide (Oliver, 25.11.2026)

* **E1 · Standard beim Öffnen:** alle zu, **der aktuelle Milestone offen** (Abweichung von der Empfehlung «alle zu»). Umgesetzt in 4.3.
* **E2 · Offene ToDos merken:** nein, nur Milestones.
* **E3 · Summe der Preise je Projekt:** ja, leise in der Werkzeugleiste (4.4).
* **E4 · «Ohne Preis»** bei erledigten, nicht verrechneten Milestones: ja, als Hinweis (4.4).
* **E5 · «Nur Milestones anzeigen»:** streichen, ersetzt durch «Alle aufklappen» und «Alle zuklappen»; `?milestones=only` klappt alles zu (4.3).

## 7. Nicht Teil

* Sortieren nach Datum oder Version in der Anzeige (t-337) und das Neu-Ordnen (47).
* Kanban, ToDo-Liste, Seite «Verrechenbar».
* Die Ursache von B6 (Pfeil am Menü) betrifft alle Seiten mit `ActionMenu`: im Bau prüfen und, wenn klein, mitbeheben, sonst als eigener Punkt melden.

**Handbuch:** Kapitel «Meilensteine, ToDos und Tasks» (Clara, nach dem Bau): Auf- und Zuklappen, Wahltaste, Preise.
