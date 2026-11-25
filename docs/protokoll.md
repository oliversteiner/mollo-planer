# Protokoll: wer hat wann was gemacht

Plan zu Auftrag 53 (Stuard P, 24.11.2026). Anlass: Oliver möchte als Admin sehen, was andere Konten (Personen und Agenten) wann gemacht haben. Club DB hat ein solches Protokoll; der Planer bekommt eine eigene, breitere Fassung, weil er die Basis des Starter-Kits wird.

## 1. Entscheide (Oliver, 24.11.2026)

| Frage | Entscheid |
|---|---|
| Umfang | Verwaltung **und** Plan: Konten, Rollen, Zugänge, Mitglieder, Einstellungen sowie jede Änderung am Plan (Status, Zuweisung, Verschieben, Titel, Löschen, Importe). Anmeldungen und Lesezugriffe **nicht**. |
| Sichtbarkeit | Admins sehen alles. Owner und Projektleitung sehen den Verlauf **ihres** Projekts. |
| Aufbewahrung | unbegrenzt. Es gibt keinen automatischen Löschlauf. |
| Grösse | zwei einstellbare Grenzen: Maximalgrösse in MB (Standard 200) und Höchstzahl der Einträge (Standard 500 000). Warnung per Mail und Toast, bevor eine davon erreicht ist. Bei 100 % wird **weiter aufgezeichnet** und gewarnt. |
| Löschen | von Hand: das ganze Protokoll oder ein Jahr. Vorher Download als CSV möglich. |
| Gelöschte Konten | erscheinen als «Gelöschtes Konto». Namen von Personen werden im Protokoll nicht mitgespeichert. |
| Detailtiefe | alter und neuer Wert je Feld. Bei langen Texten (Beschreibung, Story, Kommentar, Dokument) nur der Vermerk «geändert». |

## 2. Ausgangslage im Planer

Kein Protokoll. Verstreut gibt es: Sicherheitsereignisse in der Logdatei (`Log::warning`, nur per SSH), `mcp_tool_calls` (Aufrufe ohne Inhalt, `app:mcp-usage`), `created_by`/`deleted_by`/`done_at` an den Plan-Elementen, Versionsverlauf der Dokumente, Autor und Zeit der Kommentare.

## 3. Club DB zum Vergleich

Tabelle `audit_log` (`user_id`, `entity_type` = Tabellenname, `entity_id`, `action`, `changes` mit `old`/`new`, `created_at`), geschrieben über Model-Ereignisse im Trait `ReviewsChanges` und an etwa zwanzig Stellen von Hand; Seite `/admin/log` mit Filtern, höchstens 200 Einträge, Löschlauf nach 60 Tagen.

Übernommen wird die Grundform (eine Tabelle, alt/neu je Feld, Filter nach Art, Person, Zeitraum). Für den Planer reicht sie nicht:

| Lücke in Club DB | Folge im Planer | Lösung hier |
|---|---|---|
| kein Projektbezug | Projektleitung kann ihr Projekt nicht filtern, Rechteprüfung je Zeile nötig | Spalte `project_id` |
| kein Kanal | nicht erkennbar, ob Oberfläche, MCP, Assistent oder Konsole | Spalte `channel`, dazu der Name des Zugangs |
| kein Zusammenhang | ein Import mit 30 Änderungen sind 30 lose Zeilen | Spalte `batch_id` |
| Name nur über `user_id` | nach dem Löschen eines Elements steht nichts mehr da | Momentaufnahme des Titels (nicht von Personennamen, siehe §4) |
| Tabellenname als Art | Protokoll hängt am Datenbankschema | feste Schlüssel (`task`, `todo`, …) |
| Sperrliste der Felder | ein neues Feld mit Geheimnis landet im Protokoll | Erlaubnisliste je Modell |
| nur Model-Ereignisse | Massen-Updates (Reihenfolge) und Zuordnungstabellen (Mitglieder, Labels) fehlen oder rauschen | Model-Ereignisse nur für Felder, alles andere als bewusstes Ereignis |
| feste Obergrenze 200 | bei unbegrenzter Aufbewahrung unbrauchbar | Blättern mit Cursor |

## 4. Datenmodell

Tabelle `activity_log`, **nur Anfügen** (kein Update, kein Delete über die Anwendung, kein `updated_at`):

