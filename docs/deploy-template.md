# Deploy-Template: <Projektname>

Die Angaben der eigenen Installation stehen nicht im Repo. Adressen wie `planer.example.com` (PROD) und `planer-test.example.com` (TEST), «Webhoster» und Pfade wie `~/apps/<app>` sind Platzhalter.

Dieses Formular ausfüllen und Claude übergeben, zusammen mit dem Satz: „Deploye das Projekt nach diesem Template.“

Passwörter und Tokens gehören **nicht** in den Chat. Sie werden direkt in die `.env` auf dem Server bzw. in die GitHub-Secrets eingetragen. Falls doch einmal ein Passwort im Chat stand, danach ändern.

Platzhalter `<…>` ersetzen. Felder, die nicht zutreffen, streichen.

**Wo ein Befehl läuft:** Jeder Befehlsblock beginnt mit `# LOKAL` (auf deinem Mac) oder `# AUF DEM SERVER` (per SSH beim Webhoster angemeldet). Im Fliesstext steht es vor dem Befehl: «(lokal)» oder «(auf dem Server)». Die Zeilen in `authorized_keys` und im Cron stehen im Panel des Webhosters bzw. in dieser Datei, nicht im Terminal.

---

## 1. Voraussetzungen

- Webhosting mit SSH-Zugang
- PHP im Panel des Webhosters: `<8.5>`. Auf dem Server liegt die Version unter `/usr/local/php<85>/bin/php`
- MariaDB: `<10.11>`
- GitHub-Repository: `<owner>/<repo>`, Branch `main`
- Lokal: Node `<24>` (nvm), PHP `<8.5>`, Composer, GitHub CLI (`gh`) angemeldet

---

## 2. Projektangaben

| Angabe | Wert |
|---|---|
| Projektname | `<projekt>` |
| Frontend-Domain | `<frontend.example.ch>` |
| API-Domain (falls Backend) | `<api.example.ch>` |
| SSH-Host des Webhosters | `<ssh.example.com>` |
| SSH-Benutzer | `<username>` |
| Projektpfad auf dem Server | `~/apps/<projekt>` |
| Docroot Frontend | `~/www/<frontend-domain>` |
| Docroot API | `~/www/<api-domain>` |

---

## 3. Datenbank (Panel des Webhosters)

- **DB-Host:** `<benutzer>.mysql.db.internal` (nicht `localhost`, dort fehlt der Socket)
- **DB-Name:** `<datenbank>`
- **DB-Benutzer:** `<benutzer>`
- **DB-Passwort:** nur in `backend/.env` auf dem Server

---

## 4. E-Mail (Webhoster)

- **SMTP-Host:** `<smtp.example.com>`
- **Port:** `465`, SSL/TLS (`MAIL_SCHEME=smtps`)
- **Benutzer:** `<mailkonto@domain>`
- **Passwort:** nur in `backend/.env` auf dem Server
- **Absender:** `<absender@domain>` (sollte dem Mailkonto entsprechen)

---

## 5. Angaben für `backend/.env` (auf dem Server, Rechte `600`)

Vorlage: `backend/.env.example` aus dem Repo. Diese Werte setzen:

```env
APP_NAME="<Projektname>"
APP_ENV=production
APP_KEY=                       # erzeugen: php artisan key:generate --show
APP_DEBUG=false
APP_URL=https://<api-domain>
APP_LOCALE=de
APP_FALLBACK_LOCALE=en
LOG_LEVEL=error

DB_CONNECTION=mariadb
DB_HOST=<benutzer>.mysql.db.internal
DB_PORT=3306
DB_DATABASE=<datenbank>
DB_USERNAME=<benutzer>
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_DOMAIN=.<domain>
FRONTEND_URL=https://<frontend-domain>
SANCTUM_STATEFUL_DOMAINS=<frontend-domain>   # ohne lokale IP
# CORS_ALLOWED_ORIGINS bleibt leer: nur für die Entwicklung im lokalen Netz
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=<smtp.example.com>
MAIL_PORT=465
MAIL_USERNAME=<mailkonto@domain>
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="<absender@domain>"
MAIL_FROM_NAME="${APP_NAME}"

PASSKEYS_RELYING_PARTY_ID=<frontend-domain>   # Pflicht in Produktion: gleich dem Host von FRONTEND_URL
ANTHROPIC_API_KEY=             # nur, falls der KI-Assistent genutzt wird (ohne Schlüssel bleibt er aus)
# ASSISTANT_LIMIT_HOUR=30      # Entwürfe je Person und Stunde (Standard)
# ASSISTANT_LIMIT_DAY=200      # und je Tag (Standard)
```

Bei einem Frontend ohne Backend entfällt dieser Abschnitt.

---

## 6. Deploy-Schlüssel

Nur für CI, ohne Passphrase, **je Umgebung ein eigener Schlüssel** (bei zwei Umgebungen siehe Abschnitt 15). **Nie den persönlichen SSH-Schlüssel verwenden:** Er öffnet eine normale Shell. Der Deploy-Schlüssel darf durch `command=` nur das Deploy-Skript seiner Umgebung starten. Erzeugt wird er **lokal**, nicht auf dem Server:

```bash
# LOKAL (auf deinem Mac, im Terminal)
ssh-keygen -t ed25519 -N '' -C 'github-actions-deploy <projekt>-<test|prod>' -f ~/.ssh/<projekt>-<test|prod>-deploy
```

- **Server:** In `~/.ssh/authorized_keys` pro Schlüssel **eine einzige Zeile** anhängen, eingeschränkt auf das Deploy-Skript (der öffentliche Teil ist die Datei mit `.pub`):
  ```
  command="/home/<benutzer>/<dev|ch>/apps/<projekt>/deploy/deploy.sh",restrict ssh-ed25519 AAAA… github-actions-deploy <projekt>-<test|prod>
  ```
  Alles Folgende **auf dem Server**: Vorher `authorized_keys` sichern (`cp ~/.ssh/authorized_keys ~/.ssh/authorized_keys.bak-$(date +%Y%m%d-%H%M)`). Die Datei hat oft keinen Zeilenumbruch am Ende, deshalb vor dem Anhängen eine Leerzeile setzen (`echo "" >> ~/.ssh/authorized_keys`). Rechte: `chmod 700 ~/.ssh && chmod 600 ~/.ssh/authorized_keys`. Die Zeile erzeugst du **lokal** (`echo "command=\"…\",restrict $(cat ~/.ssh/<projekt>-<test|prod>-deploy.pub)"`), kopierst sie und fügst sie auf dem Server mit `nano ~/.ssh/authorized_keys` unten ein.
- **In GitHub gehört der private Teil** (die Datei ohne `.pub`), nicht der öffentliche (Abschnitt 7). Nach dem Eintragen die private Datei vom Server löschen, falls sie dort entstand.
- **Host-Schlüssel** (lokal): `ssh-keygen -F <ssh-host> | grep -v '^#'`. Die Zeile wird als Secret `DEPLOY_KNOWN_HOSTS` eingetragen.
- **Schlüssel lokal prüfen**, bevor GitHub ins Spiel kommt:
  ```bash
  # LOKAL (auf deinem Mac, im Terminal)
  ssh -i ~/.ssh/<projekt>-<test|prod>-deploy -o IdentitiesOnly=yes <benutzer>@<ssh-host> </dev/null; echo "Exit: $?"
  ```
  Erwartet: «==> Umgebung: …» und danach «index.html fehlt im Archiv» (der Abbruch ohne Archiv ist gewollt und ändert nichts), `Exit: 1`. Die Meldung «Pseudo-terminal will not be allocated because stdin is not a terminal» ist harmlos.

---

## 7. GitHub

GitHub hat für Secrets und Variablen dieselbe Oberfläche, nur mit verschiedenen Reitern. Die Unterscheidung ist leicht zu übersehen, deshalb hier genau:

- **Secrets** (Reiter *Secrets*): verschlüsselt, nach dem Speichern nicht mehr einsehbar.
- **Variablen** (Reiter *Variables*): Klartext, einsehbar. Für Hostnamen, Benutzer und Adressen.

Es gibt zwei Ebenen, und der Fehler liegt meist in der Verwechslung:

- **Repository-Ebene** (*Settings → Secrets and variables → Actions*): was für alle Umgebungen gleich ist, das heisst `DEPLOY_HOST`, `DEPLOY_USER` (Variablen) und `DEPLOY_KNOWN_HOSTS` sowie die npm-Tokens (Secrets), weil beide Umgebungen auf demselben Server liegen.
- **Umgebung** (*Settings → Environments → `<Name>`*, ab Abschnitt 15): was je Umgebung anders ist, das heisst das Secret `DEPLOY_SSH_KEY` und die Variablen `API_BASE` und `FRONTEND_URL`.

**Ein Secret oder eine Variable in der Umgebung überschreibt die gleichnamige auf Repository-Ebene.** Ein leerer oder falscher `DEPLOY_SSH_KEY` in der Umgebung führt zu «Permission denied (publickey)», auch wenn auf Repository-Ebene ein richtiger liegt. Fehlen `API_BASE` und `FRONTEND_URL` in der Umgebung, bricht der Lauf mit «The variables API_BASE and FRONTEND_URL are missing in the GitHub environment.» noch vor dem Build ab (es ist dann nichts auf dem Server geschehen). Den `DEPLOY_SSH_KEY` auf Repository-Ebene danach löschen, damit er nirgends als Rückfall liegt.

