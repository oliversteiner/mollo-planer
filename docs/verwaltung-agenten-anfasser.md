# Verwaltung, Agenten-Zugänge und Anfasser in der Zeitachse

Plan zu Auftrag 51 (Stuard P, 24.11.2026). Anlass: Rückmeldungen von Oliver aus dem täglichen Einsatz. Entscheide Oliver: Projekte im Zugangs-Dialog anzeigen **und** zuweisen; Anfasser nur auf der Seite Milestones (Kanban bleibt); Einordnung gleich nach Auftrag 50.

## 1. Befund (Stand `dev`, 24.11.2026)

| Thema | Heute | Ort |
|---|---|---|
| Menü «System» | Bezeichnung `nav.admin` = «System»; steht im Menü und in den Breadcrumbs von acht Seiten | `frontend/i18n/locales/{de,en}.json`, `layouts/default.vue` |
| Benutzerseite | eine Liste, Personen und Agenten gemischt, nach Name sortiert | `pages/admin/users.vue` |
| Agenten-Zugänge | unter jeder Agenten-Zeile fest ausgeklappt: Titel, Liste der Zugänge, Formular, Hinweis auf CLI | `pages/admin/users.vue`, Block `.users-access` |
| Agent anlegen | nur per SSH: `app:create-agent` | `app/Console/Commands/CreateAgent.php` |
| Projekte eines Agenten | auf der Benutzerseite nicht sichtbar; zuweisen nur auf der Mitgliederseite jedes Projekts | `setProjectMember`, `removeProjectMember` |
| Tasks verschieben (Milestones) | die **ganze Zeile** ist ziehbar (`VueDraggable`, Gruppe `timeline-tasks`, ohne `handle`); ein Klick mit kleiner Bewegung verschiebt den Task in ein anderes ToDo | `components/project/Timeline.vue`, `TimelineTask.vue` |

## 2. Entscheide

1. **Nur die Bezeichnung ändert**: «System» → «Verwaltung» (en: «Administration»). Pfade (`/system/…`, `/admin/…`), Rechte und Schlüssel (`nav.admin`) bleiben. So brechen keine Links, Lesezeichen und Tests.
2. **Benutzerseite mit zwei Abschnitten**: «Benutzer» (Personen) und «Agenten». Die Freigabe-Warteschlange bleibt zuoberst. Die Suche filtert beide Abschnitte.
3. **Zugang eines Agenten als Anzeige in der Zeile**, die Maske erst im Dialog.
4. **Der Dialog erklärt und führt**: kurzer Text, Schritte mit Stand, Zugänge, Projekte (anzeigen, zuweisen, entfernen).
5. **«Agent anlegen» in der Oberfläche** (Idee `k-1729`), weil der Abschnitt «Agenten» sonst keinen Einstieg hat. «Person einladen» heisst neu «Benutzer hinzufügen» (Idee `k-1728`).
6. **Verschieben in der Zeitachse nur noch über einen Griff** vorne an der Zeile.

## 3. Oberfläche

### 3.1 Menü

- `nav.admin`: de «Verwaltung», en «Administration». Prüfen, ob «System» als Abschnittsname noch anderswo steht (Befehlspalette, `aria-label`, Hilfe-/MCP-Seiten, Doku in `docs/`), und dort mitziehen. Nicht anfassen: «Systemrolle», «Systemstatus», Darstellung «System».

### 3.2 Seite Benutzer

```
PageFrame «Benutzer»                         [Benutzer hinzufügen] [Agent anlegen]
  Suche
  Warten auf Freigabe (wie heute, nur wenn vorhanden)
  Abschnitt «Benutzer» (Anzahl)
    Zeile: Avatar · Name, Rolle, (du) · E-Mail · Faktor-Badge · Menü
  Abschnitt «Agenten» (Anzahl)
    Zeile: Avatar · Name, Agent-Badge · E-Mail · [Zugang …] · Menü
```

