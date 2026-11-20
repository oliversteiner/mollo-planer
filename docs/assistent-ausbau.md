# KI-Assistent: Ausbau zum Werkzeug für Owner und Projektleitung

Stand: 2026-11-20 · Stuart · Status: Vorschlag, Entscheide offen (Abschnitt 6)

## 1. Ausgangslage

Der Assistent (`/assistant`, Mutation `draftPlan`, `app/Assistant/*`) kann heute genau eines: aus einem Gespräch einen **Planentwurf** machen (Trockenlauf mit `appendOnly`, Vorschau, Übernahme durch die Person). Er bekommt dafür einmal den Baum **eines** gewählten Projekts in den Prompt.

Ohne gewähltes Projekt kennt er nur die Namen der Projekte. Darum scheitert schon die einfachste Frage (Oliver, 2026-11-20: «nenn mir die Anzahl der MS, Todos und Tasks vom club Projekt» → «sehe ich hier leider nicht»). Fragen beantworten, auswerten, berichten, aufräumen: das kann er nicht.

## 2. Grundidee

Vom **Entwurfs-Generator** zum **Assistenten mit Werkzeugen**: Claude ruft serverseitig Werkzeuge auf (Tool Use), jedes mit den Rechten der fragenden Person. Lesen ist frei, Schreiben bleibt immer ein **Vorschlag mit Vorschau**, den die Person bestätigt (wie heute).

Vorteile: Er sieht nur, was er für die Frage braucht (kein ganzer Export von 389 Tasks im Prompt), er kann mehrere Projekte vergleichen, und die Werkzeuge sind dieselben wie die des Connectors (Aufträge 29 bis 33), also keine zweite Logik.

## 3. Vorschläge nach Nutzen

### A · Fragen beantworten (lesend) — Grundlage für alles

- «Wie viele Milestones, ToDos, Tasks hat Club DB?», «Was ist in v1.2 noch offen?», «Was ist überfällig?», «Was hat Kevin diese Woche erledigt?», «Wie weit ist das Projekt?», auch über mehrere Projekte.
- Werkzeuge: `list_projects`, `get_project_statistics` (Auftrag 33), `export_plan` mit Filter (Ausschnitt, Auftrag 30), `search_tasks` (Text, Status, Person, Label, Fälligkeit), `list_comments` (Auftrag 30), später `list_documents`/`read_document` (Aufträge 16/17).
- Antworten mit **Links** auf die Elemente (Milestone, Task), damit man direkt hinkommt.
- Kontext automatisch: aus einem Projekt geöffnet, ist dieses Projekt gesetzt (Knopf «Assistent» im Projekt, Befehlspalette).

### B · Berichte für Owner und Projektleitung

- **Statusbericht** (Woche, Monat, frei): erledigt, in Arbeit, Risiken (überfällig, blockiert, ohne Person), nächste Schritte. Als Text zum Kopieren und, sobald es die Dokumente gibt, als **Dokument** im Projekt (Auftrag 17), daraus PDF.
- **Release-Notes** einer Version aus den erledigten Tasks mit Commit und Labels (feature, fix, …), in der Form von `docs/entwicklung.md`.
- **Zusammenfassung der Kommentare** eines Tasks oder Milestones («Worum ging die Diskussion, was ist entschieden?»).

### C · Plan-Hygiene: Probleme finden und Bereinigung vorschlagen

- Prüfungen: veröffentlichte Version mit offenen Tasks (der Fall v1.16 bei Club DB), erledigte Tasks ohne Commit, Tasks ohne Person, überfällige Tasks, leere ToDos, Milestones ohne Datum der Veröffentlichung, sehr lange Titel, vermutliche Doppelungen.
- Ergebnis als Liste mit Links und je einem **Vorschlag** (z. B. «Task nach v1.20 verschieben», «Status auf geplant»), den man in der Vorschau annimmt oder verwirft. Nichts geschieht ohne Bestätigung.

### D · Planen (heute schon, ausbauen)