### Secrets

- `DEPLOY_SSH_KEY` (**je Umgebung**): der **private** Schlüssel, vollständig von `-----BEGIN OPENSSH PRIVATE KEY-----` bis `-----END OPENSSH PRIVATE KEY-----` (lokal: `pbcopy < ~/.ssh/<projekt>-<test|prod>-deploy`)
- `DEPLOY_KNOWN_HOSTS`: Ausgabe des Host-Schlüssel-Befehls aus Abschnitt 6
- `PRO_ASSETS_TOKEN`: nur bei Pro-Symbolen aus einem privaten Repo (Auftrag 38): fine-grained Token, nur Lesen, nur dieses eine Repository. Ein altes `FONTAWESOME_NPM_AUTH_TOKEN` kann gelöscht werden. Das Repo des Projekts darf öffentlich sein; lizenzierte Dateien gehören nie hinein.
- `WEBAWESOME_NPM_AUTH_TOKEN`: nur bei Web Awesome Pro

### Lizenzierte Dateien: nie in Cache oder Artefakte

Dateien, die einer Lizenz unterliegen (Font Awesome Pro und alles, was aus dem privaten Repo `mollo-pro-assets` kommt), landen **nie**
im Actions-Cache (`actions/cache`) und **nie** in einem Artefakt (`actions/upload-artifact`) und nicht im Repository (das ist öffentlich).

- **Warum:** Ein Cache gehört dem Repository und kann von anderen Branches und von Pull Requests gelesen werden, die auf diesen Branch zielen; ein
  Artefakt lässt sich herunterladen. So käme lizenziertes Material an Stellen, die das Secret `PRO_ASSETS_TOKEN` nie hätten.
- **Wie stattdessen:** Jeder Lauf holt die Symbole selbst, mit `npm run icons:fetch` aus dem privaten Repo (rund 2,5 MB). Das kostet bei Font Awesome nichts
  (Download von GitHub, nicht aus dem npm-Register) und braucht kein Cache.
- **Der Wächter** (`npm run icons:check`, Test `no-pro-assets`) prüft das Repository, nicht den Cache: Beim Ändern eines Workflows darauf achten, dass
  `frontend/vendor/` in keinem Cache-Pfad und in keinem Artefakt vorkommt.
- **Aufräumen:** Gab es früher Caches mit dem Schlüssel `pro-icons-*`, löschen: `gh cache list --key pro-icons`, dann `gh cache delete <id>`.

### Variablen

- `DEPLOY_HOST` (Repository): Hostname des Servers beim Webhoster, z. B. `<ssh.example.com>`. Muss zum Eintrag in `DEPLOY_KNOWN_HOSTS` passen.
- `DEPLOY_USER` (Repository): SSH-Benutzer, z. B. `<username>`
- `API_BASE` und `FRONTEND_URL` (**je Umgebung**): die Adressen der Umgebung

### Umgebung

**Settings → Environments → New environment:** je Umgebung eine. Die mit Freigabe (`production`) bekommt unter *Required reviewers* den eigenen Account, die Testumgebung keinen.

---

## 8. Einmalige Einrichtung auf dem Server

1. Projekt holen:
   ```bash
   # AUF DEM SERVER (per SSH angemeldet, z. B. mit deinem persönlichen Schlüssel)
   mkdir -p ~/apps/<projekt> && cd ~/apps/<projekt>
   git init -q && git remote add origin https://github.com/<owner>/<repo>.git
   git fetch -q origin main && git checkout -q -f -B main origin/main
   ```
2. `backend/.env` aus Abschnitt 5 anlegen, `chmod 600`.
3. Backend-Abhängigkeiten mit PHP 8.5 (beim Webhoster ist `php` seit Neuem immer 8.5, prüfen mit `php -v`; `DEPLOY_PHP` in `backend/.env` ist nur nötig, wenn das `php` des Servers einmal nicht 8.5 wäre):
   ```bash
   # AUF DEM SERVER (per SSH angemeldet, z. B. mit deinem persönlichen Schlüssel)
   cd ~/apps/<projekt>/backend
   php /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction
   php artisan storage:link
   ```
4. **Migration nur nach Freigabe und mit Backup:** `php artisan migrate --force`, danach `php artisan planer:sync-templates` (schreibt die mitgelieferten Vorlagen aus `spec/templates/` in die Datenbank, wiederholbar).
   **Vor dem Deployment von M4:** `php artisan planer:check-limits` (listet je Projekt, wo Daten eine Grenze der Planer-Datei verletzen: 200 Milestones, 50 ToDos je Milestone, 50 Tasks je ToDo, 50 Mitglieder, 30 Labels, Beschreibung höchstens 5000 Zeichen als Markdown; ändert nichts, Exit-Code 1 bei Funden). Funde zuerst von Hand bereinigen: Ein Projekt mit Überschreitungen lässt sich nicht exportieren.
   **Breaking Change am Connector (M4 I3):** Der MCP-Server hat nur noch `list_projects`, `list_users`, `list_templates`, `export_plan` und `import_plan`. `get_project`, `create_project`, `set_project_member`, `create_project_plan`, `add_to_plan`, `update_plan_item`, `move_milestone` und `set_task_status` entfallen. Zugänge und Konfiguration bleiben gültig. Wer sie nutzt, vor dem Deployment informieren (`docs/claude-connector.md`).
5. Docroot der API (`~/www/<api-domain>`): Da der Webhoster diesen Ordner selbst anlegt, wird er gefüllt statt verlinkt:
   - `index.php` kopieren und die Pfade auf `~/apps/<projekt>/backend` umstellen (`__DIR__.'/../` durch den absoluten Pfad ersetzen).
   - Die Zeilen des Webhosters oben in der `.htaccess` (der Block, den das Panel selbst einträgt, zum Beispiel die Wahl der PHP-Version) behalten, darunter die Laravel-Regeln aus `backend/public/.htaccess`.
   - `robots.txt`, `favicon.ico` und den Symlink `storage` kopieren.
6. Docroot des Frontends (`~/www/<frontend-domain>`): leer lassen, der Deploy befüllt ihn.

---

### Cron (Queue und Scheduler)

Mails und Webhooks (Benachrichtigung bei Änderungen der Planer-Datei) warten in der Queue (`QUEUE_CONNECTION=database`). Auf dem Server des Webhosters läuft kein Dauerprozess. Darum startet der Scheduler jede Minute einen Worker, der arbeitet, bis die Queue leer ist (`routes/console.php`: `queue:work --stop-when-empty --max-time=55`). Dazu genügt **ein** Cron-Eintrag im Panel des Webhosters (Cron-Jobs), jede Minute:

```
* * * * * cd <api-pfad> && <php-pfad> artisan schedule:run >> /dev/null 2>&1
```

`<api-pfad>` ist der Ordner des Backends (`~/apps/<projekt>/backend`), `<php-pfad>` das PHP 8.5 des Servers: beim Webhoster genügt `php`. Cron hat manchmal einen anderen `PATH` als SSH; einmal prüfen mit dem Eintrag `* * * * * php -v > ~/cron-php.txt` und die Datei nach einer Minute lesen, dann den Eintrag wieder ersetzen.

**Ohne diesen Eintrag gehen weder Mails noch Webhooks raus**, und es erscheint kein Fehler. Prüfen: In der App einen Task zuweisen oder in den Projekteinstellungen „Testen“ drücken; innerhalb von zwei Minuten kommt die Mail bzw. der Test an. Der Eintrag ist ein einmaliger Schritt (macht Oliver im Panel).

### Nach dem Deploy und bei Problemen

- **Assistent prüfen:** `php artisan assistant:ping` schickt eine kleine echte Anfrage mit dem echten Schema an die Claude-API und meldet OK oder den Fehler der API (schreibt nichts, braucht `ANTHROPIC_API_KEY`). Nach dem Deploy auf dem Server laufen lassen, dann einen Entwurf im Browser auslösen.
- **Kein `migrate:rollback` in Produktion.** Einige `down()`-Migrationen verlieren Daten. Der Rückweg ist die Datenbank-Sicherung (`deploy.sh` legt sie vor den Migrationen an) plus der vorherige Release.
- **Frontend-Build:** `NUXT_PUBLIC_API_BASE` ist die Build-Variable mit der API-Adresse. Leer gilt nur in der Entwicklung (dort wird die Adresse aus dem Host der Seite abgeleitet), in Produktion muss sie gesetzt sein.

## 9. Skript und Workflow anpassen

Vorlage: `deploy/deploy.sh` und `.github/workflows/deploy.yml` aus dem Projekt `mollo-planer`.

