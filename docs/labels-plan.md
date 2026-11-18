# Labels für Tasks (Label-Sets)

> **Hinweis (2026-11-13, D1):** Teilweise historisch. Label-Sets werden mit Schritt S7 durch Vorlagen und Labels je Projekt ersetzt (`planer-abgleich.md` §3, §5.2). Bis dahin beschreibt dieses Dokument den Code.

Stand: 2026-11-10, vom Auftraggeber freigegeben und umgesetzt (Branch `feature/labels`). Ergänzt `backend-plan.md`, `graphql-kontext.md` und `frontend-webawesome-plan.md`; das GraphQL-Schema bleibt der Vertrag.

## Ziel
Tasks lassen sich mit Labels kategorisieren. Ein **Label-Set** ist eine benannte Gruppe von Labels; **jedes Projekt wählt ein Set**. Die App bringt Sets mit, eigene Sets lassen sich anlegen, auch als Kopie eines bestehenden Sets.

## Entscheidungen (vom Auftraggeber, 2026-11-10)
1. **Sets gelten app-weit.** Anlegen und duplizieren dürfen die Rollen mit `labelSets.manage` (Admin, Projektleitung). Eigene Sets ändert der Ersteller mit dieser Fähigkeit, fremde nur mit `labelSets.manageAny` (Admin). Stand nach dem Rollen-Umbau, siehe `rollen-plan.md`. Ein Set für ein Projekt wählen darf, wer das Projekt ändern darf (`PROJECT_UPDATE`).
2. **Mitgelieferte Sets sind unveränderlich**; anpassen nur über Duplizieren.
3. **Set-Wechsel:** Tasks behalten ihre bisherigen Labels. Sie werden weiter angezeigt (als „nicht im Set“ erkennbar), sind aber nicht mehr wählbar, bis jemand sie entfernt.
4. **Sprache:** Mitgelieferte Sets, Labels und Kategorien sind übersetzt (de/en). Eigene Sets heißen so, wie man sie anlegt.
5. **Darstellung:** neutrale Marke im Matter-Stil mit einem farbigen Punkt vor dem Namen (nicht getönt wie die Status-Badges).

## Vorschläge (zur Freigabe)
6. **Farben als Farbton, nicht als Hex.** Gespeichert wird einer von zehn Tönen der Palette von Web Awesome (`gray`, `red`, `orange`, `yellow`, `green`, `cyan`, `blue`, `indigo`, `purple`, `pink`). Das Frontend zeichnet den Punkt mit `--wa-color-<ton>-…` (Palette „Mild“ von Matter, hell und dunkel passend). Eigene Labels wählen aus denselben zehn Tönen.
7. **Mehrere Labels pro Task**, nur aus dem Set des Projekts.
8. **Labels setzen und entfernen** darf, wer den Task ändern darf (`TASK_UPDATE`).
9. **Eigene Sets ändern oder löschen** darf, wer das Set angelegt hat, und jeder Admin. Löschen geht nur, wenn kein Projekt das Set gewählt hat. Ein Label aus einem eigenen Set löschen entfernt es nach Rückfrage auch von allen Tasks.
10. **Grenzen:** Set-Name 2–60 Zeichen, Beschreibung bis 200, Label-Name 1–40, Kategorie bis 40, höchstens 30 Labels pro Set, Namen innerhalb eines Sets eindeutig.
11. **Neue Projekte** starten ohne Set; das Set wird in den Projekteinstellungen gewählt. Die Demo-Daten geben „Website-Relaunch“ das Set „Softwareentwicklung“ und „Sommerfest 2027“ das Set „Event“.
12. **Nicht in diesem Schritt:** Labels im KI-Assistenten und im MCP-Zugang, Label-Filter auf „Meine Tasks“, Labels in Mails.

## Mitgelieferte Sets
Farbtöne aus dem JSON übertragen (nächster Ton der Palette):

| Set | Label (de / en) | Kategorie (de / en) | JSON | Ton |
|---|---|---|---|---|
| Softwareentwicklung / Software development | Recherche / research | Phase / phase | #3b82f6 | blue |
| | Design / design | Phase / phase | #ec4899 | pink |
| | Feature / feature | Typ / type | #10b981 | green |
| | Fehler / bug | Typ / type | #ef4444 | red |
| | Blockiert / blocked | Status / status | #f59e0b | yellow |
| Event / Event | Location / location | Gewerk / trade | #8b5cf6 | purple |
| | Speaker / speakers | Gewerk / trade | #06b6d4 | cyan |
| | Marketing / marketing | Gewerk / trade | #f97316 | orange |
| | Bestätigt / confirmed | Status / status | #10b981 | green |
| Podcast / Podcast | Themenidee / topic idea | Pipeline / pipeline | #6366f1 | indigo |
| | Gästebuchung / guest booking | Pipeline / pipeline | #14b8a6 | cyan |
| | Audioschnitt / audio editing | Pipeline / pipeline | #eab308 | yellow |
| | Social Media / social media | Promotion / promotion | #ec4899 | pink |

Die Beschreibungen der Sets werden ebenfalls übersetzt. Die Beispielprojekte aus dem JSON (`proj_001`, `proj_002`) werden nicht übernommen.