- Abschnittstitel als Überschrift (h2) mit Anzahl; ein leerer Abschnitt «Agenten» zeigt einen Satz und den Knopf «Agent anlegen». Trifft die Suche in einem Abschnitt nichts, steht dort «Keine Treffer».
- **Anzeige «Zugang»** in der Agenten-Zeile, ein Knopf (kein reines Badge, damit er per Tastatur erreichbar ist), öffnet den Dialog:

| Zustand | Regel | Text | Farbe, Symbol |
|---|---|---|---|
| aktiv | mindestens ein Zugang ohne Ablauf oder mit `expiresAt` in der Zukunft | «Zugang aktiv» | success, `key` |
| läuft ab | aktiv, aber der späteste Ablauf liegt in weniger als 14 Tagen | «Zugang läuft ab» | warning, `key` |
| abgelaufen | Zugänge vorhanden, alle abgelaufen | «Zugang abgelaufen» | warning, `key` |
| keiner | kein Zugang | «Zugang einrichten» | neutral/brand outlined, `plus` |

  Die Regel als reine Funktion (`agentAccessState(tokens, now)`), damit sie getestet werden kann. Daten dafür sind schon in `adminUsersQuery` (`apiTokens { expiresAt }`).
- Das Menü der Agenten-Zeile bekommt zusätzlich «Zugang und Projekte» (derselbe Dialog).
- Der Block `.users-access` unter der Zeile entfällt samt CSS.

### 3.3 Dialog «Zugang für {Agent}»

Eigene Komponente (Vorschlag `components/admin/AgentAccessDialog.vue`), `AppDialog`, Inhalt von oben nach unten:

1. **Erklärung** (zwei, drei Sätze): Ein Agent meldet sich nicht im Browser an. Er arbeitet über den Connector (MCP) mit einem Zugang (Token) und darf genau das, was seine Rolle in den zugewiesenen Projekten erlaubt.
2. **Schritte** mit Stand (erledigt / offen), als nummerierte Liste; `StepTimeline` nur, wenn es ohne Verbiegen passt:
   1. Zugang erzeugen – erledigt, wenn ein aktiver Zugang besteht.
   2. Token ablegen: als Umgebungsvariable auf dem Rechner des Agenten, nie in einer Datei im Git. Der Token wird nur einmal angezeigt.
   3. Connector eintragen: Adresse des MCP-Servers **dieser Umgebung** (aus der Laufzeit-Konfiguration, nicht fest verdrahtet) mit Kopier-Knopf, Link auf die Anleitung `/mcp/`.
   4. Projekt zuweisen – erledigt, wenn der Agent in mindestens einem Projekt Mitglied ist.
3. **Zugänge**: Liste (Name, zuletzt benutzt, Ablauf, Widerrufen) und Formular «Zugang erzeugen», unverändert in Verhalten und Texten aus der heutigen Seite übernommen. Der neue Token erscheint **im selben Dialog** als Zustand (Warnhinweis «wird nicht mehr angezeigt», Feld, Kopieren, «Fertig»), nicht in einem zweiten Dialog darüber.
4. **Projekte**: Liste «Projekt · Rolle · Entfernen», darunter «Projekt zuweisen»: Auswahl Projekt (nur nicht archivierte, in denen die Person Mitglieder verwalten darf und der Agent noch nicht Mitglied ist) und Projektrolle (Vorgabe «Agent»). Projektname verlinkt auf die Mitgliederseite.
5. Hinweis auf die CLI (`app:user-token`) bleibt als kleine Zeile.

Edge Cases:
- **Geändert durch Auftrag 55 (Regel R11, kein Dialog über Dialog):** Die Passwortbestätigung ist ein Feld im Zugangs-Dialog (`PasswordConfirmField`, `usePasswordStep`), kein eigener Dialog. Die Rückfragen zum Widerrufen und Entfernen stehen in der Zeile. Das Aus- und Einblenden des Dialogs, das hier zuerst vorgesehen war, ist entfallen. Abbruch der Bestätigung lässt alles, wie es war.
- Schliessen, solange ein neuer Token sichtbar ist: nur über «Fertig» oder Escape mit dem bestehenden Verhalten; kein versehentliches Schliessen per Klick daneben, wenn `AppDialog` das hergibt.
- Agent in einem Projekt, das die Person nicht sieht: erscheint nicht (bestehende Sichtbarkeit), darunter kein Hinweis auf dessen Existenz.
- Grenze 50 Mitglieder je Projekt, Archiv, fehlendes Recht: die Meldung des Servers erscheint am Formular.
- Entfernen aus einem Projekt mit zugewiesenen Tasks: Verhalten wie auf der Mitgliederseite (gleiche Mutation, gleiche Rückfrage).