- In `deploy.sh` die Variablen oben anpassen: `APP`, `BACKEND`, `WEB_ROOT`, `PHP`, `COMPOSER`. Das PHP nimmt das Skript aus der Zeile `DEPLOY_PHP=` in `backend/.env` auf dem Server (voller Pfad); fehlt sie oder ist sie leer, gilt `php`, beim Webhoster immer 8.5. Welches `php` eine SSH-Sitzung ohne Terminal findet, hängt von der Umgebung des Servers ab, darum den Pfad ausdrücklich setzen.
- **Wichtig:** `deploy.sh` führt die Fassung aus, die **auf dem Server liegt**, bevor es den neuen Code holt. Eine Änderung an `deploy.sh` wirkt deshalb erst beim übernächsten Deploy, ausser man holt die Datei vorher von Hand: `git fetch origin main && git checkout origin/main -- deploy/deploy.sh`.
- Bei einem Frontend ohne Backend die Schritte Datenbank-Backup, Migrationen und Caches streichen.
- In `deploy.yml` bestimmt der Branch die GitHub-Umgebung; `API_BASE` und `FRONTEND_URL` kommen aus der Umgebung, `DEPLOY_HOST` und `DEPLOY_USER` aus den Variablen des Repositorys (Abschnitt 7).
- Die Tests-Jobs an das Projekt anpassen oder streichen.

---

## 10. Deploy starten

1. Änderungen per PR nach `dev` mergen: Der Lauf deployt nach TEST (ohne Freigabe). Nach `main` mergen deployt nach PROD.
2. Der Lauf für `main` wartet im Environment `production`. Dort **Review deployments** öffnen und freigeben.
3. Schlägt ein Job fehl: **Actions → deploy → Re-run failed jobs**.

---

## 11. Prüfen

- `https://<frontend-domain>` liefert HTTP 200.
- `https://<api-domain>/up` liefert HTTP 200.
- `https://<api-domain>/sanctum/csrf-cookie` liefert HTTP 204 (nur mit Sanctum).
- Auf dem Server: `php artisan about` zeigt `Environment: production` und `Debug Mode: OFF`.
- **Bestätigungslink in einem zweiten Browser ohne Sitzung öffnen:** Ein Konto registrieren (Browser 1), den Link aus der Mail in einem Browser ohne Sitzung öffnen (privates Fenster oder anderer Browser). Erwartet: Die Seite «E-Mail-Adresse bestätigen» zeigt den Knopf, die Adresse ist noch **nicht** bestätigt; nach dem Klick ist sie es, ohne dass jemand angemeldet wird. Der Link kommt aus `APP_URL` (die API-Adresse, mit `https`); hinter dem Proxy müssen Schema und Host stimmen, sonst gilt die Signatur nicht und die Seite meldet «Link ungültig». Die Mail geht über die Queue (der Zeitplan arbeitet sie jede Minute ab, sie kann also eine Minute brauchen). Ein veränderter Link zeigt «Der Link ist ungültig oder abgelaufen» mit «Bestätigungsmail erneut senden».

---

## 12. Stolpersteine

- **DB-Host:** Nicht `localhost` verwenden. Der Socket existiert beim Webhoster nicht.
- **PHP-Version:** `deploy.sh` nimmt `DEPLOY_PHP` aus `backend/.env` (voller Pfad), sonst `php` (beim Webhoster immer 8.5). Ist die Version älter als 8.5, bricht es ab, bevor etwas geändert wird, und nennt den benutzten Pfad. Zeigt der Pfad auf eine nicht ausführbare Datei, bricht es ebenfalls ab. Die Shell-Sitzung mit Terminal und die ohne Terminal können verschiedene `php` finden; massgeblich ist `DEPLOY_PHP`.
- **Schema-Cache von Lighthouse liegt ausserhalb von Git:** Ausserhalb von `APP_ENV=local` liest Lighthouse das GraphQL-Schema aus `bootstrap/cache/lighthouse-schema.php`. Die Datei ist nicht in Git, `git reset` lässt sie stehen. Ein alter Stand zeigt auf Resolver-Klassen, die es nicht mehr gibt («Failed to find class … for directive @field», «Internal server error»). `deploy.sh` führt darum nach `config:cache` `lighthouse:clear-cache` und `lighthouse:cache` aus. Der Query-Cache (`query_cache`, Modus `store`) braucht das nicht: Er hält geparste Abfragetexte, die vom Schema unabhängig sind. Von Hand: `php artisan lighthouse:clear-cache && php artisan lighthouse:cache`.
- **Rauchtest nach dem Deploy:** Nach `artisan up` schickt `deploy.sh` `{ viewerRights }` ohne Sitzung an `APP_URL/api` (ohne `Origin`, damit Sanctum die Anfrage nicht als SPA-Anfrage mit CSRF behandelt). Erwartet: die Antwort enthält `Unauthenticated.`. Sonst meldet der Lauf «Rauchtest GraphQL fehlgeschlagen: …» und endet mit Fehler. Der neue Code ist dann schon live, es wird nichts zurückgerollt.
- **Passkey-RP-ID:** Jede App hat ihre eigene RP-ID (kein Single Sign-on). `PASSKEYS_RELYING_PARTY_ID` in Produktion **immer** setzen, gleich dem Host von `FRONTEND_URL` (z. B. `planer-test.example.com`), nie die Hauptdomain. Ein Wechsel macht alle Passkeys ungültig. Ein Passkey einer anderen App unter der Hauptdomain erscheint sonst in der Auswahl und die Anmeldung endet mit 422. `deploy.sh` warnt im Log, wenn der Wert vom Host von `FRONTEND_URL` abweicht.
- **PDF-Export der Dokumente (mPDF):** braucht die PHP-Erweiterungen `gd`, `mbstring`, `dom` und schreibt in `storage/framework/cache/mpdf` (schreibbar). Die Grenzen stellt das Panel des Webhosters ein, der Code erhöht sie nicht: für `api.<domain>` **`memory_limit` 256M** und **`max_execution_time` 60** Sekunden (das größte Dokument braucht rund 26 MB und etwa 2 Sekunden). `mpdf/mpdf` verlangt PHP `~8.5.0`: Vor einem Update auf PHP 8.6 nach einer neuen mPDF-Version sehen.
- **Erster Lauf:** Liegt `deploy/deploy.sh` schon unverwaltet auf dem Server, vorher `git fetch origin main && git reset --hard origin/main` ausführen.
- **Block des Webhosters in `.htaccess`:** Nicht löschen, sonst wechselt die PHP-Version des Docroots.
- **Frontend-Build:** Die API-Adresse wird beim Build eingebaut (`NUXT_PUBLIC_API_BASE`). Ändert sich die Domain, neu bauen.
- **Passwörter:** Nie im Chat oder im Repo. Nach versehentlicher Weitergabe ändern.

### Eigenheiten und Fallstricke beim Webhoster (aus dem Aufbau von TEST und PROD)

- **Alle Docroots liegen unter `~/www/<domain>`.** Der Webhoster legt sie beim Anlegen der Domain dort an (`~/www/planer-test.example.com`, `~/www/api.planer-test.example.com`, …). Eigene Ordnerbäume wie `~/test/www` gibt es nicht, sie würden vom Webserver nicht ausgeliefert. Der App-Ordner und die Sicherungen dürfen frei liegen (`~/test/apps/…`, `~/prod/apps/…`), nur der Docroot nicht. `DEPLOY_WEB_ROOT` zeigt deshalb auf `~/www/<frontend-domain>`; `deploy.sh` verlangt, dass der letzte Pfadteil dem Host von `FRONTEND_URL` entspricht, und einen vorhandenen Ordner.
- **Das Standardhosting des Webhosters läuft auf BSD, nicht auf Linux (GNU).** Die Kommandozeilenwerkzeuge verhalten sich deshalb wie auf dem Mac. Beispiel `sed -i`: `sed -i 's/a/b/' datei` bricht dort mit «sed: 1: … invalid command code .» ab, weil BSD-`sed` nach `-i` eine Endung für die Sicherungskopie verlangt. Portabel (läuft beim Webhoster, auf dem Mac und unter Linux) ist `sed -i.bak 's/a/b/' datei && rm datei.bak`. Alle `sed`-Befehle in dieser Anleitung sind so geschrieben. Auch sonst gilt: Es sind die BSD-Varianten. Linux-Beispiele aus dem Netz (`sed -i`, `grep -P`, `readlink -f`, `date -d`) vorher mit der Hilfe (`man sed`) abgleichen. `deploy.sh` nutzt nur Optionen, die auf beiden laufen (`sed -E`, `grep -E`, `xargs -r`).
- **`php` ist beim Webhoster immer 8.5**, ein voller Pfad ist nicht mehr nötig (`DEPLOY_PHP` leer lassen). Cron hat manchmal einen anderen `PATH` als SSH: einmal im Panel des Webhosters den Cron-Eintrag `* * * * * php -v > ~/cron-php.txt` anlegen und die Datei nach einer Minute **auf dem Server** lesen (`cat ~/cron-php.txt`), dann den echten Eintrag setzen.
- **Die `index.php` im API-Docroot hat absolute Pfade** auf `<App-Ordner>/backend` (`/home/<benutzer>/<dev|ch>/apps/<projekt>/backend/…`, drei Stellen). Zieht der App-Ordner um, muss sie angepasst werden, sonst liefert die API-Domain einen Fehler:
  ```bash
  # AUF DEM SERVER (per SSH angemeldet, z. B. mit deinem persönlichen Schlüssel)
  sed -i.bak 's#/home/<benutzer>/apps/<projekt>#/home/<benutzer>/test/apps/<projekt>#g' ~/www/<api-domain>/index.php && rm ~/www/<api-domain>/index.php.bak
  grep -n "<projekt>" ~/www/<api-domain>/index.php
  ```
  Prüfen (lokal oder auf dem Server) mit `curl -si https://<api-domain>/up | head -3` (erwartet `HTTP/2 200`); bei 500 hilft **auf dem Server** `tail -n 30 <App-Ordner>/backend/storage/logs/laravel.log`.
