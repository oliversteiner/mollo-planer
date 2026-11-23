**Requirements**

**Ziel**

Ein Planer-Import (Dry-Run und echter Lauf) darf nicht mehr komplett abbrechen, nur weil einzelne Tasks Personen
zugewiesen bekommen, die im Projekt nicht Mitglied sind oder nicht existieren. Der Import läuft durch, die betroffenen
Zuweisungen fallen weg und werden als Warnung gemeldet.

Verweis auf den Entscheid: Option 1 aus der vorherigen Analyse (Warnung statt Fehler). Option 2 (Schalter
`skipUnknownAssignees`) ist nicht Teil dieses Auftrags, siehe Offene Punkte.

**Scope**

**In Scope**

- Unbekannte oder nicht zugehörige Zuweisungen bei Tasks werden zu "niemand zugewiesen" und erzeugen eine Warnung.
- Warnungen im Ergebnis des Dry-Runs und des echten Laufs.
- Ausgabe der Warnungen in GraphQL (`ImportResult`) und im MCP-Tool `import_plan`.

**Out of Scope**

- Personen automatisch anlegen oder einladen (Regel `planer.import.person_unknown` bleibt).
- Die Mitgliederprüfung für die Mitgliederliste der Datei (`Import.php` ca. Zeilen 457–461) bleibt unverändert.
- Schalter `skipUnknownAssignees` (Option 2).
- Keine Änderung an Fehlern für andere Fälle (Rechte, Limits, Format, Revision).

**User Stories**

- Als Projektleitung möchte ich eine Planer-Datei importieren, auch wenn Tasks Personen zugewiesen sind, die es im
  Projekt nicht gibt, damit der Import nicht an einer einzelnen Zuweisung scheitert.
- Als Projektleitung möchte ich im Dry-Run sehen, welche Zuweisungen wegfallen, damit ich vor dem echten Lauf
  entscheiden kann.
- Als API-/MCP-Nutzer möchte ich die Warnungen strukturiert erhalten (Pfad, E-Mail, Hinweis).

**Functional Requirements**

- Ein Task mit unbekannter oder nicht zugehöriger `assigneeEmail` wird importiert, ohne Zuweisung.
- Der Import ergibt keinen Fehler `assignee_not_member` mehr für Tasks; der Lauf ist erfolgreich, wenn keine anderen
  Fehler vorliegen.
- Jede betroffene Zuweisung erscheint einmal in `warnings` mit: Pfad (z. B. `milestones[0].todos[1].tasks[2]`), E-Mail,
  Hinweistext (übersetzt in `lang/de` und `lang/en`).
- Dry-Run und echter Lauf liefern dieselben Warnungen.
- Bei einem echten Lauf wird nichts in der Datenbank gespeichert, wenn ein harter Fehler vorliegt (Verhalten wie
  bisher).
- Bestehende Tasks mit unbekannter Zuweisung: die aktuelle Zuweisung bleibt erhalten, es wird nur gewarnt (siehe Key
  Decisions).

**Non-Functional Requirements**

- Keine Verhaltensänderung für Importe ohne unbekannte Zuweisungen.
- Rechte und Regeln bleiben unverändert: Die Zuweisung selbst läuft weiter über `Rights::mayAssignTask`.

**Technical Design**

**Current Implementation**

- `backend/app/Planer/Validator.php` (ca. Zeilen 254–258) prüft jede `assigneeEmail` gegen die Mitglieder der Datei und
  liefert `assignee_not_member`.
- `backend/app/Planer/Import.php`:
    - Zeilen 159–170: bricht ab, sobald Fehler vorliegen, und schreibt nichts.
    - Zeilen 188–192 und 202–208: `rollBack()` bei Fehlern, nur die Fehlerliste wird zurückgegeben.
    - `assigneeOf()` (Zeilen 892–909): meldet Fehler, wenn der Nutzer `null` ist oder `roleIdOf($user) === null`, und
      gibt `false` zurück.
    - `task()` (Zeilen 769–777): `false` wird zu `null` und `assigneeGiven = false` gesetzt, damit kein "niemand"
      gelesen wird.
    - `updateTask()` nutzt `$assigneeGiven` und `$assignee`, um Änderungen zu erkennen.
- `assign()` (Zeilen 911–921) prüft die Berechtigung über `Rights::mayAssignTask`.
- Das Ergebnis `ImportResult` mit Fehlerliste, Änderungen und optional dem Dokument wird in GraphQL und im MCP-Tool
  `import_plan` ausgegeben.

**Key Decisions**