| Spalte | Typ | Bedeutung |
|---|---|---|
| `id` | bigint | fortlaufend; Sortierung und Cursor |
| `created_at` | timestamp | Zeitpunkt, `useCurrent` |
| `actor_id` | FK `users`, nullable, `nullOnDelete` | wer; null = System oder gelöschtes Konto |
| `actor_kind` | varchar(10) | `user`, `agent`, `system`; mit `actor_id` null und `user`/`agent`: «Gelöschtes Konto» |
| `channel` | varchar(12) | `web`, `mcp`, `assistant`, `console`, `system` |
| `access` | varchar(80), nullable | Name des Zugangs bzw. des OAuth-Clients bei `mcp` (nie ein Token) |
| `batch_id` | char(26), nullable | ULID; gleiche ID für alle Einträge einer Anfrage, eines Imports oder eines Befehls |
| `project_id` | FK `projects`, nullable, `nullOnDelete` | Projekt des Eintrags; null bei Verwaltung |
| `subject_type` | varchar(40) | fester Schlüssel: `project`, `milestone`, `todo`, `task`, `comment`, `document`, `label`, `member`, `user`, `agent_access`, `role`, `setting`, `oauth_client`, `template` |
| `subject_id` | bigint, nullable | ID des Elements |
| `subject_label` | varchar(200), nullable | Titel zum Zeitpunkt des Eintrags; bei `user` und `member` leer (Name wird beim Lesen aufgelöst) |
| `action` | varchar(20) | `created`, `updated`, `moved`, `deleted`, `restored`, `purged`, `archived`, `unarchived`, `assigned`, `granted`, `revoked`, `approved`, `rejected`, `reset` |
| `changes` | json, nullable | `{ "feld": [alt, neu] }`; lange Texte als `{ "description": true }` |

Indizes: `(project_id, id)`, `(subject_type, subject_id, id)`, `(actor_id, id)`, `(batch_id)`, `(created_at)`.

Regeln:
- **Erlaubnisliste:** Jedes Modell nennt die Felder, die ins Protokoll dürfen. Nie: Passwort, Token, 2FA-Geheimnisse, Wiederherstellungscodes, `remember_token`, Inhalte von Dokumenten und Kommentaren.
- **Werte lesbar:** Enum als Schlüssel (`open`, `done`), Labels als Schlüssel, Datum als ISO.
- **Keine Personennamen in der Tabelle** (Entscheid «Gelöschtes Konto»): Wer handelt, steht nur als `actor_id`. Personen als Wert (Zuweisung, Mitglied) stehen in `changes` als Verweis `{ "user": 12 }`. Der Name wird beim Lesen aufgelöst; gibt es das Konto nicht mehr, zeigt die Oberfläche «Gelöschtes Konto». Bei Änderungen an einem Konto werden Name und E-Mail nur als «geändert» vermerkt, ohne Werte. So muss beim Löschen eines Kontos nichts im Protokoll nachgeführt werden.
- **Kein Rauschen:** Positionswechsel der Geschwister werden nicht protokolliert, nur das bewegte Element (`moved` mit altem und neuem Elternteil bzw. Platz). Abgeleiteter Status von ToDo und Meilenstein, `planer_revision` und Zeitstempel erscheinen nicht.
- **In derselben Transaktion** wie die Änderung. Ein Probelauf (`dry_run`) oder eine zurückgerollte Änderung hinterlässt keinen Eintrag.
- **Private Ideen** werden nicht protokolliert.
- **Kein Rückfüllen:** Das Protokoll beginnt mit dem Deploy.
- **Unbegrenzt:** kein Löschlauf nach Alter. Grösse, Warnung und Löschen von Hand: §10.

## 5. Aufzeichnung (Backend)

Neuer Basis-Code in `App\Core\Activity`; die Basis importiert nichts aus dem Fachcode.

- **`ActivityContext`** (je Anfrage): Akteur, `channel`, `access`, `batch_id`. Gesetzt an den Eingängen: GraphQL-Route der Oberfläche (`web`), MCP-Server (`mcp`, vor dem Aufruf der Werkzeuge; die Werkzeuge rufen intern GraphQL auf und dürfen den Kanal nicht auf `web` zurücksetzen), Assistent (`assistant`), Konsole (`console`), Scheduler und Webhooks (`system`).
- **`Activity::record(subject, action, changes)`**: schreibt einen Eintrag mit dem Kontext.
- **Vertrag `Recordable`** für Modelle: `activityType()`, `activityLabel()`, `activityProjectId()`, `activityFields()` (Erlaubnisliste), optional `activityValue(feld, wert)` für die lesbare Form. Ein Trait hört auf `created`, `updated`, `deleted`, `restored`, `forceDeleted` und schreibt nur die erlaubten Felder.
- **Bewusste Ereignisse**, wo Model-Ereignisse nicht reichen: Verschieben (`Ordering`), Mitglieder und Projektrollen, Labels einer Task, Archivieren, Papierkorb leeren, Import (ein `batch_id` je Import), Freigabe und Ablehnung von Konten, 2FA zurücksetzen, Agent anlegen, Zugang erzeugen und widerrufen, Rollen und Rechte, App-Einstellungen, OAuth-Clients, Vorlagen.
- Die bestehenden `Log::warning`-Zeilen zu Sicherheitsereignissen bleiben (Logdatei für den Betrieb) und bekommen zusätzlich einen Eintrag im Protokoll.
- `mcp_tool_calls` bleibt für die Kosten zuständig und unverändert.