- **`.htaccess` im API-Docroot nie von einer anderen Domain kopieren.** Der Block des Webhosters (der Block, den das Panel selbst einträgt) gehört zur Domain. Den eigenen Block behalten und die Laravel-Regeln aus `backend/public/.htaccess` darunter anhängen.
- **`rsync --delete` leert den Frontend-Docroot bei jedem Deploy.** Alles, was dort liegt und nicht aus dem Build stammt, ist danach weg (auch eine `.htaccess`). Was bleiben muss, gehört in den Build (`frontend/public/`). Vor dem ersten Deploy **auf dem Server** ansehen: `ls -la ~/www/<frontend-domain>`.
- **Cache-Köpfe des Frontends (Auftrag 36 A):** `frontend/public/.htaccess` kommt mit `nuxt generate` in den Docroot und setzt `/_nuxt/*` auf `public, max-age=31536000, immutable` (die Dateinamen tragen den Hash), `/icons/*` auf `public, max-age=2592000` (30 Tage; der Pfad trägt die Version von Font Awesome) und alle HTML-Seiten auf `no-cache` (ein Deployment greift sofort). Dazu Kompression für `.json` und `.svg`. Jede Anweisung steht in `<IfModule>`, ein Server ohne das Modul liefert wie vorher, nur ohne Köpfe. `deploy.sh` warnt, wenn die Datei nach dem Veröffentlichen im Docroot fehlt. **Prüfen nach dem Deployment** (**lokal**, im Terminal): `curl -sI https://planer-test.example.com/ | grep -i cache-control` (erwartet `no-cache`), dasselbe mit einer Datei aus `/_nuxt/` und `/icons/<version>/duotone-light/rocket.svg` (erwartet `public, max-age=…`). Kommen die Köpfe nicht an (nginx vor Apache schluckt sie), im Panel des Webhosters lösen und melden. Das Verhalten der Datei wurde mit einem lokalen Apache geprüft, nicht beim Webhoster.
- **Der Deploy bricht sicher ab.** `deploy.sh` liest und prüft `backend/.env` vor jeder Änderung; jede Meldung auf «… Nichts wurde geändert.» heisst, dass der Server unberührt ist. Lässt sich der Fehler nicht aus dem GitHub-Log lesen, das Skript **auf dem Server** von Hand ohne Archiv laufen lassen: `bash <App-Ordner>/deploy/deploy.sh </dev/null; echo "Exit: $?"`.
- **Fehlerbilder des SSH-Schlüssels:** «Permission denied (publickey)» heisst, dass der Schlüssel im GitHub-Secret nicht zu `authorized_keys` passt (leer, falsche Umgebung, nur der öffentliche Teil, Zeile umgebrochen). Kein Fehler, aber auch keine Ausgabe, kann eine Zeile **ohne** `command=` sein: Dann öffnet sich eine normale Shell. Test (**lokal**): `ssh -i <Schlüssel> -o IdentitiesOnly=yes <benutzer>@<ssh-host> 'echo hallo' </dev/null` darf **kein** `hallo` ausgeben.
- **Leere Datenbank beim ersten Mal:** `migrate --force` baut die Tabellen auf, danach `app:create-admin`. Enthält die Datenbank noch alte Daten, die weg sollen, vorher den Namen prüfen und nur dann `migrate:fresh` ausführen (beides **auf dem Server** im Ordner `<App-Ordner>/backend`: `php artisan config:show database.connections.mariadb.database`, dann `php artisan migrate:fresh --force`, das löscht alles). Auf `staging` fragt Laravel nicht nach (Abschnitt 15).

## 13. Betrieb: Nutzer verwalten

Wenn niemand sich anmelden kann, helfen drei Artisan-Befehle auf dem Server (per SSH im Ordner `backend/`, das `php` der Shell ist 8.5). `<Nutzer>` ist die E-Mail-Adresse oder die ID.

| Aufruf | Zweck |
|---|---|
| `php artisan app:user-password <Nutzer>` | Neues Passwort setzen. Wird zweimal und versteckt abgefragt, nie als Parameter. Beendet danach Zugänge für Claude, „angemeldet bleiben“ und Sitzungen. Der zweite Faktor bleibt. |
| `php artisan app:user-email <Nutzer>` | Zeigt Adresse und Stand der Bestätigung (schreibt nichts). |
| `php artisan app:user-email <Nutzer> <neue-E-Mail>` | Adresse ändern. Gilt danach als bestätigt (`--unverified`: Bestätigung wird neu verlangt). |
| `php artisan app:user-email <Nutzer> --verified` oder `--unverified` | Nur die Bestätigung ändern. |
| `php artisan app:user-role <Nutzer> [<Rolle>]` | Systemrolle setzen (ohne Rolle: Auswahl). Zeigt alte und neue Rolle. Es gelten die Regeln der App: Die letzte Person mit `users.manage` bzw. `roles.manage` behält das Recht (der letzte Admin bleibt Admin), und kein Projekt verliert seine letzte Person mit «Projekt löschen». Beispiel nach `migrate:fresh`: das eigene Konto zum Admin machen. |
| `php artisan app:user-approve <Nutzer>` | Wartendes Konto freigeben, über dieselbe Logik wie in der App (`ReviewRegistration`): die Person bekommt die Freigabe-Mail in der Sprache ihres Kontos, ein zweiter Aufruf ändert nichts und verschickt nichts. Ist die Adresse noch nicht bestätigt, warnt der Befehl: Die API bleibt dann gesperrt (`app:user-email <Nutzer> --verified`). |
| `php artisan app:user-2fa <Nutzer> status` | Authenticator, Wiederherstellungscodes, Passkeys (ID, Name, Datum), ob die Person beim nächsten Login einrichten muss. Schreibt nichts. |
| `php artisan app:user-2fa <Nutzer> reset` | Gerät verloren: Authenticator, Wiederherstellungscodes, alle Passkeys, Zugänge für Claude, Sitzungen und „angemeldet bleiben“ weg. |
| `php artisan app:user-2fa <Nutzer> authenticator` | Nur die Authenticator-App samt Wiederherstellungscodes entfernen, Passkeys bleiben. |
| `php artisan app:user-2fa <Nutzer> passkeys [--id=<ID>]` | Alle Passkeys oder einen entfernen, die Authenticator-App bleibt. |
| `php artisan app:user-2fa <Nutzer> recovery-codes` | Neue Wiederherstellungscodes (die alten verfallen), nur mit bestätigter Authenticator-App. Die Codes erscheinen einmal im Terminal und werden nirgends gespeichert. |

Gemeinsam:

- Jeder schreibende Aufruf fragt vorher nach (`--no-interaction` ist ein Fehler, es wird nichts geschrieben). Ohne Aktion fragt `app:user-2fa` per Auswahl.
- Entfernt ein Aufruf den **letzten** zweiten Faktor, sagt die Frage das ausdrücklich: Die Person richtet beim nächsten Login einen neuen ein (Pflicht, keine Ausnahme), ihre Zugänge und Sitzungen enden.
- **Sitzungen** lassen sich nur beenden, wenn `SESSION_DRIVER=database` gilt (Produktion). Sonst nennt die Ausgabe es, und die Sitzungen laufen aus.
- **E-Mail:** Solange eine Adresse nicht bestätigt ist, bleibt die API für das Konto gesperrt (Anmelden geht, mehr nicht). Unbestätigte und nicht freigegebene Konten löscht `app:prune-unverified-users` täglich nach 7 Tagen. Der Befehl verschickt keine Bestätigungsmail.
- Im Log (`storage/logs`) steht je Eingriff eine Zeile mit Nutzer-ID, Befehl und Aktion, ohne Passwort, Hash oder Adresse.

### Agent-Konten (Claude, Kevin, Bob; Auftrag 31)

Ein **Agent-Konto** hat keinen Zugang zur Oberfläche: keine Anmeldung mit Passwort oder Passkey, kein Zurücksetzen, keine Einladung. Der einzige Weg ist ein Token für den Connector (`/mcp`). Es braucht keinen zweiten Faktor, weil es keine Web-Sitzung gibt. In der Oberfläche trägt es das Kennzeichen «Agent». **Das Token erscheint einmal im Terminal und nie in einer Datei, einem Log oder einem Chat.**

