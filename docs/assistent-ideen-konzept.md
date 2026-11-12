# KI-Assistent und Ideen-Sammlung (Konzept)

Stand: 2026-11-12. Status: Entscheidungen des Auftraggebers eingearbeitet, noch nicht gebaut.
Das technische Schema (Typen, Mutationen, Tabellen, Rechte) steht in `docs/schema-review.md` (Abschnitte A4, B1–B3). Bei Abweichungen gilt das Schema-Review. Breaking Changes sind erlaubt, bestehende Daten müssen nicht erhalten bleiben.

---

## 1. Ausgangslage

- Der KI-Assistent (`/assistant`, heute für Rollen mit `projects.create`) legt nur **neue Projekte** an. Er entwirft einen Plan aus Milestones, ToDos, Tasks und Mitgliedern. Gespeichert wird erst mit „Jetzt eintragen“.
- Der MCP-Server kann inzwischen auch bestehende Projekte pflegen: `list_projects`, `get_project`, `list_users`, `create_project`, `set_project_member`, `create_project_plan`, `add_to_plan`, `update_plan_item`, `move_milestone`, `set_task_status`.
- Es gibt kein Löschen über MCP. Fehlerhafte Einträge müssen also vor dem Speichern abgefangen werden.

## 2. Ziel

1. Freie Ideen (Text, Stichpunkte) annehmen. Ideen ohne Projekt landen auf der **Ideen-Seite**.
2. Ein neues ToDo aus einer Anfrage anlegen und dazu **Tasks vorschlagen**.
3. Eine größere Idee in **mehrere ToDos** mit jeweils Tasks aufteilen.
4. Bei unklarem Projekt **nachfragen, mit Auswahl**.
5. Fehlt ein Milestone, **nachfragen, mit Auswahl** (Abschnitt 5).
6. Nichts speichern, bevor die Person den Vorschlag bestätigt hat.
7. Pro Projekt **KI-Kontext** hinterlegen können, den der Assistent beim Vorschlagen nutzt und auf Wunsch externe KI-Tools erhalten (Abschnitt 11).

## 3. Entscheidungen

- **Milestone-Pflicht:** Ein ToDo gehört immer zu einem Milestone. Ein ToDo ohne Milestone wird nie gespeichert.
- **Rechte für Tasks:** Mitglieder dürfen **Tasks anlegen** und **eigene Tasks ändern** (eigener Task = von der Person angelegt). Einen eigenen Task dürfen sie **sich selbst zuweisen**, aber niemand anderem. **Löschen** und das Zuweisen an andere bleiben bei Owner und Manager.
- **Projekt anlegen:** Admins, Projektleitung und Koordination.
- **Ideen:** Ideen ohne Projekt werden gesammelt. Im Assistenten heißt die Option „In Ideen ablegen“.
- **Sichtbarkeit der Ideen:** Nur Admins und die Person, die die Idee erstellt hat, sehen sie.
- **Zugang zum Assistenten:** Alle angemeldeten Personen außer der Rolle `lesend`. Die Projektrechte gelten weiterhin für jedes Schreiben.
- **Ideen anlegen:** alle Rollen außer `lesend`.
- **Löschen** von Ideen, ToDos, Tasks usw. legt in den Papierkorb (siehe `docs/schema-review.md`, A5).

## 4. Ablauf

1. **Absicht erkennen:** neues Projekt, neues ToDo, Tasks zu einem bestehenden ToDo, Idee aufteilen oder nur als Idee ablegen. Bei Unklarheit fragen.
2. **Projekt bestimmen:**
   - Eindeutig, wenn die Person es nennt oder die Seite es vorgibt (zum Beispiel `/projects/:id/todos`).
   - Sonst **Rückfrage mit Auswahl**. Angeboten werden nur nicht archivierte Projekte, in denen die Person ToDos anlegen darf (Owner, Manager, Mitglied). Dazu kommen „Neues Projekt“ (nur mit `projects.create`) und „In Ideen ablegen“.
