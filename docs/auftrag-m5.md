# Auftrag Meilenstein M5 „Synchronisation“ (T2, T3, W1)

Stand: 2026-11-18, Planung. Grundlage: `docs/planer-abgleich.md` §1a, §4.4 und §10, `docs/umsetzung.md` (T2, T3, W1), `spec/format-v2.md` §5.2. Rolle: Auftrag für die Umsetzung (Bob). M4 ist abgenommen (`docs/abnahme-m4.md`).

Ziel: Eine Planer-Datei in einem beliebigen Repository bleibt mit der App im Gleichstand. Lokal mit `mollo-planer pull/push/sync`, auf GitHub automatisch über eine Action, die die App nach jeder Änderung per Webhook anstößt.

---

## 0. Was schon da ist (nicht neu bauen)

- MCP `export_plan` (`{ document }`, kanonisch, `revision` = `projects.planer_revision`) und `import_plan` (`plan`, `mode` `full`/`partial`, `dry_run`, `notify_assignees`; Ergebnis `ok`, `document`, `changes`, `errors`, `idMap`; Fehler als MCP-Fehler mit demselben JSON). `full` lehnt eine falsche Revision ab.
- Nur die App zählt `revision` (`App\Planer\PlanerRevision`, +1 je Transaktion, nach dem Commit). `changed: true` steht in Schema, Spezifikation, `Canonical.php` und `tools/planer/core/planer-canonical.mjs`; die Helfer setzen es schon (`planer-commands.mjs`).
- `tools/planer/cli`: `validate`, `template`, `json`, `render`, `convert`, Helfer. Kanonisches JSON in `core` (byte-gleich mit PHP, Fixture-Tests).
- `/mcp`: `auth:sanctum`, Fähigkeit `mcp`, `throttle:mcp` = 60 Anfragen pro Minute je Person. Zugang 90 Tage.
- Queue `database`; Mails laufen schon über die Queue. Scheduler hat nur `app:prune-unverified-users`. In `docs/deploy-template.md` steht **kein** Cron für `schedule:run` und kein Worker.
- CI: `.github/workflows/tools.yml` (Node 24, `npm test` in `tools/planer`).

## 1. Entscheidungen (Oliver, 2026-11-18)

1. **Webhook mit zwei Arten** (ersetzt §10 „Signatur HMAC“ für GitHub): GitHub `repository_dispatch` nimmt keine HMAC-Signatur an, nur einen Token.
   - **GitHub:** Repository `owner/name` und ein fein eingeschränkter Token (nur dieses Repository, „Contents: write“). Die App ruft `POST https://api.github.com/repos/{owner}/{repo}/dispatches` auf.
   - **Allgemein:** URL und Geheimnis, Body mit HMAC-SHA256 signiert.
   - Token bzw. Geheimnis verschlüsselt gespeichert, nach dem Speichern nie wieder angezeigt, nur ersetzbar.
2. **Das Repository `mollo-planer` ist öffentlich.** Die Action holt das Werkzeug mit `actions/checkout` ohne Token. Kein npm-Paket jetzt (Auslagerung bleibt bei §4.5).
3. **Queue beim Webhoster:** Der Scheduler startet jede Minute `queue:work --stop-when-empty`. Der Cron-Eintrag für `schedule:run` kommt in `docs/deploy-template.md`; Oliver trägt ihn im Panel des Webhosters ein.

## 2. T2 `pull`, `push`, `sync`, `pull --watch` (in `tools/planer/cli`)

### 2.1 Verbindung

- Neues Modul `tools/planer/cli/planer-remote.mjs` (nicht in `core`: das Frontend nutzt `core` und braucht kein Netz). Keine Abhängigkeit: Node `fetch` gegen `POST <url>/mcp`, JSON-RPC wie ein MCP-Client (`initialize`, Sitzungs-Header übernehmen, falls der Server einen schickt, dann `tools/call`). Zeitlimit 30 s.
- **Konfiguration**, Vorrang von oben nach unten:
  - URL: Umgebung `MOLLO_PLANER_URL`, sonst `url` in `.mollo-planer`.
  - Token: Umgebung `MOLLO_PLANER_TOKEN`, sonst `token` in `.mollo-planer`. **Keine** Option `--token` (Shell-Verlauf).
  - Projekt: `--project <id>`, sonst Umgebung `MOLLO_PLANER_PROJECT`, sonst `projects["<Pfad der Datei relativ zur Konfigurationsdatei>"]` in `.mollo-planer`.