Alle Befehle laufen **AUF DEM SERVER** (per SSH, im Ordner `backend/`; beim Webhoster ist `php` 8.5). Auf TEST und PROD je einzeln.

```bash
# AUF DEM SERVER
php artisan app:create-agent "Claude Club DB" claude-clubdb@example.ch   # legt das Konto an (Rückfrage), Systemrolle Mitglied
php artisan app:user-token claude-clubdb@example.ch "Claude Code"         # macht das Token, zeigt es EINMAL (Rückfrage)
php artisan app:user-token claude-clubdb@example.ch --revoke              # widerruft alle Tokens des Kontos
```

- Die Adresse muss keine echte Mailbox sein (es geht nie eine Mail dorthin).
- Danach **in der App** (als Owner oder Manager des Projekts): das Konto als Mitglied aufnehmen, mit der **Projektrolle Agent**. Sie darf den Plan exportieren und importieren, Aufgaben ändern, zuweisen, verschieben und kommentieren, aber keine Mitglieder und keine Projekteinstellungen ändern und nichts löschen.
- **Zugang auch ohne SSH (Auftrag 32):** Ein Admin (Recht `users.manage`, starke Sitzung) macht und widerruft Zugänge eines Agent-Kontos in der App unter **Admin → Benutzer**, im Bereich «Zugänge» der Zeile des Agenten: Name eingeben, Passwort bestätigen, der Zugang erscheint **einmal** in einem Dialog (Kopierknopf). Nur für Agent-Konten, nie für Menschen. Jedes Erzeugen und Widerrufen steht im Log (wer, welcher Agent, welcher Zugang, nie das Token), und alle Admins bekommen eine Info-Mail.
- Das Token gehört in die Einstellung des Connectors (siehe `docs/claude-connector.md`), nicht in `.env`, nicht ins Repository. Es gilt 90 Tage; danach ein neues machen.
- `app:user-token` verweigert Konten von Menschen: die machen ihr Token selbst im Profil.
- **Neue Standardrollen, -funktionen und -rechte** (`config/permissions.php`, zum Beispiel die Projektrolle Agent) erreichen eine bestehende Datenbank **automatisch**: `deploy.sh` ruft nach `migrate --force` `php artisan permissions:sync-defaults --add-missing` auf (Auftrag 40 B). Der Modus ergänzt nur Neues und **überschreibt nichts**:
  - Eine fehlende Standardrolle oder -funktion wird mit ihren Standardrechten bzw. -funktionen angelegt.
  - Bei einer bestehenden bleiben Namen und Zuordnungen. Nur ein Paar (ein Recht in einer Funktion, eine Funktion in einer Rolle), das **neu in die Konfiguration gekommen** ist und noch nie angeboten wurde, wird ergänzt. Was schon angeboten wurde und heute fehlt, hat ein Admin in den Einstellungen bewusst entfernt und kommt nicht zurück (Tabelle `rights_defaults_applied`).
  - **Der erste Lauf** (die Tabelle `rights_defaults_applied` ist leer, also auf jeder bestehenden Datenbank einmal) ist **vorsichtig** (Entscheid Oliver, 24.11.): Er trägt **alle** Paare aus der Konfiguration als «angeboten» ein und ergänzt nur **ganz fehlende** Rollen und Funktionen (mit ihrer Standardbelegung), aber **kein** fehlendes Paar an einer bestehenden Rolle oder Funktion. Was dort heute fehlt, hat jemand bewusst entfernt (auch die Standardrechte, die seit dem 19./20.11. entfernt wurden). Die Ausgabe nennt diese Paare unter «NICHT ergänzt», wer sie will, setzt sie in der Rechteverwaltung von Hand. **Ab dem zweiten Lauf** wird nur ergänzt, was neu in die Konfiguration kommt und noch nie angeboten wurde.
  - **Vor dem ersten Deployment** (**auf dem Server**, im Ordner `backend/`): `php artisan permissions:sync-defaults --check` zeigt getrennt (1), was ergänzt würde (fehlende Rollen und Funktionen; Exit 2), und (2), welche Paare der Konfiguration in der Datenbank fehlen und **nicht** ergänzt werden (nur zur Information, Exit bleibt 0).
  - Nichts wird entfernt. Funktionen und Rollen, die nicht in der Konfiguration stehen (in den Einstellungen angelegt), bleiben und werden nur in der Ausgabe genannt.
  - Die Ausgabe (eine Zeile je ergänztem Eintrag, sonst «Nichts zu tun») steht im Deploy-Log, dazu ein Log-Eintrag je Eintrag (`storage/logs`, ohne Personen). Ein Fehler bricht das Deployment ab wie bei `migrate`.
  - `php artisan permissions:sync-defaults --check` zeigt weiter, was fehlt, und ändert nichts. Exit-Code **0**: alles da, **2**: es fehlt etwas (die Einträge stehen in der Ausgabe, auch was `--add-missing` ergänzen würde; die Paare unter «NICHT ergänzt» zählen nicht), **1** oder anderes: die Prüfung konnte nicht laufen (Ausnahme, keine Datenbank). `deploy.sh` ruft die Prüfung am Ende jedes Deployments auf und schreibt die **Ausgabe ins Deploy-Log**: bei 2 eine **WARNUNG** mit der Liste dessen, was fehlt, bei anderen Fehlern eine **WARNUNG «Prüfung konnte nicht laufen»**. Nach `--add-missing` ist sie die Ausnahme.
  - **Der Reset für den Notfall:** `php artisan permissions:sync-defaults` ohne Option setzt die Standardbelegung auf die Konfiguration zurück und **überschreibt damit jede Anpassung in den Einstellungen**. Er läuft nie im Deployment. Da `deploy.sh` die Fassung vom Server ausführt, wirkt eine Änderung erst im Deployment nach dem ersten, das sie mitbringt.

### OAuth-Server (Auftrag 41)

Wer die Anmeldung als Dienst nutzt (`docs/oauth-sso.md`), braucht je Umgebung einmal ein Schlüsselpaar: **auf dem Server**, im Ordner
`backend/`, `php artisan passport:keys` (legt `storage/oauth-private.key` und `-public.key` an; nicht ins Repository). Danach prüfen:
`https://<API>/.well-known/jwks.json` liefert den öffentlichen Schlüssel. Die Migrationen laufen mit dem Deployment.

**`deploy.sh` bricht ab**, wenn die Schlüssel fehlen (weder `PASSPORT_PRIVATE_KEY` und `PASSPORT_PUBLIC_KEY` in `backend/.env` noch `storage/oauth-private.key` und `storage/oauth-public.key`) oder wenn die private Datei nicht die Rechte `600` (oder `400`) hat. Das Skript erzeugt die Schlüssel **nie** selbst: ein neues Paar würde alle Anmeldungen beenden. Die Prüfung läuft vor jeder Änderung; wie immer wirkt sie erst im Deployment nach dem ersten, das sie mitbringt, weil `deploy.sh` die Fassung vom Server ausführt.
### Datenbank-Sicherung vor jeder Migration (Auftrag 37 A, B)

TEST enthält die echten Planungsdaten (PROD gleich geregelt). Darum legt `deploy.sh` vor dem Aktualisieren des Codes und vor `migrate --force` einen Dump an. Die Arbeit macht `deploy/db-backup.sh` (`deploy <commit>` für das Deployment, `daily` für den Cron):

- **Wo:** `DEPLOY_BACKUP_DIR/db/planer-<Datum>-<Uhrzeit>-<Commit>.sql.gz` (Ordner `700`, Dateien `600`, `umask 077`). Aufbewahrt werden die **letzten 20** Sicherungen vor Deploys. Ältere Dateien im Ordner `DEPLOY_BACKUP_DIR` selbst (aus der Zeit vor Auftrag 37) bleiben liegen und lassen sich von Hand löschen.
- **Zugangsdaten:** Aus `backend/.env` in eine temporäre Optionsdatei (`mysqldump --defaults-extra-file`, Rechte `600`, im geschlossenen Ordner), die danach gelöscht wird. Das Passwort steht nie auf der Befehlszeile, nie in der Umgebung des Dumps und nie in der Ausgabe.
- **Schlägt der Dump fehl, wird nichts migriert:** Auch ein abgebrochener oder unvollständiger Dump (er muss gzip-gültig sein und mit «Dump completed» enden) zählt als Fehlschlag. Die halbe Datei wird gelöscht (der Dump entsteht als `.part` und erhält den Namen erst, wenn er vollständig ist), das Skript bricht ab. **Die Sicherung kommt vor dem Aktualisieren des Codes:** bei einem Fehlschlag sind Code, Datenbank und Wartungsmodus unverändert, der alte Code passt also weiter zum alten Schema. Der Dateiname trägt den Commit, der ausgerollt werden soll.
- **Prüfen:** `ls -l ~/test/backups/planer/db/` (PROD: `~/prod/...`) zeigt nach dem Deploy die neue Datei; `gzip -dc <Datei> | tail -n 1` endet mit «-- Dump completed on …».
- Wie bei jeder Änderung von `deploy.sh` wirkt das erst im Deployment nach dem ersten, das es mitbringt.

#### Tägliche Sicherung (Auftrag 37 B)