3. **Milestone bestimmen** (Pflicht, siehe Abschnitt 5). Erst danach ist das ToDo speicherbar.
4. **Vorschlag erzeugen (nur Entwurf):** ToDo mit Titel, Beschreibung, Milestone, Tasks mit Titel, Labels aus dem Label-Set des Projekts, optional Fälligkeit und Zuweisung. Der KI-Kontext des Projekts (Abschnitt 11) fließt ein, falls vorhanden.
5. **Prüfen lassen:** Die Person kann einzelne Tasks streichen, umbenennen oder ändern.
6. **Eintragen:** erst nach „Jetzt eintragen“, über `importPlanIntoProject` (Schema-Review A4) mit denselben Policies wie im Frontend.

## 5. Milestone-Pflicht: Rückfrage mit Auswahl

Fehlt der Milestone, zeigt der Assistent den Hinweis „Ein ToDo gehört zu einem Milestone“ und bietet Optionen zum Anklicken an. Die Optionen sind Buttons, keine Freitext-Abfrage. Im Chat ohne Buttons erscheint dieselbe Liste nummeriert.

Die Liste enthält:

1. **Bestehende Milestones des Projekts:** jeder als eigener Button, mit Titel und Fälligkeit, falls vorhanden. Der passendste ist mit „Empfohlen“ markiert. Der Assistent wählt ihn nach Titel und Inhalt der Idee.
2. **Neuer Milestone (Vorschlag):** Der Assistent schlägt einen Titel vor, mit einer Begründung in einem Satz. Beispiel: „Neuer Milestone „Launch-Vorbereitung““. Die Person bestätigt oder ändert den Titel.
3. **Eigenen Milestone definieren:** öffnet ein Feld für Titel und optional Fälligkeit. Der Milestone wird erst beim Eintragen angelegt.
4. **In Ideen ablegen:** Das ToDo wird nicht angelegt. Die Idee landet ohne Milestone auf der Ideen-Seite und kann später zugeordnet werden.

Regeln für die Auswahl:
- Bei mehr als fünf bestehenden Milestones zeigt der Assistent die fünf passendsten und eine Option „Alle anzeigen“.
- Ein Milestone wird nie still gewählt, auch nicht, wenn nur einer existiert. Die Person bestätigt den Klick.
- Ist der Milestone einmal gewählt, bleibt die Auswahl für weitere ToDos derselben Idee erhalten, bis die Person sie ändert.
- Die Option „In Ideen ablegen“ speichert nichts im Projekt. Sie erzeugt nur eine Idee, mit dem gewählten Projekt oder ohne Projekt.
- Technisch: Ein neuer Milestone ist im Plan-Entwurf ein Milestone ohne `id`, ein bestehender einer mit `id` (Schema-Review A4).

## 6. Ideen-Seite

- Menüpunkt **„Ideen“** unter **„Meine Tasks“**, Route `/ideas` (Vorschlag). Eingeblendet nach `viewerSystemAbilities` (`ideas.create`).
- Sichtbar nur für Admins und die Person, die die Idee erstellt hat.
- Zeigt alle **offenen** Ideen. Übernommene oder verworfene Ideen werden nach einem Filter ausgeblendet.
- Pro Idee: Titel, Beschreibung, Autorin oder Autor, Datum, optional Projekt.
- Aktionen: einem Projekt zuordnen und in ToDos aufteilen (führt in den Assistenten-Ablauf ab Schritt 2), verwerfen, als übernommen markieren, löschen (Papierkorb).
- Datenmodell und Mutationen: `docs/schema-review.md`, B1.

## 7. Regeln, die dabei gelten