- **Warnung statt Fehler:** Der Validator-Eintrag für Tasks wird zur Warnung umgewandelt. Der Fehlercode
  `assignee_not_member` bleibt für Fälle, in denen er außerhalb von Tasks noch gebraucht wird (sonst entfernen).
- **Neue Tasks:** Ohne gültige Zuweisung wird der Task ohne `assigned_user_id` angelegt.
- **Bestehende Tasks:** Die aktuelle Zuweisung bleibt unverändert, nur die Warnung wird gesammelt. Begründung: Eine
  unbekannte Person in der Datei soll keine bestehende gültige Zuweisung löschen. Gegenvorschlag wäre "auf niemand
  setzen"; das ist vor der Umsetzung zu bestätigen.
- **Keine Schalter-Option:** Kein neuer Parameter, damit Schema, Frontend-Codegen und MCP-Doku unverändert bleiben.

**Proposed Changes**

- `Validator.php`: Der Eintrag `assignee_not_member` für Tasks wird als Warnung statt Fehler geführt (Warnungsliste im
  Validator oder über den Import).
- `Import.php`:
    - `assigneeOf()` gibt bei unbekannter Person `null` zurück und sammelt eine Warnung (neue Methode `warn()` neben
      `error()`).
    - `task()`: Bei `null` aus `assigneeOf()` bei neuem Task keine Zuweisung; bei bestehendem Task
      `assigneeGiven = false` setzen.
    - Die Warnungen werden in `$this->warnings` gesammelt und mit dem Ergebnis zurückgegeben (auch im Dry-Run).
- `ImportResult` (PHP-Seite): neues Feld `warnings`.
- `backend/graphql/schema.graphql`: neuer Typ `ImportWarning { path: String!, email: String!, message: String! }` und
  Feld `warnings: [ImportWarning!]!` an `ImportResult`. Danach `composer schema:frontend` und im Frontend
  `npm run codegen`.
- MCP-Tool `import_plan`: `warnings` in der Antwort ausgeben und in der Tool-Beschreibung erwähnen.
- `lang/de` und `lang/en`: Schlüssel `planer.import.assignee_unknown_warning` (Text mit `:email`).

**Data Models / Contracts**

```php
/** @var list<array{path: string, email: string, message: string}> */
private array $warnings = [];

private function warn(string $path, string $email): void
{
    $this->warnings[] = [
        'path' => $path,
        'email' => $email,
        'message' => __('planer.import.assignee_unknown_warning', ['email' => $email]),
    ];
}
```

```graphql
type ImportWarning {
    path: String!
    email: String!
    message: String!
}
```

**File Structure**

- Geändert: `backend/app/Planer/Validator.php`, `backend/app/Planer/Import.php`, `backend/graphql/schema.graphql`,
  `frontend/graphql/schema.graphql` (generiert), `lang/de/planer.php`, `lang/en/planer.php`, MCP-Tool-Klasse für
  `import_plan` (Datei beim Umsetzen suchen).
- Tests: `backend/tests/...` (vorhandene Import-Tests erweitern).

**Risks**

- **Verdeckter Datenverlust:** Ohne Warnung im Ergebnis würden Zuweisungen still verloren gehen. Mitigation: Warnungen
  sind Pflicht im Ergebnis und im Dry-Run.
- **Bestehende Zuweisungen:** Mitigation: bestehende Zuweisung bleibt (siehe Key Decisions).
- **Rechte:** Die Warnung darf keine Rechte umgehen. Mitigation: Zuweisungen laufen weiter durch
  `Rights::mayAssignTask`.
- **Frontend:** Falls das Frontend das Importergebnis anzeigt, muss die Anzeige die Warnungen zeigen (vor Umsetzung
  prüfen).

**Testing**

**Validation Approach**

Tests in der bestehenden Import-Testsuite (Backend, PHPUnit/Pest über `php artisan test`) und zusätzlich
`composer test:mariadb`, da Abfragen und Sperren berührt werden.

**Key Scenarios**

- Datei mit Task, dessen `assigneeEmail` kein Projektmitglied ist: echter Lauf erfolgreich, Task ohne Zuweisung, eine
  Warnung mit Pfad und E-Mail.
- Dasselbe im Dry-Run: gleiche Warnung, nichts in der Datenbank geändert.
- Neuer Task mit unbekannter Zuweisung: angelegt ohne `assigned_user_id`.
- Bestehender Task mit gültiger Zuweisung, Datei nennt unbekannte Person: Zuweisung bleibt, Warnung vorhanden.
- Datei ohne unbekannte Zuweisungen: `warnings` leer, Verhalten unverändert.
- GraphQL-Mutation `importPlan` liefert `warnings` mit den Feldern `path`, `email`, `message`.
- MCP-Tool `import_plan` liefert `warnings` in der Antwort.