## Backend
- **Tabellen:** `label_sets` (`key` nur bei mitgelieferten, `name`, `description`, `created_by` nullable), `labels` (`label_set_id`, `key` nur bei mitgelieferten, `name`, `category`, `color`, `position`), `projects.label_set_id` (nullable, `restrict` beim Löschen eines Sets), Pivot `label_task`.
- **Mitgelieferte Sets** kommen per Migration (oder Seeder, der bei jedem Deploy läuft) mit festem `key`; Name, Beschreibung und Kategorie liefert der Server übersetzt nach `Accept-Language` aus `lang/de|en/labels.php`.
- **GraphQL:** Typen `LabelSet` (`id`, `name`, `description`, `isBuiltin`, `labels`, `viewerCanUpdate`, `viewerCanDelete`, `usedByProjects`) und `Label` (`id`, `name`, `category`, `color: LabelColor`); `Project.labelSet`, `Task.labels`; Abfrage `labelSets`; Mutationen `createLabelSet`, `duplicateLabelSet`, `updateLabelSet` (Name, Beschreibung, Labels in einem Zug), `deleteLabelSet`, `setProjectLabelSet`, `setTaskLabels`.
- **Rechte:** neue Fähigkeit `LABEL_SETS_MANAGE` (Owner, Manager) in `ProjectPermissions`, „in irgendeinem Projekt“ geprüft von `LabelSetPolicy`; `setProjectLabelSet` über `PROJECT_UPDATE`, `setTaskLabels` über `TASK_UPDATE`; Labels eines Tasks müssen aus dem Set seines Projekts stammen (Regel mit Test).
- **Tests** zu jeder Mutation, Regel und Policy; `composer test:mariadb` wegen der Migrationen.

## Frontend
- **`TaskLabel`**: neutrale Marke mit Farbpunkt (Ton als Token), Labels außerhalb des Sets gestrichelt umrandet.
- **Anzeige:** Kanban-Karten, Aufgaben in der Zeitachse, Task-Details, Meine Tasks.
- **Bearbeiten:** in den Task-Details eine Mehrfachauswahl (`wa-select multiple` mit Kategorien als Gruppen); im Task-Dialog beim Anlegen ebenfalls.
- **Kanban:** Filter nach Label über dem Board.
- **Projekteinstellungen:** Karte „Labels“ mit der Wahl des Sets und einer Vorschau seiner Labels, Link „Sets verwalten“.
- **Seite `/label-sets`** (Brotkrumen „Arbeitsplatz / Label-Sets“, für Admins in der Seitenleiste unter „Verwaltung“, sonst über die Projekteinstellungen): Liste der Sets (mitgeliefert und eigene), Duplizieren, Anlegen, Bearbeiten im Dialog (Name, Beschreibung, Labels mit Name, Kategorie, Farbton; Reihenfolge), Löschen.
- **i18n** de/en, Tests für die Bausteine und die Seite.

## Reihenfolge beim Bauen
Branch `feature/labels`, abgezweigt von `feature/webawesome` (die Oberfläche baut auf Web Awesome auf).
1. Backend: Migrationen, Modelle, mitgelieferte Sets mit Übersetzungen, Schema, Policy und Rechte, Mutationen, Tests; `composer schema:frontend`, Codegen.
2. Frontend: `TaskLabel`, Anzeige an Tasks.
3. Frontend: Labels setzen (Task-Details, Task-Dialog), Wahl des Sets in den Projekteinstellungen.
4. Frontend: Seite `/label-sets` mit Anlegen, Duplizieren, Bearbeiten, Löschen.
5. Kanban-Filter, Demo-Daten, Doku nachführen, Prüfung im Browser.

## Stand
- [x] 1. Backend (Migration mit den mitgelieferten Sets, Modelle, Schema, `LabelSetPolicy`, `LABEL_SETS_MANAGE`, Mutationen, Tests; zusätzlich `viewerCanManageLabelSets` für die Oberfläche)
- [x] 2. `TaskLabels` an Kanban-Karten, Zeitachse und Meine Tasks
- [x] 3. `LabelPicker` in den Task-Details und beim Anlegen eines Tasks; Karte „Labels“ in den Projekteinstellungen
- [x] 4. Seite `/label-sets` (Anlegen, Duplizieren, Bearbeiten, Löschen; Rückfrage vor dem Löschen von Labels)
- [x] 5. Filter nach Label im Kanban (Ziehen ist beim Filtern aus, das Kartenmenü verschiebt weiter), Demo-Daten, Doku

## Hinweise aus der Umsetzung
- **Reihenfolge der Labels eines Tasks:** Der Batch-Loader von Lighthouse liefert `Task.labels` nicht garantiert in der Reihenfolge des Sets. `Label.position` ist darum im Schema; das Frontend sortiert danach.
- **Mitgelieferte Sets** legt die Migration an (`App\Support\BuiltinLabelSets::sync()`, abgeglichen über `key`). Neue mitgelieferte Sets oder Labels kommen per neuer Migration, die `sync()` erneut aufruft.
- **Kopien mitgelieferter Sets** übernehmen Namen, Kategorien und Beschreibung in der Sprache der Anfrage; danach sind es gewöhnliche Texte.