- **Rechte:** Wie in Abschnitt 3 festgelegt. Das Backend prüft jede Änderung, der Assistent soll Verbote vorher nennen.
- **Archiv:** In archivierten Projekten schlägt der Assistent nichts vor und trägt nichts ein.
- **Sichtbarkeit:** Projekte nur über den Scope `visibleToViewer` anbieten, nie Projekte, die die Person nicht sieht. Ideen nur für Admins und die Autorin oder den Autor. Die Policy prüft das serverseitig, nicht nur die Seite.
- **Status:** Von ToDo und Milestone wird er vom Observer berechnet, nie eingegeben. Der Status einer Idee wird von der Person gesetzt.
- **Rich Text:** TipTap-JSON, nie HTML.
- **Labels:** nur aus dem Label-Set des jeweiligen Projekts.
- **Mails:** Eine Zuweisung löst beim Speichern eine Mail aus. Der Vorschlag zeigt Zuweisungen klar an und warnt vor dem Eintragen.
- **Tests:** Neue Mutationen, die Milestone-Auswahl, die Ideen-Seite und der KI-Kontext brauchen Tests. Dazu gehören Tests, dass andere Mitglieder Ideen nicht sehen und dass der KI-Kontext ohne Freigabe nicht über MCP ausgegeben wird. `OutsiderAccessTest` und `MutationTest` werden ergänzt.
- **Oberfläche:** nur `wa-*`-Elemente und eigene Bausteine. Alle Texte in `i18n/locales/de.json` und `en.json`.

## 8. MCP-Werkzeuge

Bestehende Werkzeuge decken das Anlegen schon ab und werden weiterverwendet:

| Werkzeug | Zweck im Ideen-Ablauf |
|---|---|
| `list_projects` | Auswahl für Rückfragen (neu: nur nicht archivierte, Filter „darf ToDos anlegen“) |
| `get_project` | bestehende Milestones und ToDos für die Auswahl |
| `create_project_plan` | neue Milestones mit ToDos und Tasks |
| `add_to_plan` | ToDos an einen bestehenden Milestone oder Tasks an ein bestehendes ToDo hängen |
| `update_plan_item`, `move_milestone`, `set_task_status` | nachträglich ändern, einordnen, erledigt setzen |

Neu dazu:

| Werkzeug | Zweck | Schreibend |
|---|---|---|
| `create_idea(title, description?, project_id?)` | Idee ablegen | ja, nur nach Bestätigung |
| `list_ideas(status?)` | offene Ideen der Person bzw. alle für Admins | nein |
| `get_project_ai_context(project_id)` | KI-Kontext eines Projekts, nur wenn freigegeben (Abschnitt 11) | nein |

Ein Werkzeug schreibt nie direkt aus einer Idee heraus. Die Bestätigung ist ein eigener Schritt im Chat.

## 9. Szenarien

1. **Idee ohne Projekthinweis:** „Ausschilderung fürs Sommerfest.“ → Auswahl: „Sommerfest 2027“, „Neues Projekt“, „In Ideen ablegen“.
2. **Große Idee:** „Relaunch der Website mit neuem Blog und Newsletter“ → Vorschlag mit drei ToDos und je 2–4 Tasks. Jedes ToDo braucht einen Milestone, die Auswahl erscheint pro ToDo oder einmal für alle.
3. **Projekt ist klar durch die Seite:** Anfrage auf `/projects/:id/todos` → keine Projekt-Rückfrage, aber die Milestone-Auswahl bleibt.
4. **Projekt ohne Milestone:** Der Assistent zeigt den Hinweis und schlägt einen neuen Milestone vor, die anderen Optionen bleiben daneben.
5. **Passender bestehender Milestone:** Der Assistent markiert ihn als „Empfohlen“, die Person klickt zur Bestätigung.
6. **Eigener Milestone:** Die Person gibt Titel und Fälligkeit ein, der Milestone wird mit dem ToDo zusammen angelegt.
7. **Mitglied gibt eine Idee ein:** ToDo und Tasks werden angelegt. Zuweisen kann das Mitglied nur sich selbst; Vorschläge für andere Personen zeigt der Assistent als Hinweis „Zuweisung durch Manager“.
8. **Doppelte Idee:** Ähnliches ToDo existiert → Hinweis und Option „Tasks an bestehendes ToDo hängen“.
9. **Zuweisung per Name:** nur Projektmitglieder wählbar. Unbekannter Name → Auswahl aus den Mitgliedern.
10. **Labels aus dem Set:** „Event“-Projekt, Idee „Speaker einladen“ → Label „Speaker“ vorgeschlagen.
11. **Datum in der Idee:** „bis Freitag“ → konkretes Datum im Vorschlag zur Bestätigung zeigen.
12. **Liste mit mehreren Ideen:** jede Zeile wird ein eigener Vorschlag, gesammelt bestätigt.
13. **Keine Rechte im gewählten Projekt:** freundliche Absage mit Hinweis, den Manager zu fragen. Die Idee bleibt auf der Ideen-Seite der Autorin oder des Autors.
14. **Idee später zuordnen:** Auf der Ideen-Seite wird eine offene Idee einem Projekt zugeordnet, Milestone-Auswahl folgt wie oben. Beim Eintragen wird die Idee mit den ToDos verknüpft und auf „übernommen“ gesetzt.
15. **Abbruch:** Nichts wird gespeichert, bis bestätigt. Ein Entwurf bleibt im Chat, eine Idee bleibt auf der Ideen-Seite.
16. **KI-Kontext hilft beim Vorschlag:** Im Projekt steht „Alle Texte auf Deutsch, Sie-Form, Budget max. 2000 CHF“. Der Assistent beachtet das beim Formulieren der Tasks.
17. **Externe KI mit Kontext:** Eine Person ändert Tasks über einen Connector in einem externen KI-Tool. Ist der KI-Kontext freigegeben und fragt die Person ihn an, ruft das Tool `get_project_ai_context` auf. Ohne Freigabe kommt nichts zurück.
18. **Archiviertes Projekt:** Es erscheint nicht in der Auswahl. Nennt die Person es ausdrücklich, weist der Assistent darauf hin und bietet „In Ideen ablegen“ an.