## 6. Lesen (API und Rechte)

```graphql
"Protokoll, neueste zuerst. Mit projectId: Verlauf eines Projekts. Ohne: alles (nur mit activity.viewAny)."
activityLog(filter: ActivityFilter, first: Int = 50, after: String): ActivityPage!

input ActivityFilter {
  projectId: ID
  subjectType: String
  subjectId: ID
  actorId: ID          # "system" für Einträge ohne Konto
  channel: String
  action: String
  from: Date
  to: Date
  batchId: String
}

type ActivityPage { entries: [ActivityEntry!]!  hasMore: Boolean!  endCursor: String }

type ActivityEntry {
  id: ID!  createdAt: DateTime!
  actor: User  actorKind: String!   # actor null und kind user/agent: gelöschtes Konto
  channel: String!  access: String  batchId: String
  project: Project  subjectType: String!  subjectId: ID  subjectLabel: String
  action: String!  changes: [ActivityChange!]!
}
type ActivityChange { field: String!  old: String  new: String  oldUser: User  newUser: User  textChanged: Boolean! }
```

- `first` höchstens 200. Cursor über `id`, keine Seitenzahlen (die Tabelle wächst unbegrenzt).
- **Rechte:** neues Systemrecht `activity.viewAny` (Standard: Admin) und neues Projektrecht `activity.view` (Standard: Owner, Projektleitung). Ohne `projectId` braucht es `activity.viewAny`. Mit `projectId` genügt `activity.view` in diesem Projekt. Einträge ohne Projekt (Verwaltung, Sicherheit) sieht nur `activity.viewAny`. Löschen verlangt ein drittes Recht, `activity.purge` (§10).
- `actor` und `project` in einem Zug laden (kein N+1). Bezeichnungen der Felder und Aktionen übersetzt die Oberfläche, nicht die API.
- Einträge lassen sich über die API nicht ändern. Die einzige Mutation ist das Löschen nach §10.

## 7. Oberfläche

- **Verwaltung → «Protokoll»** (`/admin/activity`, Menüeintrag nur mit `activity.viewAny`): Filter Zeitraum, Person, Projekt, Bereich, Aktion, Kanal. Liste neueste zuerst, «Weitere laden». Zeile: Zeit · Person (mit Agent-Kennzeichen) · Kanal · Satz («Status von *Titel*: offen → erledigt») · Projekt. Einträge mit gleicher `batch_id` als eine aufklappbare Gruppe («Import über MCP: 12 Änderungen»).
- Auf derselben Seite oben: Anzeige des Speichers und «Protokoll aufräumen» (§10).
- **Projekt → Reiter «Verlauf»** (nur mit `activity.view`): dieselbe Liste, fest auf das Projekt gefiltert, ohne Projekt-Filter.
- **Task-Details → Abschnitt «Verlauf»**: die Einträge dieser Task, eingeklappt.
- Leerer Zustand, Ladezustand und Fehler nach den Mustern der bestehenden Seiten (`ui-consistency`). Ruhige Ansicht: Reiter und Abschnitt ausgeblendet.

## 8. Tests (mindestens)

