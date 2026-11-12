# Mollo Planer

Web-App zur Projektverwaltung: Milestones → ToDos → Tasks → Kanban, dazu ein Dashboard.

- `backend/`: Laravel mit GraphQL-API (Lighthouse), Anmeldung über Fortify
- `frontend/`: Nuxt (folgt)
- `docs/`: Entscheidungen und Bauplan (`backend-plan.md`, `graphql-kontext.md`)

## Ersten Admin anlegen

Auf einer neuen Installation gibt es noch keinen Admin. Den ersten legt der Artisan-Befehl `app:create-admin` an. Ein Seeder dafür existiert bewusst nicht, damit nie ein Konto mit bekanntem Passwort entsteht. Im Ordner `backend/` ausführen:

```bash
php artisan app:create-admin "<Name>" <E-Mail> --password
```

Der Befehl fragt das Passwort zweimal im Terminal ab. Es wird nie als Parameter übergeben und nirgends gespeichert.

Ohne `--password` verschickt der Befehl stattdessen eine Einladung per Mail mit Link zum Setzen des Passworts. Dafür muss der Mail-Versand in der `.env` eingerichtet sein. Mit `MAIL_MAILER=log` landet die Mail nur im Log, deshalb lokal `--password` verwenden.

## Demo-Daten

Zum Ausprobieren der API (zum Beispiel mit Postman) gibt es Demo-Daten wie in der GraphQL-Demo:

| Person | E-Mail | Rolle |
|---|---|---|
| Anna Keller | anna.keller@example.ch | Admin |
| Ben Müller | ben.mueller@example.ch | Owner „Website-Relaunch“ |
| Chiara Rossi | chiara.rossi@example.ch | Managerin „Website-Relaunch“, Owner „Sommerfest 2027“ |
| David Frei | david.frei@example.ch | Mitglied in beiden Projekten |

Dazu zwei Projekte mit Milestones, ToDos, Tasks und Kommentaren.

Die Demo-Daten laufen nur lokal, nie in Produktion. Alle Befehle im Terminal im Ordner `backend/` ausführen.

### Einspielen (leere Datenbank)

```bash
php artisan migrate
php artisan db:seed --class=DemoSeeder
```

Der Befehl fragt nach einem Passwort, das für alle vier Personen gilt. Leer lassen, dann wird eines erzeugt. Ist die Datenbank nicht leer, bricht er ab und ändert nichts.

### Zurücksetzen

```bash
php artisan migrate:fresh --seed --seeder=DemoSeeder
```

**Achtung:** Das löscht alle Tabellen und damit alle Daten, auch eigene Einträge. Danach ist der Stand wieder wie nach dem ersten Einspielen, mit neuem Passwort und neuen 2FA-Secrets.

### Anmelden

Am Ende zeigt der Befehl einmalig an (danach nirgends mehr gespeichert, also gleich notieren):

- das Passwort für alle Demo-Personen
- je Person das 2FA-Secret und eine `otpauth://`-Adresse, zum Eintragen in eine Authenticator-App
- je Person 8 Wiederherstellungscodes, jeder nur einmal gültig

**Die Demo-Daten starten mit ausgeschalteter 2FA:** Für die Anmeldung genügt das Passwort, auch in Postman.

**2FA wieder einschalten** (zum Beispiel um den Login mit Code zu testen): als Admin (Anna Keller) in Postman „4 Admin / 2FA ein- oder ausschalten“ mit `true` ausführen. Dann braucht die Anmeldung Passwort **und** 2FA-Code (oder einen Wiederherstellungscode), mit den oben ausgegebenen 2FA-Secrets. Mit `false` wieder ausschalten. Das geht nur ausserhalb Produktion; auf dem Produktivserver ist die 2FA immer Pflicht.

### Postman-Collection

Die Collection liegt im Format Postman Collection v3 (YAML, eine Datei pro Anfrage) in `docs/postman/`, dem lokalen Ordner des Postman-Workspaces (`.postman/resources.yaml`). In Postman den Workspace mit diesem Ordner verbinden (Local View), dann erscheint die Collection „Mollo Planer“. Änderungen in Postman landen direkt in den YAML-Dateien und können committet werden.

Sie enthält die Anmeldung und Beispiel-Anfragen für alle wichtigen Abfragen und Änderungen, vorbelegt mit den IDs frisch eingespielter Demo-Daten.

1. Backend starten: `php artisan serve` (Adresse `http://localhost:8000`, Variable `baseUrl`)
2. In den Collection-Variablen `password` (und bei Bedarf `email`, Standard Ben Müller) als **Current value** eintragen, nie als Initial value
3. Im Ordner „1 Anmelden“ nacheinander: „CSRF-Cookie holen“, „Login (Passwort)“. Nur wenn die 2FA eingeschaltet ist: den aktuellen Code aus der Authenticator-App in `totpCode` eintragen und „2FA-Code bestätigen“
4. Danach laufen alle Anfragen in „2 Abfragen“ und „3 Änderungen“

