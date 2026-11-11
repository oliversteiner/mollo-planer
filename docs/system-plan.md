# Plan: Bereich System (Status und Datenbank-Doku)

Stand: 2026-11-11. Status: bestätigt, in Umsetzung. Vorlage ist die FC-Aarau-Datenbank (`~/www/fcadb`): Abschnitt „System“ in der Seitenleiste, Seite `/system/status`, Seite `/system/db`.

## Entscheidungen

1. **Zugriff.** Status für alle angemeldeten Benutzer. Datenbank-Doku nur für Benutzer mit `USERS_MANAGE` (Rolle Admin).
2. **Status ohne Schema-Änderung.** Die GraphQL-Prüfung ruft `POST /api` mit `{ __typename }` auf. Die Antwort zeigt, dass die API erreichbar ist. Versionsangaben gibt es nicht, weil die App noch keine Version führt.
3. **Umfang der Datenbank-Doku.** Nur die Fachtabellen. Tabellen von Laravel-Paketen (Tokens, Passkeys, Cache, Jobs) bleiben weg. Personenbezogene Spalten (E-Mail, Passwort, 2FA-Geheimnisse, Avatar) sind als privat markiert.
4. **Überschrift.** Der Abschnitt „Verwaltung“ heißt überall „System“, also auch in den Brotkrumen.

## Umfang

### Seitenleiste
- Überschrift `nav.admin` von „Verwaltung“ zu „System“ (de und en).
- Neue Einträge: „Status“ (`/system/status`, alle Angemeldeten) und „Datenbank“ (`/system/db`, nur `USERS_MANAGE`).
- Bestehende Einträge Benutzer, Label-Sets und Einstellungen bleiben.

### Backend
- `App\Http\Controllers\StatusController`: `GET /status`, öffentlich, gedrosselt (30 pro Minute). Antwort `{ backend, database, checkedAt }`, 200 bei Datenbank ok, sonst 503. Keine Versionen, keine Fehlerdetails.
- Tests: Antwort bei funktionierender Datenbank, Antwort 503 wenn die Datenbank nicht antwortet.

### Frontend
- `pages/system/status.vue`: drei Zeilen (Backend, Datenbank, GraphQL-API) mit Antwortzeit und Badge, Knopf „Erneut prüfen“.
- `pages/system/db.vue` mit `db/index.vue` (leitet auf die erste Tabelle weiter) und `db/[table].vue`. Die Seiten liegen unter der Middleware `admin` (`USERS_MANAGE`).
- `utils/db-schema.ts`: Tabellenliste, Spalten (Typ, Schlüssel, Null, Standard, Fremdschlüssel, Enum-Werte, privat, generiert), Indizes.
- Texte in `i18n/locales/de.json` und `en.json` unter `db` und `status`.
- Test `tests/unit/db-schema.test.ts`: vergleicht die dokumentierten Spalten mit den Migrationen (Erstellung und spätere Änderungen mit `dropColumn`).

## Bau-Reihenfolge

1. Plan und Branch `feature/system` von `feature/roles`.
2. Backend: Status-Endpunkt mit Tests.
3. Seitenleiste: Überschrift und Einträge.
4. Statusseite mit Test der Anzeige.
5. Datenbank-Doku: Fakten aus dem Schema, Texte, Seiten, Migrations-Test.
6. Dokumentation nachführen (`CLAUDE.md`, `README`, dieser Plan), Lint, Typecheck, alle Tests, Prüfung im Browser.

## Stand

- [x] 1. Plan und Branch `feature/system` (von `feature/roles`)
- [x] 2. Backend-Status: `StatusController`, `GET /status`, Tests (3 Tests)
- [x] 3. Seitenleiste: Überschrift „System“, Einträge „Datenbank“ (nur Admins) und „Status“ (alle)
- [x] 4. Statusseite `/system/status`: Backend, Datenbank, GraphQL (`GET`-Abfrage `{ __typename }`), Erneut prüfen
- [x] 5. Datenbank-Doku `/system/db`, `/system/db/[table]`: 11 Fachtabellen, Struktur aus dem Schema erzeugt, Texte in de und en, Test gegen die Migrationen (24 Tests)
- [ ] 6. Prüfung im Browser (Admin und Nicht-Admin), Commit

## Abweichungen

- Die Texte der Statusseite liegen unter `systemStatus`, nicht unter `status`: Dieser Schlüssel enthält schon die Texte des Aufgabenstatus.
- Die Statusseite hat keinen eigenen Komponententest. Geprüft wird sie im Browser.
- Die Doku nennt die Tabelle `label_task` (Zuordnung Labels zu Tasks) so, wie sie in der Datenbank heißt.