Zusätzlich zur Sicherung vor Deploys läuft einmal täglich `deploy/db-backup.sh daily`. Gleiche Technik, gleiche Rechte, gleicher Abbruch bei Fehlern (Exit 1, die Meldung steht auf der Fehlerausgabe).

- **Wo:** `DEPLOY_BACKUP_DIR/db/daily/planer-<Datum>-<Uhrzeit>.sql.gz`, aufbewahrt werden die **letzten 14**. Dazu die erste tägliche Sicherung jedes Monats als `DEPLOY_BACKUP_DIR/db/monthly/planer-<JahrMonat>.sql.gz`, aufbewahrt die **letzten 6** (also 6 Monate). Die Sicherungen vor Deploys (`db/planer-*.sql.gz`, die letzten 20) bleiben davon unberührt.
- **Cron-Eintrag** (Oliver richtet ihn im Panel des Webhosters ein, je Umgebung einer; Pfad und Zeit prüfen, nachts, nicht zur vollen Stunde, damit der Server nicht gleichzeitig ausgelastet ist). Die normale Ausgabe wird verworfen, damit Cron nur bei einem Fehler eine Mail schickt:
  ```
  17 3 * * * ~/test/apps/mollo-planer/deploy/db-backup.sh daily > /dev/null
  17 4 * * * ~/prod/apps/mollo-planer/deploy/db-backup.sh daily > /dev/null
  ```
  (Der erste Eintrag gilt für TEST, der zweite für PROD. Läuft das Skript nicht direkt, `bash ~/test/apps/…/deploy/db-backup.sh daily` schreiben. `mysqldump` muss im `PATH` von Cron liegen; einmal von Hand prüfen: `~/test/apps/mollo-planer/deploy/db-backup.sh daily` **auf dem Server**.)
- **Platzbedarf:** höchstens 20 + 14 + 6 = 40 Dumps je Umgebung, im Schnitt also das Vierzigfache der gepackten Datenbankgrösse. Die Grösse nach dem ersten Lauf **auf dem Server** ablesen: `ls -lh ~/test/backups/planer/db/daily/` und `du -sh ~/test/backups/planer`, und im Abschlussbericht melden.
- **Kopie ausser Haus:** noch nicht gebaut, braucht einen Entscheid (Vorschlag im Abschlussbericht von Auftrag 37). Die Sicherungen liegen sonst auf demselben Server wie die Datenbank.

#### Zurückspielen (Auftrag 37 C)

`deploy/db-restore.sh <Datei>` ersetzt die **ganze** Datenbank einer Umgebung durch eine Sicherung. Alles, was seit der Sicherung dazukam, ist danach weg. Darum fragt das Skript zuerst, und es gibt keinen Schalter, der die Frage überspringt.

1. **Auf dem Server** (per SSH angemeldet, im Ordner der Umgebung) die Sicherung wählen: `ls -lt ~/test/backups/planer/db/daily/ | head` (oder `db/monthly/`, oder die Sicherungen vor Deploys direkt in `db/`).
2. **Auf dem Server** starten (PROD: `~/prod/...`):
   ```bash
   # AUF DEM SERVER
   ~/test/apps/mollo-planer/deploy/db-restore.sh ~/test/backups/planer/db/daily/planer-20261124-031700.sql.gz
   ```
3. Das Skript prüft zuerst die Datei (gültiges gzip, endet mit «Dump completed») und zeigt **Umgebung, Datenbank, Datei, Datum und Grösse**. Zur Bestätigung tippst du den Namen der Umgebung ein (`staging` oder `production`, der Wert von `APP_ENV`). Jede andere Eingabe bricht ab, ohne etwas zu ändern.
4. Danach legt es **automatisch eine Sicherung des jetzigen Stands** an (`db/planer-<Datum>-<Uhrzeit>-vor-restore.sql.gz`). Schlägt sie fehl, wird nichts zurückgespielt.
5. Es schaltet den Wartungsmodus ein, spielt die Datei ein, führt `optimize:clear`, `lighthouse:clear-cache` und `lighthouse:cache` aus und schaltet den Wartungsmodus wieder aus. Bei einem Fehler in der Mitte wird der Wartungsmodus ebenfalls aufgehoben, die Meldung nennt dann die Sicherung des Stands von vorher.
6. **Stammt die Datei aus der Zeit vor neueren Migrationen,** passt das Schema nicht zum Code. `php artisan migrate:status` (im Ordner `backend/`, **auf dem Server**) zeigt es, `php artisan migrate --force` holt es nach. Das Skript tut das nicht von selbst.
7. Danach im Browser prüfen (Anmeldung, ein Projekt öffnen).

**Probe:** Das Zurückspielen wird einmal auf TEST mit Oliver geprobt, bevor man sich im Ernstfall darauf verlässt (Auftrag 37 C). Der Ablauf ist mit Stand-ins für `mysql`, `mysqldump` und `php` getestet (`DeployRestoreTest`), nicht gegen eine echte Datenbank.

#### Hochgeladene Dateien sichern (Auftrag 64)

Die Datenbank allein genügt nicht: Dokumente, Briefpapier und **ausgestellte Rechnungs-PDFs** liegen als Dateien in `backend/storage/app/` (`private/` und `public/`). Eine ausgestellte Rechnung wird nie neu erzeugt und ist eine Geschäftsunterlage mit Aufbewahrungspflicht. Darum sichert `deploy/files-backup.sh` diesen Ordner wie die Datenbank.

- **Ein Skript mehr, derselbe Cron-Eintrag:** `deploy/db-backup.sh daily` ruft am Ende `deploy/files-backup.sh daily` auf. Der Cron-Eintrag bleibt also unverändert. Schlägt die Sicherung der Dateien fehl, bleibt der Dump erhalten, und das Skript endet mit Exit 1 (die Meldung steht auf der Fehlerausgabe, Cron schickt eine Mail). `db-backup.sh deploy <commit>` sichert die Dateien **nicht**: eine Migration berührt sie nicht, und ein Fehler beim Packen soll kein Deployment aufhalten.
- **Wo:** `DEPLOY_BACKUP_DIR/files/daily/planer-files-<Datum>-<Uhrzeit>.tar.gz`, aufbewahrt die **letzten 14**; die erste tägliche Sicherung jedes Monats als `files/monthly/planer-files-<JahrMonat>.tar.gz`, die **letzten 6**. Ordner `700`, Dateien `600`.
- **Nur bei Änderung:** Die Dateien ändern sich selten. Ist seit der letzten täglichen Sicherung keine Datei neuer, packt das Skript nichts, meldet «Keine Änderung» und endet mit Exit 0. Die 14 Sicherungen reichen dann länger zurück. Nicht erkannt wird, wenn nur eine Datei gelöscht wurde; die nächste Änderung holt es nach.
- **Was drin ist:** nur der Ordner `backend/storage/app`. `storage/framework`, `storage/logs`, Caches und die OAuth-Schlüssel (`storage/oauth-*.key`, getrennt im Passwortmanager) liegen ausserhalb und kommen nicht in die Sicherung.
- **Sorgfalt wie beim Dump:** Das Archiv entsteht als `.part`, wird mit `tar -tzf` geprüft und erst dann umbenannt. Bei einem Fehler wird die halbe Datei gelöscht.
- **Platzbedarf:** höchstens 14 + 6 Archive je Umgebung, jedes so gross wie `storage/app`. **Auf dem Server** ablesen: `du -sh ~/test/backups/planer/files` (PROD: `~/prod/...`) und `du -sh <App-Ordner>/backend/storage/app`.

**Zurückspielen:** `deploy/files-restore.sh <Datei>` ersetzt den **ganzen** Ordner `backend/storage/app` durch eine Sicherung, mit denselben Schutzmassnahmen wie `db-restore.sh`: Es prüft die Datei (gültiges Archiv, nur der Ordner `app`), zeigt Umgebung, Datei, Datum und Grösse und verlangt den Namen der Umgebung (`APP_ENV`) als Bestätigung. Danach sichert es den jetzigen Stand (`files/planer-files-<Datum>-<Uhrzeit>-vor-restore.tar.gz`; schlägt das fehl, wird nichts zurückgespielt), entpackt neben den Ordner, schaltet den Wartungsmodus ein, tauscht die Ordner aus und schaltet ihn wieder aus. Geht der Austausch schief, kommt der alte Ordner zurück.

```bash
# AUF DEM SERVER
~/test/apps/mollo-planer/deploy/files-restore.sh ~/test/backups/planer/files/daily/planer-files-20261125-031700.tar.gz
```

**Probe:** Nach dem ersten Merge löst Oliver die tägliche Sicherung auf TEST einmal von Hand aus (`~/test/apps/mollo-planer/deploy/db-backup.sh daily`, **auf dem Server**); die Dateien liegen dann neben dem Dump. Getestet ist alles mit einem Wegwerf-Ordner (`DeployFilesBackupTest`, `DeployFilesRestoreTest`).

#### Schutz gegen Überschreiben (Auftrag 37 D)