**Edge Cases**

- Unbekannte Person und zugleich ein anderer harter Fehler: kein Schreiben, Fehler wie bisher, Warnungen zusätzlich.
- Person existiert, ist aber nicht Mitglied des Projekts: Warnung (wie bisher Fehler).
- Zuweisung fehlt in der Datei (leer): keine Warnung.
- Mehrere Tasks mit derselben unbekannten E-Mail: je eine Warnung pro Task.
- Revisions-Verhalten: Ein Lauf nur mit Warnungen zählt als Lauf mit Änderungen, wenn sich Daten ändern; Dry-Run erhöht
  die Revision nie.

**Test Changes**

- Bestehende Tests, die `assignee_not_member` für Tasks erwarten, anpassen.
- Neue Tests für Warnungen in Dry-Run und echtem Lauf.
- Keine Tests für Option 2 (nicht Teil des Auftrags).

**Plan-Ausgabe**

**Offene Punkte (vor Umsetzung klären)**

- Bestehende Tasks: Zuweisung bleibt bei unbekannter Person (Vorschlag) oder wird auf "niemand" gesetzt?
- Soll später zusätzlich Option 2 (`skipUnknownAssignees`) für API-Aufrufer kommen, die einen harten Fehler brauchen?
- Ob das Frontend das Importergebnis anzeigt und die Warnungen dort sichtbar sein müssen.

**Ablage**

Diese Datei gehört als `next-work/29-import-unbekannte-zuweisungen.md` in den Ordner `next-work/`. Der Plan wurde im
Planungsmodus erstellt und nicht als Datei abgelegt. Beim Umsetzen nach `erledigt/` verschieben (`git mv`) und
`docs/umsetzung.md` nachführen.

**Delivery Steps**

*** Step 1: Warnungen im Import-Kern sammeln**

Ein Task mit unbekannter oder nicht zugehöriger Zuweisung wird ohne Zuweisung importiert, und die Warnung landet im
Ergebnis.

- `Import::assigneeOf()` gibt bei unbekannter Person `null` statt `false` zurück und ruft `warn()` auf.
- `Import::task()` setzt bei neuem Task keine Zuweisung; bei bestehendem Task bleibt die Zuweisung unverändert
  (`assigneeGiven = false`).
- `Validator.php` (ca. Zeilen 254–258) führt die Zuweisung nicht mehr als Fehler `assignee_not_member`.
- `$warnings` wird gesammelt und im Ergebnis von Dry-Run und echtem Lauf zurückgegeben.

**Step 2: Warnungen in GraphQL, MCP und Sprachdateien ausgeben**

Die Warnungen sind über die Mutation `importPlan` und das MCP-Tool `import_plan` sichtbar.

- `backend/graphql/schema.graphql`: Typ `ImportWarning` und Feld `warnings` an `ImportResult`.
- `composer schema:frontend` und `npm run codegen` im Frontend.
- MCP-Tool `import_plan`: `warnings` in der Antwort und in der Tool-Beschreibung.
- `lang/de/planer.php` und `lang/en/planer.php`: Schlüssel `planer.import.assignee_unknown_warning`.
- Falls das Frontend das Importergebnis anzeigt, Warnungen dort ebenfalls anzeigen.

**Step 3: Tests für Dry-Run, echten Lauf und Warnungen ergänzen**

Die Tests decken Warnungen, unveränderte Bestandsdaten und Fehlerfälle ab.

- Tests für neuen Task mit unbekannter Zuweisung (Dry-Run und echter Lauf).
- Test für bestehenden Task mit gültiger Zuweisung und unbekannter Person in der Datei (Zuweisung bleibt).
- Test für GraphQL-Antwort mit `warnings` und für MCP-Antwort.
- Bestehende Tests für `assignee_not_member` bei Tasks anpassen.
- `php artisan test`, `composer test:mariadb`, `php artisan lighthouse:validate-schema`, `./vendor/bin/pint`.

**Step 4: Stand dokumentieren und Auftrag abschließen**

Die Dokumentation und die Auftragsdatei spiegeln den neuen Stand.

- `docs/umsetzung.md` um den Stand ergänzen (Warnungen beim Import).
- `next-work/29-import-unbekannte-zuweisungen.md` oben auf `Status: erledigt (<Commit>)` setzen und nach
  `next-work/erledigt/` verschieben (`git mv`).