Die Collection setzt `Origin`, `Referer` und `X-XSRF-TOKEN` selbst und meldet GraphQL-Fehler als fehlgeschlagenen Test. Ein Test im Backend (`PostmanCollectionTest`) prüft, dass alle Anfragen zum aktuellen Schema passen.

Ablauf von Hand (ohne Collection), Header `Origin: http://localhost:3000` bei jeder Anfrage:

1. `GET /sanctum/csrf-cookie`, danach bei jeder Anfrage den Header `X-XSRF-TOKEN` mit dem Wert des Cookies `XSRF-TOKEN` mitschicken (URL-dekodiert, also `%3D` als `=`; sonst antwortet Laravel mit 419)
2. `POST /login` mit `email` und `password`
3. `POST /two-factor-challenge` mit `code` (aus der App) oder `recovery_code`
4. `POST /api` mit der Abfrage, zum Beispiel `{ "query": "{ me { name } projects { name viewerRole } }" }`

## Claude anbinden (Inhalte per natürlicher Sprache)

Das Backend bietet einen MCP-Server unter `http://localhost:8000/mcp` (später auf dem API-Server). Claude handelt dort mit deinen Rechten: Projekte lesen, Projekte anlegen (Rollen mit `projects.create`: Admin, Projektleitung, Koordination), Mitglieder setzen und ganze Projektpläne mit Milestones, ToDos und Tasks anlegen und zuweisen. Löschen kann Claude nichts.

1. In der App unter **Profil → Zugang für Claude** einen Zugang erzeugen und kopieren (wird nur einmal angezeigt, gilt 90 Tage).
2. **Claude Code in diesem Projekt:** Den Server kennt die `.mcp.json` schon. Den Zugang als Umgebungsvariable setzen und Claude Code neu starten:
   ```bash
   export MOLLOPLANER_TOKEN="<Zugang>"
   ```
   **Woanders:** den in der App angezeigten Befehl ausführen (`claude mcp add --transport http mollo-planer http://localhost:8000/mcp --header "Authorization: Bearer <Zugang>"`).
3. Dann einfach schreiben, zum Beispiel: „Erstelle ein Projekt für einen Podcast mit Milestones, ToDos und Tasks; Oliver macht Technik und Schnitt, Lindita moderiert.“

Die Personen müssen in der App schon ein Konto haben (Verwaltung → Benutzer). Zuweisungsmails gehen nur auf Wunsch raus.

## KI-Assistent (Projekt per Chat oder Sprache anlegen)

Admins finden in der Seitenleiste **KI-Assistent**: links ein Chat (auch mit Spracheingabe per Mikrofon-Knopf in Chrome und Edge), rechts die Vorschau des Plans mit Name, Beschreibung und Timeline (Milestones, aufklappbare ToDos, Tasks mit Personen und Daten). Mit weiteren Nachrichten lässt sich der Entwurf ändern. Gespeichert wird erst mit **Jetzt eintragen**.

Einrichten: einen API-Schlüssel unter https://console.anthropic.com erzeugen und in `backend/.env` eintragen (nie committen), danach das Backend neu starten:

```bash
ANTHROPIC_API_KEY=sk-ant-...
# optional, Standard claude-opus-5-5
ANTHROPIC_MODEL=claude-opus-5-5
```

Jede Chat-Nachricht ist eine kostenpflichtige Anfrage an die Claude-API. Bei der Spracheingabe schickt der Browser die Aufnahme zur Erkennung an seinen Anbieter (Google bzw. Microsoft).

## Deployment (Produktion)

Produktiv laufen Frontend unter `https://planer.mollo.dev` und API unter `https://api.planer.mollo.dev` auf dem Hostpoint-Server `sl23.web.hostpoint.ch` (Benutzer `oliver14`). Der Ablauf ist automatisiert: Ein Push auf `main` testet, baut und spielt die App aus, nach einer Freigabe.

### Ablauf

Die Datei `.github/workflows/deploy.yml` läuft bei jedem Push auf `main` (oder manuell, siehe unten):

1. **Tests:** Backend (`composer test`, SQLite) und Frontend (Lint, Typecheck, Unit-Tests). Schlägt ein Test fehl, wird nichts veröffentlicht.
2. **Freigabe:** Der Job `deploy` wartet auf eine Freigabe im Environment `production`.
3. **Build:** Das Frontend wird mit `NUXT_PUBLIC_API_BASE=https://api.planer.mollo.dev` gebaut.
4. **Übertragung:** Das Archiv `frontend/.output/public` geht per SSH an den Server. Der Schlüssel darf nur das Skript `deploy/deploy.sh` starten, keine Shell.
5. **Server:** `deploy/deploy.sh` prüft das Archiv, setzt den Code auf `origin/main`, sichert die Datenbank, schaltet den Wartungsmodus ein, führt `composer install --no-dev`, `migrate --force` und die Caches aus, kopiert das Frontend nach `~/www/planer.mollo.dev` und schaltet den Wartungsmodus wieder aus.