- `.mollo-planer` ist JSON (`{ "url": …, "token": …, "projects": { "docs/x.planer.json": 7 } }`), gesucht vom Ordner der Datei aufwärts bis zur Git-Wurzel. Ist sie in Git eingecheckt oder nicht in `.gitignore`: Warnung auf stderr. `pull --project` trägt die Zuordnung ein, wenn die Datei existiert (Token nie anfassen).
- Fehler mit klarem deutschem Text, ohne Token in der Ausgabe:
  - 401/403: „Zugang ungültig oder abgelaufen. Neuer Zugang: Profil › Zugang für Claude.“
  - 429: „Zu viele Anfragen. In einer Minute erneut versuchen.“
  - Netz/Zeitlimit: mit URL.
  - MCP-Fehler aus `import_plan`: jede Meldung mit Pfad (`errors[].path`).

### 2.2 Befehle

| Befehl | Verhalten |
|---|---|
| `pull [datei] [--project <id>] [--force]` | `export_plan`. Datei fehlt: anlegen. Datei hat `changed: true`: Abbruch (Exit 3) mit „Lokale Änderungen. Zuerst `push` oder `sync`, oder `pull --force` (verwirft sie).“ Kanonischer Inhalt gleich: nichts schreiben, „unverändert (Revision N)“. Sonst kanonisch schreiben, „Revision A → B“. |
| `push [datei] [--partial] [--yes]` | Lokal prüfen (wie `validate`). Datei ohne `changed`: „Nichts einzutragen.“ Sonst `import_plan` mit `dry_run`: Revision anders als in der App → Abbruch (Exit 4) „Die App ist bei Revision B, die Datei bei A. Zuerst `pull` (lokale Änderungen gehen dabei nicht verloren, `pull` bricht ab) …“, mit Hinweis, wie man die eigenen Änderungen neu anwendet. Änderungen zusammengefasst ausgeben (Anzahl je Art, Löschungen mit Titel). Enthält der Trockenlauf Löschungen und fehlt `--yes`: Abbruch (Exit 5) mit der Liste. Dann echter Import und das zurückgegebene `document` kanonisch schreiben (echte IDs, neue Revision, kein `changed`). Ohne Änderungen im Trockenlauf: nur `changed` entfernen. |
| `sync [datei] [--yes]` | `changed` gesetzt: `push`, danach `pull`. Sonst `pull`. |
| `pull --watch [--interval 60]` | Wie `pull` in einer Schleife, Abstand mindestens 10 s (Standard 60). Bei `changed` in der Datei: Hinweis einmal ausgeben und weiter warten, nicht abbrechen. Strg-C beendet sauber. Fehler 429/Netz: nächste Runde, nicht abbrechen. |

- Exit-Codes in `USAGE` und `SKILL.md` aufführen (0 ok/unverändert, 1 Aufruf falsch, 2 Datei ungültig, 3 lokale Änderungen, 4 Revision, 5 Löschungen ohne `--yes`, 6 Verbindung/Zugang).
- `push` mit `--partial`: `mode: partial`, keine Revisionsprüfung durch die App; die CLI prüft trotzdem und verlangt dann `--force`, wenn die Revision abweicht.
- Das Werkzeug committet nie.

### 2.3 Tests

- Einheitstests mit gefälschtem `fetch` für Konfiguration (Vorrang, Suche, Warnung bei eingecheckter Datei), Fehlertexte, Exit-Codes, `--watch` (zwei Runden mit Zeit-Attrappe).
- **Integrationstest gegen den echten MCP-Server** (§4.4, keine Nachbildung): ein Skript, das `php artisan serve` auf einer eigenen SQLite-Datei startet, `migrate:fresh --seed --seeder=DemoSeeder` ausführt, einen Zugang mit Fähigkeit `mcp` erzeugt (kleiner Artisan-Befehl nur für `local`/`testing`, oder `tinker`) und dann die Node-Tests mit `MOLLO_PLANER_TEST_URL`/`…_TOKEN` laufen lässt. Ohne diese Variablen werden die Integrationstests übersprungen. In CI: `tools.yml` um einen Job mit PHP erweitern, wenn es ohne großen Umbau geht; sonst lokal (`composer test:planer-remote`) und in `umsetzung.md` vermerken.
- Fälle mindestens:
  1. `pull` neue Datei; zweites `pull`: unverändert, Datei nicht angefasst (mtime).
  2. Helfer ändert lokal → `changed`; `pull` bricht ab; `pull --force` verwirft.
  3. `push` mit neuem Task (`k-new-1`) → echte ID, Revision +1, kein `changed`; danach `pull` unverändert.
  4. Änderung in der App (zweiter Client über GraphQL) → `push` bricht mit Revisionshinweis ab.
  5. `push` mit Löschung ohne/mit `--yes`; `sync`.
  6. Abgelaufener/falscher Token → Exit 6 mit Text.