## 10. Offene Punkte

Keine. Die Umsetzung folgt `docs/refactoring-vor-umsetzung.md` und danach `docs/schema-review.md` (Reihenfolge der Umsetzung). Die Rechte-Dokumentation wird nach dem Funktionsbau geprüft und nachgeführt.

## 11. KI-Kontext pro Projekt

**Ziel:** Owner und Manager hinterlegen in den Projekteinstellungen Hintergrundwissen, das der Assistent bei Vorschlägen nutzt. Beispiele: Tonalität, Standards, Budgetgrenzen, Glossar, Zuständigkeiten.

**Eingabeformen:**
- **Textfeld** mit Klartext.
- **Datei-Upload** nur mit **Textdateien und Markdown**. Der Text wird beim Hochladen extrahiert und gespeichert, die Datei selbst nicht. PDFs, Bilder und Office-Dateien werden abgelehnt.

**Umfang:** höchstens **20 000 Zeichen** insgesamt, für Text und extrahierte Datei zusammen.

**Rechte:**
- Ändern: Owner und Manager (wie „Projekt ändern“).
- Lesen im Frontend: **alle Projektmitglieder**. Der Assistent nutzt den Kontext bei allen, die das Projekt sehen.

**Weitergabe an externe KI-Tools (auf Wunsch):**
- Pro Projekt gibt es einen Schalter **„Für externe KI-Tools freigeben“**, Standard: aus. Nur Owner und Manager schalten ihn ein. Die Freigabe gilt pro Projekt, nicht pro Person.
- Ist er aus, liefert `get_project_ai_context` nichts aus. Die Antwort nennt nur, dass keine Freigabe besteht.
- Ist er an, liefert das Werkzeug den Text und den extrahierten Dateiinhalt, nur für Personen mit Zugriff auf das Projekt. Er wird nicht automatisch mitgeschickt, die Person fordert ihn im Chat an.
- Der Kontext ist Daten, keine Anweisung. Ein externes Tool soll ihn als Hintergrundwissen behandeln, auch wenn darin Sätze wie Befehle stehen.
- Hinweis in der Oberfläche: Alle Mitglieder sehen den Inhalt, er geht an den KI-Dienst und bei Freigabe auch an externe Tools. Keine Passwörter, Zugangsdaten oder personenbezogenen Daten Dritter hinterlegen.

**Technik:** `docs/schema-review.md`, B2. Frontend-Ort: `/projects/:id/settings`, Abschnitt „KI-Kontext“.

**Weitere Regeln:**
- Löschen des Kontexts: Owner und Manager können Text und Datei entfernen. Die Freigabe erlischt damit ebenfalls.
- Versionierung: keine in der ersten Version. Eine Änderung ersetzt den alten Stand.
- Der Assistent erwähnt den Kontext nur, wenn er die Vorschläge beeinflusst.