Die `.env` auf dem Server wird nie überschrieben und nie ins Repository übernommen.

### Deployment auslösen

- **Automatisch:** Push oder Merge auf `main`. Im GitHub-Tab **Actions** den Lauf öffnen und unter „Review deployments“ für `production` freigeben.
- **Manuell:** Actions → `deploy` → **Run workflow** (Branch `main`).

### Einrichtung (bereits erledigt, zur Dokumentation)

Für eine Neuinstallation oder bei einem Schlüsselwechsel:

- **Deploy-Schlüssel** nur für CI: `ssh-keygen -t ed25519 -N '' -C 'github-actions-deploy planer' -f ~/.ssh/mollo-planer-deploy`. Den öffentlichen Teil auf dem Server in `~/.ssh/authorized_keys` mit Einschränkung eintragen (eine Zeile):
  ```
  command="/home/oliver14/apps/mollo-planer/deploy/deploy.sh",restrict ssh-ed25519 AAAA… github-actions-deploy planer
  ```
- **GitHub-Secrets** unter *Settings → Secrets and variables → Actions*:
  - `DEPLOY_SSH_KEY`: Inhalt der privaten Datei `~/.ssh/mollo-planer-deploy`
  - `DEPLOY_KNOWN_HOSTS`: Ausgabe von `ssh-keygen -F sl23.web.hostpoint.ch | grep -v '^#'`, damit der Server fest geprüft wird
  - `FONTAWESOME_NPM_AUTH_TOKEN` und `WEBAWESOME_NPM_AUTH_TOKEN` (wie bei der Frontend-CI)
- **Environment `production`** unter *Settings → Environments* mit mindestens einem Required Reviewer.
- **Server:** `~/apps/mollo-planer` ist ein Git-Checkout von `origin/main`. Die Datei `backend/.env` mit den Produktionswerten liegt dort mit Rechten `600`.
- **Docroots:** `~/www/planer.mollo.dev` ist das Frontend. `~/www/api.planer.mollo.dev` ist ein eigener Ordner mit einer `index.php`, die auf `~/apps/mollo-planer/backend` zeigt, und der Laravel-`.htaccess` unter dem Hostpoint-Block. Ändert sich `backend/public/index.php` im Repo, muss die Kopie im Docroot von Hand angepasst werden.
- **PHP:** Der Server hat PHP 8.5 unter `/usr/local/php85/bin`. Das Skript nutzt diesen Pfad fest. Die Standard-`php` (8.3) reicht für das Projekt nicht, mindestens 8.4.1 ist nötig.

### Fehlersuche

- **Tests rot:** Im Log des Jobs `test-backend` oder `test-frontend` steht der Fehler. Es wurde nichts veröffentlicht.
- **Deploy abgebrochen:** Im Log zeigt die letzte Zeile mit `==>` den Schritt. Bricht das Skript während des Wartungsmodus ab, hebt es ihn selbst wieder auf. Falls die App trotzdem hängen bleibt, auf dem Server ausführen:
  ```bash
  cd ~/apps/mollo-planer/backend && /usr/local/php85/bin/php artisan up
  ```
- **Stand prüfen:** `ssh mollo.ch` (persönlicher Schlüssel), dann `cd ~/apps/mollo-planer && git log --oneline -1`.

### Zurückrollen

Jedes Deploy sichert die Datenbank vorher nach `~/backups/planer/planer-<Zeit>-<Commit>.sql.gz` (die letzten 10 bleiben erhalten).

- **Code:** Auf `main` den fehlerhaften Commit mit `git revert` rückgängig machen und den Workflow neu starten. `deploy.sh` setzt immer auf `origin/main`, ein erneuter Lauf eines alten Workflows bringt also den alten Code nicht zurück.
- **Datenbank:** Nur bei einer kaputten Migration. Ein Backup einspielen überschreibt alle Daten, die seit dem Backup entstanden sind. Die Zugangsdaten stehen in `backend/.env`:
  ```bash
  gunzip -c ~/backups/planer/<Datei>.sql.gz | MYSQL_PWD='<DB_PASSWORD>' mysql -h <DB_HOST> -u <DB_USERNAME> <DB_DATABASE>
  ```

### Bekannte Punkte

- Die CI-Workflows (`backend.yml`, `deploy.yml`) testen das Backend mit PHP 8.5, passend zum Server.
- Das Skript kopiert das Frontend per `rsync --delete`. Eigene Dateien im Docroot `~/www/planer.mollo.dev` gehen dabei verloren.