| Bereich | Muss geprüft sein |
|---|---|
| Aufzeichnung | je Art ein Eintrag mit richtigem Akteur, Kanal, Projekt, Titel; alt/neu lesbar; lange Texte nur als Vermerk |
| Geheimnisse | Passwort, Token, 2FA-Felder erscheinen nie, auch nicht bei einem neuen Feld ohne Eintrag in der Erlaubnisliste |
| Rauschen | Verschieben erzeugt einen Eintrag, nicht einen je Geschwister; abgeleiteter Status keinen |
| Transaktion | Probelauf und zurückgerollte Änderung: kein Eintrag |
| Kanal | dieselbe Änderung über Oberfläche, MCP, Assistent und Konsole trägt den richtigen Kanal; MCP nennt den Zugang |
| Import | alle Einträge eines Imports tragen dieselbe `batch_id` |
| Momentaufnahme | nach dem Löschen eines Elements bleibt der Titel lesbar |
| Gelöschtes Konto | nach dem Löschen eines Kontos zeigen seine Einträge und Zuweisungen «Gelöschtes Konto»; in der Tabelle steht nirgends sein Name oder seine E-Mail (Test sucht danach) |
| Grösse | Stufen ok, Warnung, überschritten an den Grenzen, für MB und für Einträge je einzeln und zusammen (die weiter ausgeschöpfte zählt); Standardwerte 200 MB und 500 000 gelten ohne Einstellung; Mail genau einmal je Stufe, wieder nach Rückkehr unter die Schwelle; beide Grenzen leer: keine Warnung |
| Löschen | Jahr und alles: nur mit Recht, starker Sitzung und Passwortbestätigung; danach genau ein Eintrag «Protokoll gelöscht»; andere Jahre unberührt; Jahr mit sehr vielen Zeilen läuft in Portionen durch |
| Export | CSV eines Jahres enthält alle Einträge des Jahres, aufgelöste Namen, keine Geheimnisse; nur mit Recht |
| Rechte | ohne Recht: abgelehnt; Projektleitung sieht nur ihr Projekt und keine Verwaltungseinträge; Admin alles; Gast, Mitglied und Agent nichts |
| Abfragen | feste Zahl von Abfragen je Seite; Cursor liefert lückenlos und ohne Doppelte, auch wenn neue Einträge dazukommen |
| Nur Anfügen | Update und Delete am Modell schlagen fehl; Löschen geht nur über den Weg aus §10 |
| Architektur | `App\Core\Activity` importiert keinen Fachcode |

## 9. Entschieden und zurückgestellt

1. **Gelöschte Konten:** «Gelöschtes Konto» (Entscheid Oliver, 24.11.). Umsetzung über §4: keine Personennamen in der Tabelle.
2. **Keine IP-Adresse** im Protokoll.
3. **MCP-Werkzeug `list_activity`** für Agenten: später, eigener Auftrag.

## 10. Speicher: Grenze, Warnung, Löschen, Export

Entscheide Oliver (24.11.): Grenze in MB und als Anzahl Einträge, je mit Standardwert; bei 100 % weiter aufzeichnen und warnen; Löschen von Hand (alles oder ein Jahr); vorher Download als CSV.

### 10.1 Messen

- Grösse = Daten und Indizes der Tabelle `activity_log` in der Datenbank (MariaDB: `information_schema.TABLES`, `data_length + index_length`). Der Wert ist eine Schätzung der Datenbank und wird nicht bei jedem Eintrag neu berechnet.
- Hinter einer kleinen Schnittstelle (`ActivityStorage::sizeBytes()`, `entries()`), damit Tests und SQLite ohne `information_schema` auskommen.
- Zusätzlich die Zahl der Einträge und die Einträge je Jahr (für die Auswahl beim Löschen).
- Gemessen wird täglich durch den Scheduler und beim Öffnen der Seite «Protokoll», nicht je Anfrage.

### 10.2 Einstellungen (Verwaltung → App-Einstellungen, Abschnitt «Protokoll»)

| Schlüssel | Standard | Bedeutung |
|---|---|---|
| `activity.max_size_mb` | 200 | Maximalgrösse der Tabelle samt Indizes. Leer: diese Grenze gilt nicht. Mindestens 10. |
| `activity.max_entries` | 500 000 | Höchstzahl der Einträge (Zeilen). Leer: diese Grenze gilt nicht. Mindestens 10 000. |
| `activity.warn_at_percent` | 80 | Schwelle der Vorwarnung, 50 bis 95; gilt für beide Grenzen. |

Stufen: `ok` (unter der Schwelle), `warning` (ab Schwelle), `exceeded` (ab 100 %). Es zählt die Grenze, die **weiter ausgeschöpft** ist; Mail, Toast und Anzeige nennen, welche es ist. Sind beide leer, gibt es keine Warnung. **Auch bei `exceeded` wird weiter aufgezeichnet.**

Zu den Standardwerten (Schätzung von Stuard, **nicht gemessen**): Ein Eintrag braucht mit den fünf Indizes etwa 0,4 KB, 500 000 Einträge also rund 200 MB; die beiden Werte passen damit zusammen. Beim heutigen Betrieb (eine Person, einige Agenten, grob 200 bis 500 Änderungen an einem Arbeitstag) sind das mehrere Jahre. Die Seite zeigt den tatsächlichen Platz je Eintrag; nach den ersten Wochen auf TEST die Standardwerte daran prüfen und im Bericht nennen, falls die Schätzung nicht stimmt. Die Standardwerte stehen in der Konfiguration des Kits (`config/activity.php`), die Einstellung der App überschreibt sie.