### 3.4 Agent anlegen

- Knopf «Agent anlegen» (Seitenaktion und im leeren Abschnitt), Befehl in der Palette wie bei `users-invite`.
- Dialog: Name, E-Mail (Hinweis: «Es wird keine Mail verschickt, ein Postfach ist nicht nötig»). Nach dem Anlegen schliesst der Dialog und öffnet **nichts** (Auftrag 55: zwei Schritte). Die neue Zeile ist kurz hervorgehoben, «Zugang einrichten» hat den Fokus. Das Passwort wird bei Bedarf als Feld im Dialog gefragt.

### 3.5 Zeitachse: Anfasser

- `TimelineTask` bekommt vorne einen Griff (Symbol `grip-vertical`), **nur** wenn Verschieben erlaubt ist (`canMoveTask`). `VueDraggable` in `Timeline.vue` mit `handle=".task-handle"`; der Rest der Zeile löst kein Ziehen mehr aus.
- Der Griff: `cursor: grab`, Klick und Doppelklick öffnen nichts (`@click.stop`, `@dblclick.stop`), `touch-action: none`, damit Scrollen auf dem Touch-Gerät die Zeile nicht mehr mitnimmt; Trefferfläche mindestens 24 × 24 px; Tooltip/`aria-label` «Task verschieben».
- Ruhige Ansicht (Zen): Griff ausgeblendet (`data-zen="hide"`), dort wird nicht verschoben.
- Platz: der Griff steht vor dem Symbol des Tasks; ohne Recht kein leerer Platz, der die Zeilen einrückt (prüfen, was ruhiger aussieht, und in beiden Fällen bündig halten).
- Verschieben ohne Maus: Das Menü des Tasks hat heute nur «Nach oben/unten» im selben ToDo. Ergänzen: «In anderes ToDo verschieben …» (Auswahl des ToDos, gruppiert nach Milestone, Mutation `moveTaskToTodo`), damit der Griff nicht der einzige Weg ist. Falls das mehr als eine kleine Ergänzung wird: weglassen und als Kommentar melden.
- **Nicht Teil:** Sortieren innerhalb eines ToDos per Ziehen (`:sort="false"` bleibt), Kanban (ganze Karte bleibt ziehbar).

## 4. Backend und API

Neuer Basis-Code (Konten, Agenten) nach der Trennregel: `App\Core\…`, nichts aus dem Fachcode importieren.

### 4.1 `createAgent`

```graphql
"Legt ein Agent-Konto an (kein Login im Browser). Nur mit users.manage und frischer Passwortbestätigung; Protokolleintrag und Mail an die Admins."
createAgent(input: CreateAgentInput!): User!

input CreateAgentInput {
  name: String!   # min 2, max 120
  email: String!  # email, unique:users,email
}
```

- Dieselbe Policy und dieselben Voraussetzungen wie `createAgentToken` (`manageAgentAccess`, Passwortbestätigung, Drossel).
- Die Logik aus `CreateAgent` (Konsole) in eine gemeinsame Aktion ziehen; Konsole und Mutation rufen sie auf. Unverändert: Systemrolle «Mitglied», nie Admin, Zufallspasswort, bestätigt und freigegeben, `is_agent = true`, keine Mail an das Konto.
- Protokoll wie `logIntervention` (ohne Geheimnisse), Mail an die Admins in der Art der Mail zu neuen Zugängen.
- Grenze: prüfen, ob es eine Obergrenze für Konten gibt (`CheckLimits`), und sie hier anwenden.

### 4.2 Projekte eines Agenten