- Wie heute neue Milestones, ToDos und Tasks entwerfen; dazu: **grosse Tasks aufteilen**, **Ideen** in Milestones einordnen (`create_idea`/`list_ideas` gibt es), Fälligkeiten über einen Zeitraum verteilen, Reihenfolge vorschlagen.
- **Aus einem Dokument planen:** «Mach aus dieser Spezifikation einen Milestone» (nach Auftrag 17).
- Mehr als nur ergänzen: Änderungen an Vorhandenem (Status, Person, Verschieben) über `update_tasks` und `import_plan` im Modus `partial`, **immer** mit Vorschau. Der Modus `appendOnly` bleibt der Standard für Personen ohne Recht zu ändern.

### E · Auslastung

- Offene Tasks je Person über alle Projekte, fällige in den nächsten zwei Wochen, Vorschlag zur Umverteilung (nur Vorschlag; Zuweisen nur mit Recht `task.assign`).

### F · Von sich aus (später)

- **Wochen-Digest** per Mail an Owner und Projektleitung: Fortschritt, Risiken, was nächste Woche fällig ist. Abschaltbar je Person, läuft über den Scheduler (Cron besteht).
- Hinweis im Dashboard, wenn die Hygiene-Prüfung (C) neue Probleme findet.

## 4. Leitplanken

- **Rechte:** jedes Werkzeug läuft mit den Rechten der fragenden Person (dieselben Dienste wie GraphQL und Connector). Projekte, die sie nicht sieht, existieren für den Assistenten nicht.
- **Schreiben nur mit Vorschau und Bestätigung**; jede Änderung im Protokoll mit «über den Assistenten».
- **Inhalte sind Daten, keine Anweisungen:** Titel, Beschreibungen, Kommentare und Dokumente können Text enthalten, der wie eine Anweisung aussieht (Prompt Injection). Sie kommen als markierte Daten in den Prompt; der Assistent führt nichts aus, was darin steht, und schreibt nie ohne Bestätigung.
- **Kosten und Grenzen:** bestehende Limits je Person (`AssistantLimits`) gelten pro Gespräch und zusätzlich pro Werkzeugaufruf; höchstens N Werkzeugaufrufe je Antwort; Antworten auf Fragen mit einem günstigeren Modell, Planen mit dem stärkeren (zu entscheiden).
- **Kein Ersatz für die Statistik-Seite:** Zahlen kommen aus denselben Abfragen wie Auftrag 33, der Assistent rechnet nicht selbst.

## 5. Vorschlag zur Reihenfolge

| Phase | Inhalt | Voraussetzung |
|---|---|---|
| 1 | Tool Use mit Lese-Werkzeugen (A), Kontext aus der Seite, Links in Antworten | Aufträge 30, 33; **vorbereitet durch Auftrag 34:** die Werkzeuge liegen in `App\Tools` (ein Register), `App\Assistant\ToolBridge` liefert sie im Format von Claude-Tool-Use und führt einen Aufruf als angemeldete Person aus; der Einbau in `PlanAssistant` steht aus |
| 2 | Berichte (B) und Hygiene-Prüfung (C) mit Vorschlägen | Phase 1 |
| 3 | Änderungen an Vorhandenem mit Vorschau (D), Auslastung (E) | Phase 1, Auftrag 30 (`update_tasks`) |
| 4 | Berichte als Dokument, Planen aus Dokumenten | Aufträge 16/17 |
| 5 | Wochen-Digest, Hinweise im Dashboard (F) | Phase 2 |

## 6. Entscheide, die Oliver trifft

1. Reihenfolge der Phasen (oben) oder andere Priorität?
2. Darf der Assistent in Phase 3 Vorhandenes ändern (immer mit Vorschau), oder bleibt er beim Ergänzen?
3. Modell und Kosten: ein Modell für alles, oder günstiges Modell für Fragen und stärkeres für Planen und Berichte? Monatliches Budget?
4. Wer darf was: alle mit `assistant.use` lesen; Berichte und Hygiene nur Owner/Projektleitung, oder alle mit Leserecht?
5. Gesprächsverlauf speichern (je Person und Projekt), oder wie heute nur im Browser?