Auf TEST (`APP_ENV=staging`) und PROD (`APP_ENV=production`) verweigern `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback`, `db:wipe` und `db:seed` den Dienst, auch mit `--force` (`App\Providers\AppServiceProvider`, Test `DestructiveCommandsProhibitedTest`). Der Schutz greift nur, wenn `APP_ENV` stimmt: **TEST muss auf `APP_ENV=staging` stehen** (offener Punkt aus Auftrag 28, Task «TEST auf APP_ENV=staging umstellen»). `migrate --force` im Deployment ist nicht betroffen.

## 14. Konto, Anmeldung und Mails (seit Onboarding-Härtung, Auftrag 22)

- **Passwortregel:** mindestens 12 Zeichen, keine Vorgaben zur Zusammensetzung, höchstens 72 Byte (bcrypt liest nicht mehr; längere werden mit Meldung abgelehnt, nicht still abgeschnitten). Neue Passwörter werden gegen bekannte Datenlecks geprüft. **Das ist ein ausgehender Aufruf des Servers an `https://api.pwnedpasswords.com/range/<erste 5 Zeichen des Hashs>`** (haveibeenpwned; das Passwort selbst verlässt den Server nicht). Bei einer Netzwerkfreigabe (Firewall) muss `api.pwnedpasswords.com` Port 443 erreichbar sein. Antwortet der Dienst nicht, wird das Passwort nach höchstens 3 Sekunden angenommen. Schalter: `PASSWORD_UNCOMPROMISED_CHECK` (Standard `true`, in Produktion an lassen; aus nur ohne Netz, zum Beispiel in den Browser-Tests). Bestehende Konten bleiben unberührt.
- **Drosseln je IP:** «Passwort vergessen» und «Passwort setzen» 5 pro Minute, Registrierung 5 pro Minute, Bestätigungslink 20 pro Minute, «Bestätigungsmail erneut senden» angemeldet 1 pro Minute, ohne Sitzung 3 pro Stunde je Adresse und je IP.
- **Neutrale Antworten:** «Passwort vergessen» antwortet immer 200 mit demselben Text, die Registrierung immer 201 (gleicher Körper), auch bei einer vergebenen Adresse. Nichts verrät, welche Adressen ein Konto haben. Bei einer vergebenen Adresse geht eine Mail an die Adresse (höchstens eine je Stunde).
- **Mails** laufen alle über die Queue (der Zeitplan arbeitet sie jede Minute ab, sie können eine Minute brauchen): Bestätigung, «Du hast schon ein Konto», Hinweis bei jeder Änderung der Anmeldung (Passwort, Authenticator, Passkey, Wiederherstellungscodes, Reset durch einen Admin), Freigabe und Ablehnung. Die Sprache steht in `users.locale` (Sprache bei der Registrierung bzw. Einladung); ist sie leer (ältere Konten, Konten per Befehlszeile), gilt `APP_LOCALE`.
- **Nach dem Zurücksetzen des Passworts** per Mail werden Sitzungen, «angemeldet bleiben» und die Zugänge für Claude beendet. **Sitzungen lassen sich nur beenden, wenn `SESSION_DRIVER=database` gilt** (Produktion); bei einem anderen Treiber läuft die Sitzung bis zu ihrem Ablauf weiter.
- **Anmeldung als Passwortbestätigung:** Wer sich mit dem Passwort angemeldet hat, muss es beim Einrichten des zweiten Faktors in den folgenden 3 Stunden (`AUTH_PASSWORD_TIMEOUT`) nicht nochmals eingeben. Gilt nicht für die Anmeldung allein mit Passkey.

---

## 15. Zwei Umgebungen: TEST und PROD (Auftrag 28)

Beide Umgebungen liegen auf demselben Konto beim Webhoster (nginx vor Apache, die `.htaccess` mit dem Block des Webhosters gilt weiter). Getrennt sind: Datenbank, `backend/.env`, Ordner, Cron-Eintrag, SSH-Schlüssel und GitHub-Umgebung.

| | TEST | PROD |
|---|---|---|
| Domains | `planer-test.example.com`, `api.planer-test.example.com` | `planer.example.com`, `api.planer.example.com` |
| Ordner der App | `~/test/apps/mollo-planer` | `~/prod/apps/mollo-planer` |
| Docroot Frontend | `~/www/planer-test.example.com` | `~/www/planer.example.com` |
| Docroot API | `~/www/api.planer-test.example.com` | `~/www/api.planer.example.com` |
| Sicherungen | `~/test/backups/planer` | `~/prod/backups/planer` |
| Branch auf dem Server (`DEPLOY_BRANCH`) | `dev` | `main` |
| Auslöser | Push auf `dev`, **ohne** Freigabe (GitHub-Umgebung `test`) | Push auf `main`, **mit** Freigabe (GitHub-Umgebung `production`) |
| `APP_ENV` | `staging` | `production` |
| `APP_NAME` | `"Mollo Planer (Test)"` | `"Mollo Planer"` |
| Datenbank | eigene, startet leer | eigene, startet leer |
| Admin | per `app:create-admin` | per `app:create-admin` |
| `PASSKEYS_RELYING_PARTY_ID` | `planer-test.example.com` | `planer.example.com` |

**`APP_ENV=staging`:** Alles, was die Produktion sperrt, bleibt auf TEST gesperrt (`App\Support\DeploymentEnvironment::isLive()` gilt für `production` und `staging`): die 2FA ist nicht abschaltbar, Webhooks gehen nur an öffentliche Adressen über https, keine Demo-Seeder (`DemoSeeder`, `DevelopmentHistorySeeder`, `E2eSeeder`), GraphQL ohne Introspektion, kein Fehler bei Lazy Loading, Schema-Cache von Lighthouse an. Offen ist nur ein Entwicklungsrechner (`local`, `testing`). Die App zeigt ausser in der Produktion das Band «Testumgebung» (`appSettings.isTestEnvironment`).
Hinweis für beide: Laravel fragt bei `migrate` und ähnlichen Befehlen nur in `production` nach einer Bestätigung. Auf TEST gibt es diese Rückfrage nicht, `deploy.sh` nutzt ohnehin `--force`; auf dem Server von Hand `migrate:fresh` oder `db:wipe` also nur mit Vorsicht, nie ohne Blick auf den Ordner.

### Pflichtwerte in `backend/.env` je Umgebung

```env
APP_ENV=staging                         # PROD: production
APP_NAME="Mollo Planer (Test)"          # PROD: "Mollo Planer"
APP_URL=https://api.planer-test.example.com    # PROD: https://api.planer.example.com
FRONTEND_URL=https://planer-test.example.com   # PROD: https://planer.example.com
SANCTUM_STATEFUL_DOMAINS=planer-test.example.com
SESSION_DOMAIN=.planer-test.example.com
PASSKEYS_RELYING_PARTY_ID=planer-test.example.com
# DEPLOY_PHP bleibt leer: beim Webhoster ist `php` immer 8.5
DEPLOY_BRANCH=dev                       # PROD: main
DEPLOY_WEB_ROOT=~/www/planer-test.example.com         # PROD: ~/www/planer.example.com
DEPLOY_BACKUP_DIR=~/test/backups/planer         # PROD: ~/prod/backups/planer
```

`deploy.sh` ermittelt den App-Ordner aus seinem eigenen Pfad und liest `DEPLOY_BRANCH`, `DEPLOY_WEB_ROOT`, `DEPLOY_BACKUP_DIR` und `FRONTEND_URL` **vor jeder Änderung**. Fehlt etwas, zeigt `DEPLOY_WEB_ROOT` auf keinen Ordner oder ist sein letzter Pfadteil nicht der Host von `FRONTEND_URL`, bricht es mit «… Nichts wurde geändert.» ab. Das Log beginnt mit Umgebung, Branch, App-Ordner, Docroot und Sicherungsordner.

### Je ein Schlüssel, je ein Cron-Eintrag

- `~/.ssh/authorized_keys`: **zwei** Zeilen, je ein eigener Schlüssel, der nur das Skript seiner Umgebung startet. Dadurch kann der Client die Umgebung nicht wählen:
  ```
  command="/home/<benutzer>/test/apps/mollo-planer/deploy/deploy.sh",restrict ssh-ed25519 AAAA… github-actions-deploy planer-test
  command="/home/<benutzer>/prod/apps/mollo-planer/deploy/deploy.sh",restrict ssh-ed25519 AAAA… github-actions-deploy planer-prod
  ```
- Cron (Panel des Webhosters, Pfad prüfen, Abschnitt 12), **zwei** Einträge:
  ```
  * * * * * cd ~/test/apps/mollo-planer/backend && php artisan schedule:run >> /dev/null 2>&1
  * * * * * cd ~/prod/apps/mollo-planer/backend && php artisan schedule:run >> /dev/null 2>&1
  ```

### GitHub: Umgebungen