Für den Dialog braucht es je Agent: Projekt (ID, Name, archiviert), Rolle des Agenten, ob die Person dort Mitglieder verwalten darf.

- `User.projects` gibt es (sichtbare Projekte), aber ohne Rolle. Vorschlag:

```graphql
type User {
  "Mitgliedschaften in Projekten, die du siehst; nach Projektname."
  memberships: [ProjectMembership!]!
}
type ProjectMembership {
  project: Project!
  role: Role!
  "Ob du in diesem Projekt Mitglieder verwalten darfst"
  viewerCanManage: Boolean!
}
```

  Wenn `Project.members` plus `viewerRights` dasselbe ohne neuen Typ hergibt: den einfacheren Weg nehmen und im Bericht nennen.
- **Laden erst beim Öffnen des Dialogs** (eigene Abfrage für einen Agenten), nicht in `adminUsersQuery`: die Liste soll nicht für jeden Agenten Projekte und Rollen nachladen (N+1). In der Abfrage Projekte und Rolle in einem Zug laden (`with`), nicht je Zeile.
- Auswahl «Projekt zuweisen»: bestehende Abfrage `projects` (nicht archiviert) mit dem Recht, Mitglieder zu verwalten; Rollen aus der bestehenden Rollen-Abfrage der Mitgliederseite.
- Schreiben über die bestehenden `setProjectMember` / `removeProjectMember`; keine neue Mutation, keine zweite Rechteprüfung. Nach dem Schreiben die Abfrage des Dialogs neu laden.

## 5. Tests

| Bereich | Mindestens |
|---|---|
| Navigation | Abschnitt heisst «Verwaltung»/«Administration»; Breadcrumb einer Verwaltungsseite |
| Benutzerseite | zwei Abschnitte, Agent nur unter «Agenten»; Suche filtert beide; leerer Abschnitt; kein Zugangs-Block mehr in der Zeile |
| Anzeige Zugang | `agentAccessState`: keiner, aktiv, läuft ab (13 Tage), abgelaufen, gemischt, ohne Ablauf |
| Dialog | öffnet über Anzeige und Menü; Schritte zeigen den Stand; Zugang erzeugen zeigt den Token einmal im Dialog; Widerrufen; abgebrochene Passwortbestätigung ändert nichts |
| Projekte | Liste mit Rolle; zuweisen und entfernen rufen die bestehenden Mutationen; Fehlermeldung des Servers sichtbar |
| `createAgent` | mit Recht und Bestätigung: Konto mit `is_agent`, Rolle Mitglied, Protokoll, Mail an Admins; ohne Recht, ohne frische Bestätigung, doppelte E-Mail: abgelehnt; Konsole liefert dasselbe Ergebnis wie vorher; in den Listen aller Mutationen eingetragen (`OutsiderAccessTest`, `MutationTest`) |
| Mitgliedschaften | nur sichtbare Projekte; Anzahl der Abfragen bleibt fest, egal wie viele Projekte |
| Zeitachse | `handle` gesetzt; Griff nur mit Recht; Klick auf den Griff öffnet den Task nicht; Menüpunkt «In anderes ToDo verschieben» ruft `moveTaskToTodo` |
| Browser | Playwright: Ziehen an der Zeile verschiebt nicht, Ziehen am Griff verschiebt |

Oberfläche mit `ui-consistency` (Muster finden, prüfen lassen). Texte in `de` und `en`.

## 6. Abnahme

- Menü und Breadcrumbs zeigen «Verwaltung».
- Benutzerseite: Abschnitte «Benutzer» und «Agenten»; die Agenten-Zeilen sind einzeilig mit der Anzeige «Zugang …».
- Klick auf die Anzeige öffnet den Dialog mit Erklärung, Schritten, Zugängen und Projekten; ein neuer Agent lässt sich ohne SSH anlegen, mit Zugang versehen und einem Projekt zuweisen.
- Auf der Seite Milestones lässt sich ein Task nur noch am Griff ziehen; Klicken, Markieren und Scrollen auf der Zeile verschieben nichts.
