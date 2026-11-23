---
sessionId: session-261123-011352-udnr
---

# Arbeitsanweisung

### Ziel

Die Aufgaben des Release **1.3** im Projekt **Mollo Planer** (ID 2) umsetzen. Grundlage ist der gefilterte Export `export_plan` mit `version: "1.3"` (Revision 15, Excerpt).

### Aufgaben im Release 1.3

Milestone **m-92 „Zeitachse sortieren und filtern“** (Status: geplant), 3 ToDos, 11 Tasks:

| ToDo | Task | Status | Art |
|---|---|---|---|
| t-337 Milestones sortieren | k-1287 Knöpfe «Neuste zuerst» und «Älteste zuerst» | planned | Design, Feature |
| | k-1288 Sortierung nach Fälligkeitsdatum, ohne Datum nach Version | planned | Feature |
| | k-1289 Gewählte Sortierung pro Projekt merken | planned | Feature |
| | k-1299 In MS Zeitachse: aufklappen geht nich | open | Fehler |
| t-338 Geplante Milestones ausblenden | k-1290 Umschalter «Geplante ausblenden» | planned | Design, Feature |
| | k-1291 Festlegen und umsetzen, wann ein Milestone als geplant gilt | planned | Planung |
| | k-1292 Tests für Sortierung, Filter und gespeicherte Auswahl | planned | Feature |
| t-340 Anzeige «ohne Datum» ausblenden | k-1295 Hinweis «ohne Datum» nicht mehr anzeigen | planned | Feature |
| | k-1296 Tests für Milestones ohne Datum anpassen | planned | Feature |
| | k-1297 Task anlegen: Fokus in den Titel legen | open | Feature |
| | k-1298 Milestone-Titel-Nummer als Feld anlegen | open | – |

### Vorgehen

1. Vor jeder Aufgabe den Code lesen. Die Zeitachse liegt in `../frontend/app/components/project/Timeline.vue`, die Tests in `frontend/tests/nuxt/timeline.test.ts`, die Texte in `frontend/i18n/locales/de.json` (und `en.json`).
2. Jede Änderung mit Vitest prüfen (`npx vitest run tests/nuxt/timeline.test.ts` im Ordner `../frontend`), danach `npm run lint` und `npm run typecheck`.
3. Texte nur über i18n (`de.json` und `en.json`), nie hart codiert.
4. Nur die Oberfläche mit `wa-*`-Elementen und bestehenden Bausteinen (`ActionMenu`, `StepTimeline`) bauen. Kein `v-html`.
5. Statusänderungen der Tasks erst nach Bestätigung durch den Nutzer, und zuerst mit `update_tasks` im `dry_run: true`.

### Hinweise zu einzelnen Aufgaben

- **k-1299 (Fehler):** Der Code ist bereits angepasst (Auf-/Zuklapp-Knopf je Milestone in Vollansicht und Ruheansicht, Tests grün). Die Sichtprüfung im Browser steht noch aus. Erst danach den Status vorschlagen.
- **k-1291 (Definition „geplant“):** Vorschlag aus dem Planer: ein Milestone gilt als geplant, wenn alle Tasks den Status `planned` oder `idea` haben. Das muss bestätigt werden.
- **k-1289 (Speichern der Sortierung):** Offen, wo gespeichert wird. Vorschlag: pro Projekt im Browser (`localStorage`, Schlüssel mit der Projekt-ID). Eine Speicherung im Backend würde das GraphQL-Schema ändern.
- **k-1297 und k-1298:** Die Beschreibung ist knapp. Die genaue Umsetzung muss vor der Arbeit geklärt werden.

### Abschluss

- Alle Tests und Prüfungen grün.
- Sichtprüfung der Zeitachse in Vollansicht und Ruheansicht, Deutsch und Englisch.
- Status der fertigen Tasks mit dem Nutzer abstimmen.

# Technische Hinweise

### Betroffene Dateien