### 10.3 Benachrichtigen

- **Mail** an alle Konten mit dem Recht `activity.purge`: einmal beim Erreichen von `warning`, einmal beim Erreichen von `exceeded` (der letzte gemeldete Stand wird gespeichert). Fällt die Grösse wieder unter die Schwelle, beginnt es von vorn. Inhalt: Grösse und Einträge mit ihren Grenzen, Prozent, welche Grenze erreicht ist, Einträge je Jahr, Link auf Verwaltung → Protokoll.
- **Toast** für dieselben Konten: einmal je Browser-Sitzung beim Laden der App, mit Link auf die Seite. Bei `exceeded` bleibt er stehen, bis er geschlossen wird.
- **Seite «Protokoll»:** Anzeige des Speichers (zwei Balken: Grösse von Grenze, Einträge von Grenze) dauerhaft oben; bei `warning` und `exceeded` als Hinweis mit dem Knopf «Protokoll aufräumen».
- `app:doctor` (Auftrag 46) meldet die Stufe mit.

### 10.4 Löschen

```graphql
"Speicher des Protokolls. Nur mit activity.viewAny."
activityStorage: ActivityStorage!
type ActivityStorage { sizeBytes: Float!  maxBytes: Float  entries: Int!  maxEntries: Int  percent: Int  limitedBy: String  level: String!  years: [ActivityYear!]!  purging: Boolean! }   # percent: der höhere der beiden Werte; limitedBy: "size" oder "entries"
type ActivityYear { year: Int!  entries: Int! }

"Löscht ein Jahr des Protokolls oder, ohne year, das ganze. Recht activity.purge, starke Sitzung, frische Passwortbestätigung."
purgeActivity(year: Int): ActivityStorage!
```

- Neues Systemrecht **`activity.purge`** (Standard: nur Admin), getrennt von `activity.viewAny`.
- **Ganzes Protokoll:** `TRUNCATE`. **Ein Jahr:** in Portionen (z. B. 5000 Zeilen) als Auftrag in der Warteschlange, weil der Webhoster Anfragen nach 60 Sekunden abbricht; danach die Tabelle neu ordnen (`OPTIMIZE TABLE`), sonst gibt die Datenbank den Platz nicht frei und die Anzeige sinkt nicht. Während der Auftrag läuft: `purging: true`, kein zweiter Start.
- Das laufende Jahr darf gelöscht werden, der Dialog sagt es ausdrücklich.
- **Das Löschen selbst wird protokolliert:** danach steht genau ein Eintrag «Protokoll gelöscht» (wer, wann, Jahr oder alles, Anzahl). Zusätzlich eine Zeile in der Logdatei und eine Mail an die Admins, wie bei den übrigen Sicherheitsereignissen.
- **Dialog:** Auswahl «Jahr» (mit Anzahl Einträge) oder «Alles», Knopf «Zuerst herunterladen», Bestätigung durch Eintippen des Jahres bzw. des Wortes «alles», dann Passwortbestätigung. Muster der bestehenden Rückfragen, kein Dialog über Dialog (Erkenntnis aus Auftrag 52).
- **Konsole:** `activity:purge --year=2026` bzw. `--all`, verlangt den Umgebungsnamen, schreibt denselben Eintrag.
- Datenbank-Sicherungen enthalten das Protokoll weiterhin, bis sie selbst verfallen. Das steht im Dialog.

### 10.5 Export als CSV

- `GET /activity/export?year=2026` (ohne `year`: alles), Recht `activity.viewAny`, starke Sitzung, gestreamt (nicht im Speicher aufbauen), Dateiname `protokoll-<umgebung>-<jahr>.csv`.
- Spalten: Zeit, Person (aufgelöster Name oder «Gelöschtes Konto»), Art des Kontos, Kanal, Zugang, Gruppe, Projekt, Bereich, ID, Titel, Aktion, Feld, alt, neu. Eine Zeile je geändertem Feld.
- UTF-8 mit BOM und Semikolon, damit Excel die Datei richtig öffnet. Werte, die mit `=`, `+`, `-` oder `@` beginnen, werden entschärft (CSV-Injection).
- Der Export wird protokolliert («Protokoll exportiert», Jahr).
