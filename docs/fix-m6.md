# Fix M6 (F9): Befunde der Abnahme M6

Stand: 2026-11-18, Planung. Geprüft an `dev` bis `5a5d55a` mit frischen Demo-Daten und echtem Claude. Rolle: Auftrag für die Umsetzung (Bob). Ein Commit je Punkt, `umsetzung.md` „Stand“ nachführen. Ergebnisse: `docs/abnahme-m6.md`.

## F9.1 Mitglieder können Entwürfe nicht eintragen (wichtig)

**Befund:** David (Mitglied, `task.create`, `task.assignSelf`) bekommt Rückfrage und Vorschau, „Jetzt eintragen“ endet mit „Dafür fehlt dir die Berechtigung.“ `ImportPlan` prüft `Gate::authorize('import', $project)` = `planer.import` (Projektfunktion mit `project.update`), auch mit `appendOnly`. Konzept §3: Eintragen mit den Projektrechten der Person, je Änderung geprüft. Der Trockenlauf von `draftPlan` läuft offenbar ohne diese Prüfung, darum fällt es erst beim Eintragen auf.
**Umsetzung:**
- `importPlan` mit `projectId` und `appendOnly: true` verlangt statt `planer.import` nur, dass die Person das Projekt sieht und es nicht archiviert ist. Jede Änderung prüft der Importer wie bisher mit ihrem Recht (`milestone.create`, `todo.create`, `task.create`, `task.assign`/`task.assignSelf`, `task.move` für Status, Labels, `members.manage`).
- Ohne `appendOnly` bleibt `planer.import` nötig.
- Der Trockenlauf in `draftPlan` nutzt **dieselbe** Prüfung wie `importPlan`, damit die Vorschau schon zeigt, was beim Eintragen scheitern würde (Fehler je Änderung mit Pfad statt einer pauschalen Ablehnung).
- Gleich für MCP `import_plan` mit `append_only`.
- Tests: Mitglied mit `task.create` trägt einen Task in ein vorhandenes ToDo ein; Mitglied ohne `todo.create` bekommt den Fehler am neuen ToDo im Trockenlauf; Gast ohne Rechte: Fehler je Änderung; ohne `appendOnly` weiterhin `planer.import`.

## F9.2 `appendOnly` verhindert neue Projekte mit Labels (wichtig)

**Befund:** Ein neues Projekt (ohne `projectId`) mit `appendOnly: true` scheitert: „Der Assistent ergänzt nur. ‚project.labels‘ würde geändert.“ Die Oberfläche des Assistenten setzt `appendOnly` immer, darum lässt sich kein Projekt mit Labels, also keines aus einer Vorlage, über den Assistenten anlegen (Vorschau „Tag der offenen Tür“ mit Vorlage „Event“).
**Umsetzung:** Ohne `projectId` gibt es nichts Vorhandenes: `appendOnly` hat dann keine Wirkung (Projektfelder, Labels, Mitglieder sind Anlegen). Test: Entwurf aus einer Vorlage mit `appendOnly` und `templateId` → Projekt angelegt, `template_id` gesetzt, `projectCount` +1; dazu ein Test über `draftPlan` mit gefälschtem Client bis zum Eintragen.

## F9.3 Einstieg aus dem Projekt fehlt (klein)

**Befund:** Kein „Mit dem Assistenten ergänzen“ im Projekt (Auftrag §4.7). Heute nur über das Menü und dann die Projektwahl, oder über `/assistant?project=1`.
**Umsetzung:** Knopf im Projektkopf (neben „Milestone anlegen“, als zweiter Knopf mit Icon) oder im Projektmenü, sichtbar mit `assistant.use` und nicht im archivierten Projekt; öffnet `/assistant?project=<id>` mit leerem Chat.

## F9.4 Milestone still gewählt (mittel)

**Befund:** Ben: „Kontaktformular absichern … bis Ende Dezember“ ohne Milestone. Claude legte das ToDo direkt unter „Go-live“ an und erklärte es nur im Text. Konzept §5: Milestone ist Pflicht, Auswahl mit Knöpfen, „nie still wählen“. Bei David (Task ohne ToDo) kam die Auswahl richtig.
**Umsetzung:**
- Prompt: die Regel aus §5 ausdrücklich (neues ToDo ohne genannten Milestone → `choices` mit den passenden Milestones, `NEW_MILESTONE`, `CUSTOM_MILESTONE`, `IDEA`; kein `plan`).
- Server-Prüfung in `DraftProcessor`: Enthält der Entwurf ein neues ToDo unter einem vorhandenen Milestone, und hat die Person in dieser Unterhaltung weder diesen Milestone per Knopf gewählt noch seinen Titel oder seine ID in einer eigenen Nachricht genannt, dann wird statt des Plans eine Auswahl geliefert: der von Claude gewählte Milestone als „Empfohlen“, die übrigen nach §5, der Entwurf bleibt als `currentPlan` für den nächsten Schritt. Gleich für neue Tasks in einem nicht genannten vorhandenen ToDo.
- Tests mit gefälschtem Client: still gewählt → Auswahl; per Knopf gewählt → Plan; im Text genannt → Plan.

## F9.5 „In Ideen ablegen“ ohne Titel (klein)