- `../frontend/app/components/project/Timeline.vue`: Sortierung (computed über `timeline`), Filter «geplante ausblenden», Datumsanzeige, Auf-/Zuklappen (`toggleMilestone`, `toggleAll`, `toggleMilestonesOnly`).
- `../frontend/app/components/StepTimeline.vue`: zeigt das Datum nur, wenn `item.date` gesetzt ist. Für «ohne Datum» reicht es, in `Timeline.vue` kein Datum zu übergeben.
- `../frontend/tests/nuxt/timeline.test.ts`: neue und angepasste Tests.
- `../frontend/i18n/locales/de.json` und `en.json`: neue Schlüssel für Sortier- und Filterknöpfe.
- k-1297 (Fokus beim Anlegen eines Tasks): vermutlich `../frontend/app/components/item/FormModal.vue`. Die Stelle muss noch bestätigt werden.

### Keine Backend-Änderung

Sortierung und Filter arbeiten auf den vorhandenen Daten (`milestones`, `position`, `dueDate`, Task-Status). Das GraphQL-Schema bleibt unverändert, außer die Speicherung der Sortierung soll im Backend erfolgen (siehe Hinweis k-1289).

# Delivery Steps

###   Step 1: Milestone-Sortierung mit Knöpfen umsetzen
Die Zeitachse sortiert die Milestones über zwei Knöpfe «Neuste zuerst» und «Älteste zuerst».

- In `Timeline.vue` eine Sortier-Funktion (computed) auf der Liste `milestones` ergänzen. Sortierung nach `dueDate`, Milestones ohne Datum nach Version (k-1288).
- Knopfgruppe über der Zeitachse, der aktive Knopf ist markiert (k-1287).
- Gewählte Sortierung pro Projekt im `localStorage` merken (k-1289, Schlüssel mit der Projekt-ID; Vorschlag, bestätigen lassen).
- Texte in `de.json` und `en.json`. Tests in `timeline.test.ts` für Reihenfolge und gemerkte Auswahl (k-1292).

###   Step 2: Filter «Geplante ausblenden» umsetzen
Ein Umschalter blendet Milestones aus, an denen noch nicht gearbeitet wird.

- Vorschlag bestätigen lassen: ein Milestone gilt als geplant, wenn alle Tasks `planned` oder `idea` sind (k-1291).
- Umschalter «Geplante ausblenden» über der Zeitachse, gleiche Bauweise wie «Nur Milestones anzeigen» (k-1290).
- Filter in der computed `timeline` anwenden.
- Tests in `timeline.test.ts` für geplante und laufende Milestones (k-1292).

###   Step 3: Hinweis «ohne Datum» aus der Zeitachse entfernen
Milestones ohne Fälligkeitsdatum zeigen keinen Hinweis mehr, die Zeile bleibt stimmig.

- In `Timeline.vue` für fehlendes `dueDate` kein Datum übergeben statt `t('project.timeline.noDate')` (k-1295).
- Prüfen, dass `StepTimeline.vue` die Datumsstelle dann leer lässt und die Ausrichtung stimmt.
- Tests in `timeline.test.ts` anpassen: kein Hinweis bei fehlendem Datum, Milestones mit Datum unverändert (k-1296).

###   Step 4: Offene Fehler und Aufgaben klären und abschliessen
Der Fehler «aufklappen geht nicht» ist im Code behoben, die Restpunkte sind geklärt.

- k-1299: Sichtprüfung der Zeitachse im Browser (Vollansicht und Ruheansicht), danach Status-Vorschlag mit `update_tasks` (erst `dry_run: true`).
- k-1297 (Fokus in den Titel beim Anlegen eines Tasks): Ziel und Komponente mit dem Nutzer klären, vermutlich `item/FormModal.vue`, dann umsetzen.
- k-1298 (Milestone-Titel-Nummer als Feld): Anforderung mit dem Nutzer klären, dann umsetzen oder bewusst verschieben.
- Abschluss: Vitest, `npm run lint`, `npm run typecheck` grün; Statusänderungen erst nach Bestätigung.