*Settings → Environments:* **`test`** (keine Reviewer) und **`production`** (Reviewer: Oliver). In **jeder** Umgebung (nicht auf Repository-Ebene): Secret `DEPLOY_SSH_KEY` (der Schlüssel der Umgebung) und die Variablen `API_BASE` (`https://api.planer-test.example.com` bzw. `.ch`) und `FRONTEND_URL` (`https://planer-test.example.com` bzw. `.ch`). Auf Repository-Ebene bleiben `DEPLOY_HOST`, `DEPLOY_USER` und `DEPLOY_KNOWN_HOSTS` (gleicher Server). Fehlen `API_BASE` oder `FRONTEND_URL` in der Umgebung, bricht der Lauf vor dem Build ab. Die Umgebung `production` aus Abschnitt 7 («keine Variablen oder Secrets in dieser Umgebung») gilt nicht mehr.

| Push auf | GitHub-Umgebung | Server | Freigabe |
|---|---|---|---|
| `dev` | `test` | `~/test/apps/mollo-planer`, Branch `dev` | nein |
| `main` | `production` | `~/prod/apps/mollo-planer`, Branch `main` | ja, Oliver |
| anderer Branch | kein Deploy | | |

Beim manuellen Start (*Run workflow*) wählt man die Umgebung; sie muss zum Branch passen (`test` nur von `dev`, `production` nur von `main`), sonst bricht der Lauf ab: Der Server setzt sich auf **seinen** Branch, ein anderes Frontend würde zu fremdem Code passen.

### Docroot der API (`index.php`)

Die Docroots liegen unter `~/www/<domain>` (der Webhoster legt sie an, Abschnitt 12). Der API-Docroot enthält eine eigene `index.php` mit absoluten Pfaden auf `<App-Ordner>/backend`. Der Deploy pflegt sie **nicht** (sie liegt ausserhalb des App-Ordners und gehört Webhoster; ein Skript, das dort schreibt, könnte bei einem Pfadfehler die andere Umgebung treffen). Sie wird bei Umzug und Ersteinrichtung **einmal von Hand** angepasst, danach nur, wenn sich `backend/public/index.php` ändert (selten).

### TEST einrichten (Umzug des bisherigen App-Ordners, leere Datenbank)

Beide Umgebungen starten mit leerer Datenbank, es gibt keine Datenmigration; Konten, Passkeys und Projekte der alten Installation entfallen, der Admin wird neu angelegt. Der bisherige App-Ordner `~/apps/<projekt>` zieht nach `~/test/apps/<projekt>` um.

```bash
# AUF DEM SERVER (per SSH angemeldet, z. B. mit deinem persönlichen Schlüssel)
# 1. Wartungsmodus an (alter Pfad), dann Ordner verschieben
cd ~/apps/mollo-planer/backend && php artisan down --retry=60
mkdir -p ~/test/apps ~/test/backups/planer && chmod 700 ~/test/backups/planer
mv ~/apps/mollo-planer ~/test/apps/mollo-planer

# 2. index.php der API auf den neuen Ort stellen (drei Stellen) und prüfen
cp ~/www/api.planer-test.example.com/index.php ~/www/api.planer-test.example.com/index.php.bak
sed -i.bak 's#/home/<benutzer>/apps/mollo-planer#/home/<benutzer>/test/apps/mollo-planer#g' ~/www/api.planer-test.example.com/index.php
grep -n "mollo-planer" ~/www/api.planer-test.example.com/index.php
rm ~/www/api.planer-test.example.com/index.php.bak

# 3. backend/.env: andere Datenbank (die leere von TEST) und die neuen Werte
nano ~/test/apps/mollo-planer/backend/.env
#   APP_ENV=staging, APP_NAME="Mollo Planer (Test)",
#   DB_DATABASE=<Datenbank TEST>, DB_USERNAME=<Benutzer TEST>, DB_PASSWORD=<…>,
#   DEPLOY_BRANCH=dev, DEPLOY_WEB_ROOT=~/www/planer-test.example.com, DEPLOY_BACKUP_DIR=~/test/backups/planer
cd ~/test/apps/mollo-planer/backend
php artisan config:clear
php artisan config:show database.connections.mariadb.database      # muss die Datenbank von TEST sein, sonst STOPP

# 4. Branch auf dev, Datenbank aufbauen, Admin anlegen, Caches
cd ~/test/apps/mollo-planer && git fetch origin dev && git checkout -f -B dev origin/dev
cd backend
php /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan planer:sync-templates
php artisan config:cache && php artisan route:cache && php artisan event:cache
php artisan lighthouse:clear-cache && php artisan lighthouse:cache
php artisan app:create-admin "<Name>" "<E-Mail>"
php artisan up
```

Danach (im Panel, auf dem Server, lokal wie angegeben): Cron-Eintrag im Panel auf `~/test/apps/mollo-planer/backend`, in `authorized_keys` die Zeile des TEST-Schlüssels mit `command=` auf `/home/<benutzer>/test/apps/mollo-planer/deploy/deploy.sh` (auf dem Server, vorher sichern, Abschnitt 6), `curl -si https://api.planer-test.example.com/up | head -3` (lokal, erwartet 200), im Browser `https://planer-test.example.com` (Band «Testumgebung», Anmeldung). Die neue `deploy.sh` kommt mit dem Checkout in Schritt 4; die alte kennt `DEPLOY_*` nicht.

### Ersteinrichtung PROD

1. **Panel des Webhosters:** Domains `planer.example.com` und `api.planer.example.com` mit SSL anlegen (die Docroots entstehen unter `~/www/`), PHP 8.5, eine **eigene Datenbank mit eigenem Benutzer**, `memory_limit` 256M und `max_execution_time` 60 (Abschnitt 12).
2. **Code holen:**
   ```bash
   # AUF DEM SERVER (per SSH angemeldet, z. B. mit deinem persönlichen Schlüssel)
   mkdir -p ~/prod/apps/mollo-planer ~/prod/backups/planer && cd ~/prod/apps/mollo-planer
   git init -q && git remote add origin https://github.com/<owner>/<repo>.git
   git fetch -q origin main && git checkout -q -f -B main origin/main
   chmod 700 ~/prod/backups/planer
   ```
3. **`backend/.env`** neu anlegen (`chmod 600`, Vorlage die von TEST): eigener `APP_KEY` (`php artisan key:generate --show`), eigene Datenbank, `APP_ENV=production`, `APP_NAME="Mollo Planer"`, `APP_URL=https://api.planer.example.com`, `FRONTEND_URL=https://planer.example.com`, `SANCTUM_STATEFUL_DOMAINS=planer.example.com`, `SESSION_DOMAIN=.planer.example.com`, `PASSKEYS_RELYING_PARTY_ID=planer.example.com`, Mail, `DEPLOY_BRANCH=main`, `DEPLOY_WEB_ROOT=~/www/planer.example.com`, `DEPLOY_BACKUP_DIR=~/prod/backups/planer` (Rest wie Abschnitt 5).
4. **Installieren** (enthält die Datenbank noch alte Daten, vorher den Namen prüfen und dann `migrate:fresh --force` statt `migrate --force`, Abschnitt 12):
   ```bash
   # AUF DEM SERVER (per SSH angemeldet, z. B. mit deinem persönlichen Schlüssel)
   cd ~/prod/apps/mollo-planer/backend
   php /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction
   php artisan config:show database.connections.mariadb.database      # muss die Datenbank von PROD sein
   php artisan migrate --force
   php artisan planer:sync-templates
   php artisan config:cache && php artisan route:cache && php artisan event:cache
   php artisan lighthouse:cache
   php artisan app:create-admin "<Name>" "<E-Mail>"
   ```
5. **Docroot der API** (`~/www/api.planer.example.com`): `index.php`, `robots.txt`, `favicon.ico` von TEST kopieren und die Pfade umstellen, `.htaccess` **nicht** kopieren (Block des Webhosters der Domain behalten, Laravel-Regeln darunter anhängen):
   ```bash
   # AUF DEM SERVER (per SSH angemeldet, z. B. mit deinem persönlichen Schlüssel)
   D=~/www/api.planer.example.com
   cp ~/www/api.planer-test.example.com/index.php ~/www/api.planer-test.example.com/robots.txt ~/www/api.planer-test.example.com/favicon.ico $D/
   sed -i.bak 's#/test/apps/mollo-planer#/prod/apps/mollo-planer#g' $D/index.php && rm $D/index.php.bak
   grep -n "mollo-planer" $D/index.php
   cat ~/prod/apps/mollo-planer/backend/public/.htaccess >> $D/.htaccess
   curl -si https://api.planer.example.com/up | head -3
   ```
6. **Cron** (Eintrag oben) und **eigener Deploy-Schlüssel** (Abschnitt 6, `command=` auf das PROD-Skript).
7. **GitHub:** Umgebungen `test` und `production` wie oben, mit Secret `DEPLOY_SSH_KEY` und den Variablen `API_BASE` und `FRONTEND_URL` **in der jeweiligen Umgebung**.
8. **Erste Deploys:** Push auf `dev` (TEST, läuft sofort), danach Merge `dev` → `main` (PROD, Freigabe). Anschliessend die Nachkontrolle mit Stuart (`docs/onboarding-leitfaden.md` §6.4) für **beide** Umgebungen.

Die Rauchtests am Ende von `deploy.sh` (GraphQL, Passkey-RP-ID, `APP_NAME` auf TEST) laufen in beiden Umgebungen mit ihren eigenen Werten.