- `tools/planer/skills/planer/SKILL.md`: Abschnitt „Mit der App abgleichen“: vor der Arbeit `pull`, Revision nie von Hand ändern, `push` zeigt erst den Trockenlauf, Löschungen nur mit `--yes` nach Rückfrage beim Menschen, nie committen ohne Auftrag.

## 3. T3 Vorlage für die GitHub Action

- `tools/planer/templates/github-action/planer-export.yml` und `README.md` daneben (deutsch).
- Auslöser: `repository_dispatch` (`types: [planer-export]`), `schedule` (stündlich, Minute ungleich 0, z. B. `17 * * * *`), `workflow_dispatch`.
- `concurrency` je Datei, kein paralleler Lauf. `permissions: contents: write`.
- Schritte: Ziel-Repository auschecken; `mollo-planer` öffentlich auschecken (`repository: <owner>/mollo-planer`, `ref` aus Variable `MOLLO_PLANER_REF`, Standard `main`, `sparse-checkout: tools/planer` und `spec`, Pfad außerhalb des Arbeitsbaums oder in `.gitignore`); Node 24; `pull` mit `MOLLO_PLANER_URL`/`MOLLO_PLANER_TOKEN` aus Secrets, Datei und Projekt aus Variablen `PLANER_FILE`, `PLANER_PROJECT`; nur bei Änderung committen und pushen, Nachricht `Docs: planer export rev <N>`, Autor `github-actions[bot]`.
- Bricht `pull` mit Exit 3 ab (jemand hat eine Datei mit `changed` committet): Job schlägt fehl, Meldung sagt, wer was tun muss (lokal `push`).
- README: Einrichtung in vier Schritten (Zugang erzeugen, Secrets und Variablen setzen, Datei kopieren, Webhook in der App einrichten), Hinweis: der Zugang läuft nach 90 Tagen ab, die Action schlägt dann mit Exit 6 fehl.
- Prüfung: `actionlint`, falls vorhanden; sonst YAML-Prüfung im Node-Test. Echter Lauf in einem Test-Repository: Abnahme durch Oliver.
- `docs/claude-connector.md`: kurzer Abschnitt „Planer-Datei im Repository“ mit Verweis auf README und `SKILL.md`.

## 4. W1 Webhook

### 4.1 Daten

- Tabelle `project_webhooks` (eine Zeile je Projekt, `project_id` eindeutig, Löschen mit dem Projekt): `kind` (`github`/`generic`), `target` (GitHub: `owner/name`; Allgemein: URL), `secret` (Cast `encrypted`, nie in `$visible`/`toArray`), `last_sent_revision`, `last_sent_at`, `last_failed_at`, `last_error` (kurz, ohne Header oder Body der Antwort), Zeitstempel. `db-schema.ts` nachführen.
- Prüfung: GitHub `target` Muster `owner/name`. Allgemein: nur `https` (in `local`/`testing` auch `http`), in Produktion keine Ziele auf private, Loopback- oder Link-Local-Adressen (nach DNS-Auflösung prüfen, beim Speichern und beim Senden), keine Weiterleitungen folgen.

### 4.2 Schnittstellen

- `Project.webhook: ProjectWebhook` (nur mit `project.update`, sonst `null`): `kind`, `target`, `hasSecret`, `lastSentAt`, `lastSentRevision`, `lastFailedAt`, `lastError`. Kein Feld liefert das Geheimnis.
- `setProjectWebhook(projectId, input { kind, target, secret })`: Geheimnis beim Anlegen Pflicht, beim Ändern optional (fehlt → bleibt). `removeProjectWebhook(projectId)`. `testProjectWebhook(projectId)`: sendet sofort synchron, Ergebnis `{ ok, message }`, ändert die Status-Felder. Alle drei mit `project.update`; archiviertes Projekt: abgelehnt wie andere Änderungen. Zählt **nicht** als Änderung am Plan (keine Revision).

### 4.3 Senden