**Befund:** Speichert sofort mit dem Projektnamen als Titel („Website-Relaunch“).
**Umsetzung:** Öffnet „Idee anlegen“ mit dem Entwurf (Auftrag §4.7): Titel vorgeschlagen aus dem ersten neuen Element (ToDo, sonst Task, sonst Milestone) bzw. bei neuem Projekt dessen Name, Beschreibung aus der ersten Nachricht der Person, Projekt vorbelegt; Speichern legt die Idee an und leert den Chat.

## F9.6 Texte (klein)

- Einzahl/Mehrzahl in der Vorschau („Neu: 0 Milestones, 1 ToDos, 2 Tasks“) und auf den Ideen-Karten („Entwurf mit 0 Milestones, 0 ToDos, 1 Tasks“); Null-Werte weglassen („Neu: 1 ToDo, 2 Tasks“).
- Nach dem Eintragen „Eingetragen: 1 ToDo, 2 Tasks“ (Auftrag §4.4) statt „Eingetragen.“
- Gewählter Knopf erscheint als Nachricht ohne interne ID („Ich wähle: Zu «Texte überarbeiten»“ statt „… (t-4)“); die ID geht unsichtbar mit.
- Fehler `append_only` nennt den Titel („„Styleguide erstellen“ würde geändert“), die ID nur, wenn kein Titel da ist.
- Ungültiger Entwurf einer Idee: „Der Entwurf ist ungültig: …“ statt „Die Datei ist ungültig: …“.
- DE/EN.

## F9.7 Chat-Zustand überlebt den Wechsel der Person (mittel, Datenschutz)

**Befund:** `assistant-draft-v2` in `sessionStorage` bleibt nach Abmelden; die nächste Person im selben Tab bekommt Projekt (und bei laufendem Chat Nachrichten, Entwurf, Vorschau) der vorherigen.
**Umsetzung:** Schlüssel mit der ID der Person (`assistant-draft-v2:<userId>`), beim Abmelden alle `assistant-draft*`-Schlüssel löschen (auch beim Ablauf der Sitzung), beim Laden einen gespeicherten `projectId` verwerfen, den die Person nicht sieht. „Neu beginnen“ auch ohne Nachrichten anbieten, wenn ein Projekt gesetzt ist („Anderes Projekt“). Test (Vitest).

## F9.8 Hinweis beim KI-Kontext fehlt (klein)

**Befund:** Die Karte erklärt den Zweck, aber nicht, dass alle im Projekt den Text sehen und er an den KI-Dienst geht.
**Umsetzung:** Hinweis aus Auftrag §4.7 unter dem Textfeld (DE/EN), bei „Für externe Werkzeuge freigeben“ zusätzlich „… auch über den Zugang für Claude lesbar“.

## Ablauf

F9.1 → F9.2 → F9.4 → F9.7 → F9.3 → F9.5 → F9.6 → F9.8, Prüfungen grün, im Browser ansehen (als Mitglied und mit einem neuen Projekt aus einer Vorlage, je ein echter Entwurf, falls der Schlüssel gesetzt ist), dann anhalten.

## Nachprüfung (2026-11-18, Planung)

F9.1 bis F9.8 mit echtem Claude nachgeprüft: alle ✅. Neu: F10.1 und F10.2 (unten). Details in `docs/abnahme-m6.md`.

---

# Fix M6 Nachtrag (F10)

## F10.1 Vorlage im Text genannt: keine Verknüpfung

**Befund:** Chiara schreibt „Nimm die Vorlage Event“ (ohne Knopf). Der Assistent plant auf dem Gerüst und mit den Labels der Vorlage, das Projekt entsteht aber mit `template` = `null`, `Template.projectCount` bleibt. Die Oberfläche übergibt `templateId` nur nach einem Klick auf `TEMPLATE`.
**Umsetzung:** Die Ausgabe von Claude nennt die verwendete Vorlage (`templateKey` bzw. ID, im erzeugten Schema neben `plan`); der Server prüft sie (sichtbar, existiert) und liefert sie in `PlanDraft.templateId`; die Oberfläche übernimmt `templateId` aus jedem `PlanDraft` (nicht nur aus dem Knopf) und gibt sie beim Eintragen mit. Ohne Vorlage `null`. Test mit gefälschtem Client: Vorlage im Text → `templateId` gesetzt, Projekt verknüpft, `projectCount` +1.

## F10.2 Interne ID und ISO-Datum in den Knöpfen (klein)

**Befund:** `detail` der Knöpfe lautet z. B. „m-3, fällig 2027-01-29“; der Text kommt ungeprüft von Claude.
**Umsetzung:** `detail` für `MILESTONE`, `PROJECT`, `TEMPLATE`, `APPEND_TO_TODO` baut der Server selbst aus den Daten (Fälligkeit im Format der Sprache, Version, Umfang), Claude liefert nur `kind`, `value`, `recommended` und optional eine Begründung (`NEW_MILESTONE`), aus der der Server IDs der Form `m-<Zahl>`/`t-<Zahl>`/`k-<Zahl>` entfernt. Daten in Antworten von Claude: Prompt verlangt das Datumsformat der Sprache (z. B. „31. Juli 2027“). Test.

Ablauf: F10.1 → F10.2, Prüfungen grün, im Browser ansehen, anhalten.