- Nach `PlanerRevision::touch` (nach dem Commit) für jedes Projekt mit Webhook einen Job `SendPlanerWebhook` einreihen, **gebündelt**: `ShouldBeUnique` je Projekt, Verzögerung `max(60 s, last_sent_at + 5 min − jetzt)`. Der Job liest beim Laufen die aktuelle Revision; gleich `last_sent_revision` → nichts senden.
- **GitHub:** `POST https://api.github.com/repos/{owner}/{repo}/dispatches`, Header `Authorization: Bearer <token>`, `Accept: application/vnd.github+json`, `X-GitHub-Api-Version: 2022-11-28`, Body `{ "event_type": "planer-export", "client_payload": { "project_id", "revision", "app_url" } }`. Erfolg = 204.
- **Allgemein:** `POST <url>`, Body `{ "event": "planer.revision", "project_id", "revision", "sent_at" }`, Header `X-Mollo-Event: planer.revision`, `X-Mollo-Signature: sha256=<HMAC-SHA256 des rohen Bodys mit dem Geheimnis>`. Erfolg = 2xx.
- Zeitlimit 10 s. Fehler: drei weitere Versuche nach 1, 5 und 15 Minuten (`tries`, `backoff`). Danach `last_failed_at`, `last_error` (z. B. „HTTP 401“, „Zeitüberschreitung“). Erfolg setzt `last_sent_*` und löscht `last_failed_at`/`last_error`. Keine Mail. Token/Geheimnis nie loggen.

### 4.4 Queue beim Webhoster

- `routes/console.php`: `Schedule::command('queue:work --stop-when-empty --max-time=55')->everyMinute()->withoutOverlapping()`.
- `docs/deploy-template.md`: Abschnitt „Cron“ mit dem Eintrag `* * * * * cd <api-pfad> && <php-pfad> artisan schedule:run >> /dev/null 2>&1`, Hinweis: ohne ihn gehen weder Mails noch Webhooks raus. In `umsetzung.md` als offenen Schritt für Oliver vermerken.

### 4.5 Oberfläche

- Projekteinstellungen, Abschnitt „Benachrichtigung bei Änderungen“ (nur mit `project.update` sichtbar), unter „Planer-Datei“:
  - Art: GitHub / Allgemein.
  - GitHub: Feld „Repository“ (`owner/name`), Feld „Token“ (Passwortfeld), Hinweis „Fein eingeschränkter Token, nur dieses Repository, Berechtigung Contents: write.“
  - Allgemein: URL, Geheimnis.
  - Nach dem Speichern: „Token gespeichert“ mit Knopf „Ersetzen“, nie den Wert.
  - Knöpfe „Speichern“, „Testen“ (Meldung mit Ergebnis), „Entfernen“ (Bestätigung).
  - Statuszeile: „Zuletzt gesendet am … (Revision N)“ bzw. „Letzte Benachrichtigung fehlgeschlagen am …: <Fehler>“.
- DE/EN, hell/dunkel, Telefonbreite.

### 4.6 Tests

- Bündelung: mehrere Änderungen in kurzer Zeit → ein Job, ein POST mit der letzten Revision; gleiche Revision → kein POST.
- Form beider Anfragen (`Http::fake`), Signatur nachrechenbar.
- Wiederholungen und Fehlerstatus; Erfolg löscht ihn.
- Rechte (David, Manager mit/ohne `project.update`, archiviert), Geheimnis nie in GraphQL-Antworten oder Logs, Prüfung der Ziel-URL (privat/Loopback in Produktion abgelehnt).
- Import über MCP (`push`) löst den Webhook aus; `export_plan` nicht.

## 5. Ablauf und Abschluss

T2 → T3 → W1, je Teil eigene Commits (Vorarbeit, Funktion, Tests, Doku). `docs/umsetzung.md` „Stand“ nachführen. Alle Prüfungen grün (`php artisan test`, `composer test:mariadb`, `npm test` in `frontend/` und `tools/planer/`, Integrationstest §2.3, Lint, Typecheck, Build). Im Browser die Webhook-Einstellungen ansehen. Dann anhalten; die Abnahme macht die Planung mit frischen Demo-Daten (`php artisan migrate:fresh --seed --seeder=DemoSeeder` durch Oliver).

Rückfragen an die Planung in `docs/umsetzung.md` unter „Rückfrage an die Planung“ und anhalten, statt zu raten. Besonders: falls der MCP-Server ohne `initialize` keine `tools/call` annimmt oder die Laravel-MCP-Version Sitzungen anders handhabt als hier beschrieben.

## 6. Was die Abnahme prüfen wird (Vorschau)

- CLI gegen die lokale App: Fälle aus §2.3 von Hand, Texte und Exit-Codes.
- Webhook „Allgemein“ gegen einen lokalen Empfänger (Signatur prüfen), Bündelung, Fehlerstatus in der Oberfläche.
- ⏸ Oliver: Webhook „GitHub“ und Action in einem Test-Repository, Cron beim Webhoster.
