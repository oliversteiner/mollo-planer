# Deploy-Template: <Projektname>

Dieses Formular ausfüllen und Claude übergeben, zusammen mit dem Satz: „Deploye das Projekt nach diesem Template.“

Passwörter und Tokens gehören **nicht** in den Chat. Sie werden direkt in die `.env` auf dem Server bzw. in die GitHub-Secrets eingetragen. Falls doch einmal ein Passwort im Chat stand, danach ändern.

Platzhalter `<…>` ersetzen. Felder, die nicht zutreffen, streichen.

---

## 1. Voraussetzungen

- Hostpoint-Webhosting mit SSH-Zugang
- PHP im Hostpoint-Panel: `<8.5>`. Auf dem Server liegt die Version unter `/usr/local/php<85>/bin/php`
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
| Hostpoint-SSH-Host | `<servername.hostpoint.ch>` |
| SSH-Benutzer | `<username>` |
| Projektpfad auf dem Server | `~/apps/<projekt>` |
| Docroot Frontend | `~/www/<frontend-domain>` |
| Docroot API | `~/www/<api-domain>` |

---

## 3. Datenbank (Hostpoint-Panel)

- **DB-Host:** `<benutzer>.mysql.db.internal` (nicht `localhost`, dort fehlt der Socket)
- **DB-Name:** `<datenbank>`
- **DB-Benutzer:** `<benutzer>`
- **DB-Passwort:** nur in `backend/.env` auf dem Server

---

## 4. E-Mail (Hostpoint)

- **SMTP-Host:** `asmtp.mail.hostpoint.ch`
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
MAIL_HOST=asmtp.mail.hostpoint.ch
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

Nur für CI, ohne Passphrase, nur für dieses Projekt:

```bash
ssh-keygen -t ed25519 -N '' -C 'github-actions-deploy <projekt>' -f ~/.ssh/<projekt>-deploy
```

- **Server:** In `~/.ssh/authorized_keys` eine Zeile anhängen, eingeschränkt auf das Deploy-Skript:
  ```
  command="/home/<benutzer>/apps/<projekt>/deploy/deploy.sh",restrict ssh-ed25519 AAAA… github-actions-deploy <projekt>
  ```
  Vorher `authorized_keys` sichern. Die Datei hat oft keinen Zeilenumbruch am Ende, deshalb vor dem Anhängen eine Leerzeile setzen.
- **Host-Schlüssel:** `ssh-keygen -F <ssh-host> | grep -v '^#'`. Die Zeile wird als Secret `DEPLOY_KNOWN_HOSTS` eingetragen.

---

## 7. GitHub

GitHub hat für Secrets und Variablen dieselbe Oberfläche, nur mit verschiedenen Reitern. Die Unterscheidung ist leicht zu übersehen, deshalb hier genau:

- **Secrets** (Reiter *Secrets*): verschlüsselt, nach dem Speichern nicht mehr einsehbar.
- **Variablen** (Reiter *Variables*): Klartext, im Repo-Einstellungen sichtbar. Für Hostnamen und Benutzer.

Beides auf **Repository-Ebene** anlegen, nicht in der Umgebung `production`. Der Deploy-Job liest die Variablen aus dem Repository. Liegen sie nur in einer Umgebung, bleiben sie für den Job leer.

### Secrets

**Settings → Secrets and variables → Actions → Reiter „Secrets“ → New repository secret**

- `DEPLOY_SSH_KEY`: Inhalt von `~/.ssh/<projekt>-deploy` (`pbcopy < ~/.ssh/<projekt>-deploy`)
- `DEPLOY_KNOWN_HOSTS`: Ausgabe des Host-Schlüssel-Befehls aus Abschnitt 6
- `FONTAWESOME_NPM_AUTH_TOKEN`: nur bei Font Awesome Pro
- `WEBAWESOME_NPM_AUTH_TOKEN`: nur bei Web Awesome Pro

### Variablen

**Settings → Secrets and variables → Actions → Reiter „Variables“ → New repository variable**

- `DEPLOY_HOST`: Hostname des Hostpoint-Servers, z. B. `<servername.hostpoint.ch>`. Muss zum Eintrag in `DEPLOY_KNOWN_HOSTS` passen.
- `DEPLOY_USER`: SSH-Benutzer, z. B. `<username>`

Häufiger Fehler: Eine Variable in der Umgebung `production` statt auf Repository-Ebene anlegen. Dann findet der Deploy-Job den Wert nicht.

### Umgebung

**Settings → Environments → New environment:** Name `production`, unter *Required reviewers* den eigenen Account eintragen. Keine Variablen oder Secrets in dieser Umgebung anlegen.

---

## 8. Einmalige Einrichtung auf dem Server

1. Projekt holen:
   ```bash
   mkdir -p ~/apps/<projekt> && cd ~/apps/<projekt>
   git init -q && git remote add origin https://github.com/<owner>/<repo>.git
   git fetch -q origin main && git checkout -q -f -B main origin/main
   ```
2. `backend/.env` aus Abschnitt 5 anlegen, `chmod 600`.
3. Backend-Abhängigkeiten mit PHP 8.5 (auf Hostpoint ist `php` seit Neuem immer 8.5, prüfen mit `php -v`; `DEPLOY_PHP` in `backend/.env` ist nur nötig, wenn das `php` des Servers einmal nicht 8.5 wäre):
   ```bash
   cd ~/apps/<projekt>/backend
   php /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction
   php artisan storage:link
   ```
4. **Migration nur nach Freigabe und mit Backup:** `php artisan migrate --force`, danach `php artisan planer:sync-templates` (schreibt die mitgelieferten Vorlagen aus `spec/templates/` in die Datenbank, wiederholbar).
   **Vor dem Deployment von M4:** `php artisan planer:check-limits` (listet je Projekt, wo Daten eine Grenze der Planer-Datei verletzen: 200 Milestones, 50 ToDos je Milestone, 50 Tasks je ToDo, 50 Mitglieder, 30 Labels, Beschreibung höchstens 5000 Zeichen als Markdown; ändert nichts, Exit-Code 1 bei Funden). Funde zuerst von Hand bereinigen: Ein Projekt mit Überschreitungen lässt sich nicht exportieren.
   **Breaking Change am Connector (M4 I3):** Der MCP-Server hat nur noch `list_projects`, `list_users`, `list_templates`, `export_plan` und `import_plan`. `get_project`, `create_project`, `set_project_member`, `create_project_plan`, `add_to_plan`, `update_plan_item`, `move_milestone` und `set_task_status` entfallen. Zugänge und Konfiguration bleiben gültig. Wer sie nutzt, vor dem Deployment informieren (`docs/claude-connector.md`).
5. Docroot der API (`~/www/<api-domain>`): Da Hostpoint diesen Ordner selbst anlegt, wird er gefüllt statt verlinkt:
   - `index.php` kopieren und die Pfade auf `~/apps/<projekt>/backend` umstellen (`__DIR__.'/../` durch den absoluten Pfad ersetzen).
   - Die Hostpoint-Zeilen oben in der `.htaccess` (`Use php-fpm latest` und `#@__HCP_END__@#`) behalten, darunter die Laravel-Regeln aus `backend/public/.htaccess`.
   - `robots.txt`, `favicon.ico` und den Symlink `storage` kopieren.
6. Docroot des Frontends (`~/www/<frontend-domain>`): leer lassen, der Deploy befüllt ihn.

---

### Cron (Queue und Scheduler)

Mails und Webhooks (Benachrichtigung bei Änderungen der Planer-Datei) warten in der Queue (`QUEUE_CONNECTION=database`). Auf dem Hostpoint-Server läuft kein Dauerprozess. Darum startet der Scheduler jede Minute einen Worker, der arbeitet, bis die Queue leer ist (`routes/console.php`: `queue:work --stop-when-empty --max-time=55`). Dazu genügt **ein** Cron-Eintrag im Hostpoint-Panel (Cron-Jobs), jede Minute:

```
* * * * * cd <api-pfad> && <php-pfad> artisan schedule:run >> /dev/null 2>&1
```

`<api-pfad>` ist der Ordner des Backends (`~/apps/<projekt>/backend`), `<php-pfad>` das PHP 8.5 des Servers: auf Hostpoint genügt `php`. Cron hat manchmal einen anderen `PATH` als SSH; einmal prüfen mit dem Eintrag `* * * * * php -v > ~/cron-php.txt` und die Datei nach einer Minute lesen, dann den Eintrag wieder ersetzen.

**Ohne diesen Eintrag gehen weder Mails noch Webhooks raus**, und es erscheint kein Fehler. Prüfen: In der App einen Task zuweisen oder in den Projekteinstellungen „Testen“ drücken; innerhalb von zwei Minuten kommt die Mail bzw. der Test an. Der Eintrag ist ein einmaliger Schritt (macht Oliver im Panel).

### Nach dem Deploy und bei Problemen

- **Assistent prüfen:** `php artisan assistant:ping` schickt eine kleine echte Anfrage mit dem echten Schema an die Claude-API und meldet OK oder den Fehler der API (schreibt nichts, braucht `ANTHROPIC_API_KEY`). Nach dem Deploy auf dem Server laufen lassen, dann einen Entwurf im Browser auslösen.
- **Kein `migrate:rollback` in Produktion.** Einige `down()`-Migrationen verlieren Daten. Der Rückweg ist die Datenbank-Sicherung (`deploy.sh` legt sie vor den Migrationen an) plus der vorherige Release.
- **Frontend-Build:** `NUXT_PUBLIC_API_BASE` ist die Build-Variable mit der API-Adresse. Leer gilt nur in der Entwicklung (dort wird die Adresse aus dem Host der Seite abgeleitet), in Produktion muss sie gesetzt sein.

## 9. Skript und Workflow anpassen

Vorlage: `deploy/deploy.sh` und `.github/workflows/deploy.yml` aus dem Projekt `mollo-planer`.

- In `deploy.sh` die Variablen oben anpassen: `APP`, `BACKEND`, `WEB_ROOT`, `PHP`, `COMPOSER`. Das PHP nimmt das Skript aus der Zeile `DEPLOY_PHP=` in `backend/.env` auf dem Server (voller Pfad); fehlt sie oder ist sie leer, gilt `php`, auf Hostpoint immer 8.5. Welches `php` eine SSH-Sitzung ohne Terminal findet, hängt von der Umgebung des Servers ab, darum den Pfad ausdrücklich setzen.
- **Wichtig:** `deploy.sh` führt die Fassung aus, die **auf dem Server liegt**, bevor es den neuen Code holt. Eine Änderung an `deploy.sh` wirkt deshalb erst beim übernächsten Deploy, ausser man holt die Datei vorher von Hand: `git fetch origin main && git checkout origin/main -- deploy/deploy.sh`.
- Bei einem Frontend ohne Backend die Schritte Datenbank-Backup, Migrationen und Caches streichen.
- In `deploy.yml` die Umgebungsvariable `API_BASE` anpassen. `DEPLOY_HOST` und `DEPLOY_USER` kommen aus den GitHub-Variablen (Abschnitt 7).
- Die Tests-Jobs an das Projekt anpassen oder streichen.

---

## 10. Deploy starten

1. Änderungen per PR nach `main` mergen.
2. Der Lauf `deploy` wartet im Environment `production`. Dort **Review deployments** öffnen und freigeben.
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

- **DB-Host:** Nicht `localhost` verwenden. Der Socket existiert auf Hostpoint nicht.
- **PHP-Version:** `deploy.sh` nimmt `DEPLOY_PHP` aus `backend/.env` (voller Pfad), sonst `php` (auf Hostpoint immer 8.5). Ist die Version älter als 8.5, bricht es ab, bevor etwas geändert wird, und nennt den benutzten Pfad. Zeigt der Pfad auf eine nicht ausführbare Datei, bricht es ebenfalls ab. Die Shell-Sitzung mit Terminal und die ohne Terminal können verschiedene `php` finden; massgeblich ist `DEPLOY_PHP`.
- **Schema-Cache von Lighthouse liegt ausserhalb von Git:** Ausserhalb von `APP_ENV=local` liest Lighthouse das GraphQL-Schema aus `bootstrap/cache/lighthouse-schema.php`. Die Datei ist nicht in Git, `git reset` lässt sie stehen. Ein alter Stand zeigt auf Resolver-Klassen, die es nicht mehr gibt («Failed to find class … for directive @field», «Internal server error»). `deploy.sh` führt darum nach `config:cache` `lighthouse:clear-cache` und `lighthouse:cache` aus. Der Query-Cache (`query_cache`, Modus `store`) braucht das nicht: Er hält geparste Abfragetexte, die vom Schema unabhängig sind. Von Hand: `php artisan lighthouse:clear-cache && php artisan lighthouse:cache`.
- **Rauchtest nach dem Deploy:** Nach `artisan up` schickt `deploy.sh` `{ viewerRights }` ohne Sitzung an `APP_URL/api` (ohne `Origin`, damit Sanctum die Anfrage nicht als SPA-Anfrage mit CSRF behandelt). Erwartet: die Antwort enthält `Unauthenticated.`. Sonst meldet der Lauf «Rauchtest GraphQL fehlgeschlagen: …» und endet mit Fehler. Der neue Code ist dann schon live, es wird nichts zurückgerollt.
- **Passkey-RP-ID:** Jede App hat ihre eigene RP-ID (kein Single Sign-on). `PASSKEYS_RELYING_PARTY_ID` in Produktion **immer** setzen, gleich dem Host von `FRONTEND_URL` (z. B. `planer.mollo.dev`), nie die Hauptdomain. Ein Wechsel macht alle Passkeys ungültig. Ein Passkey einer anderen App unter der Hauptdomain erscheint sonst in der Auswahl und die Anmeldung endet mit 422. `deploy.sh` warnt im Log, wenn der Wert vom Host von `FRONTEND_URL` abweicht.
- **Erster Lauf:** Liegt `deploy/deploy.sh` schon unverwaltet auf dem Server, vorher `git fetch origin main && git reset --hard origin/main` ausführen.
- **Hostpoint-Block in `.htaccess`:** Nicht löschen, sonst wechselt die PHP-Version des Docroots.
- **Frontend-Build:** Die API-Adresse wird beim Build eingebaut (`NUXT_PUBLIC_API_BASE`). Ändert sich die Domain, neu bauen.
- **Passwörter:** Nie im Chat oder im Repo. Nach versehentlicher Weitergabe ändern.

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

Alle Befehle laufen **AUF DEM SERVER** (per SSH, im Ordner `backend/`; Hostpoint: `php` ist 8.5). Auf TEST und PROD je einzeln.

```bash
# AUF DEM SERVER
php artisan app:create-agent "Claude Club DB" claude-clubdb@example.ch   # legt das Konto an (Rückfrage), Systemrolle Mitglied
php artisan app:user-token claude-clubdb@example.ch "Claude Code"         # macht das Token, zeigt es EINMAL (Rückfrage)
php artisan app:user-token claude-clubdb@example.ch --revoke              # widerruft alle Tokens des Kontos
```

- Die Adresse muss keine echte Mailbox sein (es geht nie eine Mail dorthin).
- Danach **in der App** (als Owner oder Manager des Projekts): das Konto als Mitglied aufnehmen, mit der **Projektrolle Agent**. Sie darf den Plan exportieren und importieren, Aufgaben ändern, zuweisen, verschieben und kommentieren, aber keine Mitglieder und keine Projekteinstellungen ändern und nichts löschen.
- Das Token gehört in die Einstellung des Connectors (siehe `docs/claude-connector.md`), nicht in `.env`, nicht ins Repository. Es gilt 90 Tage; danach ein neues machen.
- `app:user-token` verweigert Konten von Menschen: die machen ihr Token selbst im Profil.
- Auf einem Bestand, dessen Rollen vor Auftrag 31 angelegt wurden, einmal `php artisan permissions:sync-defaults` (holt die Projektrolle Agent).

## 14. Konto, Anmeldung und Mails (seit Onboarding-Härtung, Auftrag 22)

- **Passwortregel:** mindestens 12 Zeichen, keine Vorgaben zur Zusammensetzung, höchstens 72 Byte (bcrypt liest nicht mehr; längere werden mit Meldung abgelehnt, nicht still abgeschnitten). Neue Passwörter werden gegen bekannte Datenlecks geprüft. **Das ist ein ausgehender Aufruf des Servers an `https://api.pwnedpasswords.com/range/<erste 5 Zeichen des Hashs>`** (haveibeenpwned; das Passwort selbst verlässt den Server nicht). Bei einer Netzwerkfreigabe (Firewall) muss `api.pwnedpasswords.com` Port 443 erreichbar sein. Antwortet der Dienst nicht, wird das Passwort nach höchstens 3 Sekunden angenommen. Schalter: `PASSWORD_UNCOMPROMISED_CHECK` (Standard `true`, in Produktion an lassen; aus nur ohne Netz, zum Beispiel in den Browser-Tests). Bestehende Konten bleiben unberührt.
- **Drosseln je IP:** «Passwort vergessen» und «Passwort setzen» 5 pro Minute, Registrierung 5 pro Minute, Bestätigungslink 20 pro Minute, «Bestätigungsmail erneut senden» angemeldet 1 pro Minute, ohne Sitzung 3 pro Stunde je Adresse und je IP.
- **Neutrale Antworten:** «Passwort vergessen» antwortet immer 200 mit demselben Text, die Registrierung immer 201 (gleicher Körper), auch bei einer vergebenen Adresse. Nichts verrät, welche Adressen ein Konto haben. Bei einer vergebenen Adresse geht eine Mail an die Adresse (höchstens eine je Stunde).
- **Mails** laufen alle über die Queue (der Zeitplan arbeitet sie jede Minute ab, sie können eine Minute brauchen): Bestätigung, «Du hast schon ein Konto», Hinweis bei jeder Änderung der Anmeldung (Passwort, Authenticator, Passkey, Wiederherstellungscodes, Reset durch einen Admin), Freigabe und Ablehnung. Die Sprache steht in `users.locale` (Sprache bei der Registrierung bzw. Einladung); ist sie leer (ältere Konten, Konten per Befehlszeile), gilt `APP_LOCALE`.
- **Nach dem Zurücksetzen des Passworts** per Mail werden Sitzungen, «angemeldet bleiben» und die Zugänge für Claude beendet. **Sitzungen lassen sich nur beenden, wenn `SESSION_DRIVER=database` gilt** (Produktion); bei einem anderen Treiber läuft die Sitzung bis zu ihrem Ablauf weiter.
- **Anmeldung als Passwortbestätigung:** Wer sich mit dem Passwort angemeldet hat, muss es beim Einrichten des zweiten Faktors in den folgenden 3 Stunden (`AUTH_PASSWORD_TIMEOUT`) nicht nochmals eingeben. Gilt nicht für die Anmeldung allein mit Passkey.

---

## 15. Zwei Umgebungen: TEST und PROD (Auftrag 28)

Beide Umgebungen liegen auf demselben Hostpoint-Konto (nginx vor Apache, die `.htaccess` mit dem Hostpoint-Block gilt weiter). Getrennt sind: Datenbank, `backend/.env`, Ordner, Cron-Eintrag, SSH-Schlüssel und GitHub-Umgebung.

| | TEST | PROD |
|---|---|---|
| Domains | `planer.mollo.dev`, `api.planer.mollo.dev` | `planer.mollo.ch`, `api.planer.mollo.ch` |
| Ordner der App | `~/dev/apps/mollo-planer` | `~/ch/apps/mollo-planer` |
| Docroot Frontend | `~/dev/www/planer.mollo.dev` | `~/ch/www/planer.mollo.ch` |
| Docroot API | `~/dev/www/api.planer.mollo.dev` | `~/ch/www/api.planer.mollo.ch` |
| Sicherungen | `~/dev/backups/planer` | `~/ch/backups/planer` |
| Branch auf dem Server (`DEPLOY_BRANCH`) | `dev` | `main` |
| Auslöser | Push auf `dev`, **ohne** Freigabe (GitHub-Umgebung `test`) | Push auf `main`, **mit** Freigabe (GitHub-Umgebung `production`) |
| `APP_ENV` | `staging` | `production` |
| `APP_NAME` | `"Mollo Planer (Test)"` | `"Mollo Planer"` |
| Daten | die heutigen Daten (ziehen um) | startet leer, Admin per `app:create-admin` |
| `PASSKEYS_RELYING_PARTY_ID` | `planer.mollo.dev` | `planer.mollo.ch` |

**`APP_ENV=staging`:** Alles, was die Produktion sperrt, bleibt auf TEST gesperrt (`App\Support\DeploymentEnvironment::isLive()` gilt für `production` und `staging`): die 2FA ist nicht abschaltbar, Webhooks gehen nur an öffentliche Adressen über https, keine Demo-Seeder (`DemoSeeder`, `DevelopmentHistorySeeder`, `E2eSeeder`), GraphQL ohne Introspektion, kein Fehler bei Lazy Loading, Schema-Cache von Lighthouse an. Offen ist nur ein Entwicklungsrechner (`local`, `testing`). Die App zeigt ausser in der Produktion das Band «Testumgebung» (`appSettings.isTestEnvironment`).
Hinweis für beide: Laravel fragt bei `migrate` und ähnlichen Befehlen nur in `production` nach einer Bestätigung. Auf TEST gibt es diese Rückfrage nicht, `deploy.sh` nutzt ohnehin `--force`; auf dem Server von Hand `migrate:fresh` oder `db:wipe` also nur mit Vorsicht, nie ohne Blick auf den Ordner.

### Pflichtwerte in `backend/.env` je Umgebung

```env
APP_ENV=staging                         # PROD: production
APP_NAME="Mollo Planer (Test)"          # PROD: "Mollo Planer"
APP_URL=https://api.planer.mollo.dev    # PROD: https://api.planer.mollo.ch
FRONTEND_URL=https://planer.mollo.dev   # PROD: https://planer.mollo.ch
SANCTUM_STATEFUL_DOMAINS=planer.mollo.dev
SESSION_DOMAIN=.planer.mollo.dev
PASSKEYS_RELYING_PARTY_ID=planer.mollo.dev
# DEPLOY_PHP bleibt leer: auf Hostpoint ist `php` immer 8.5
DEPLOY_BRANCH=dev                       # PROD: main
DEPLOY_WEB_ROOT=~/dev/www/planer.mollo.dev     # PROD: ~/ch/www/planer.mollo.ch
DEPLOY_BACKUP_DIR=~/dev/backups/planer         # PROD: ~/ch/backups/planer
```

`deploy.sh` ermittelt den App-Ordner aus seinem eigenen Pfad und liest `DEPLOY_BRANCH`, `DEPLOY_WEB_ROOT`, `DEPLOY_BACKUP_DIR` und `FRONTEND_URL` **vor jeder Änderung**. Fehlt etwas, zeigt `DEPLOY_WEB_ROOT` auf keinen Ordner oder ist sein letzter Pfadteil nicht der Host von `FRONTEND_URL`, bricht es mit «… Nichts wurde geändert.» ab. Das Log beginnt mit Umgebung, Branch, App-Ordner, Docroot und Sicherungsordner.

### Je ein Schlüssel, je ein Cron-Eintrag

- `~/.ssh/authorized_keys`: **zwei** Zeilen, je ein eigener Schlüssel, der nur das Skript seiner Umgebung startet. Dadurch kann der Client die Umgebung nicht wählen:
  ```
  command="/home/<benutzer>/dev/apps/mollo-planer/deploy/deploy.sh",restrict ssh-ed25519 AAAA… github-actions-deploy planer-test
  command="/home/<benutzer>/ch/apps/mollo-planer/deploy/deploy.sh",restrict ssh-ed25519 AAAA… github-actions-deploy planer-prod
  ```
- Cron (Hostpoint-Panel), **zwei** Einträge:
  ```
  * * * * * cd ~/dev/apps/mollo-planer/backend && php artisan schedule:run >> /dev/null 2>&1
  * * * * * cd ~/ch/apps/mollo-planer/backend && php artisan schedule:run >> /dev/null 2>&1
  ```

### GitHub: Umgebungen

*Settings → Environments:* **`test`** (keine Reviewer) und **`production`** (Reviewer: Oliver). In **jeder** Umgebung (nicht auf Repository-Ebene): Secret `DEPLOY_SSH_KEY` (der Schlüssel der Umgebung) und die Variablen `API_BASE` (`https://api.planer.mollo.dev` bzw. `.ch`) und `FRONTEND_URL` (`https://planer.mollo.dev` bzw. `.ch`). Auf Repository-Ebene bleiben `DEPLOY_HOST`, `DEPLOY_USER` und `DEPLOY_KNOWN_HOSTS` (gleicher Server). Fehlen `API_BASE` oder `FRONTEND_URL` in der Umgebung, bricht der Lauf vor dem Build ab. Die Umgebung `production` aus Abschnitt 7 («keine Variablen oder Secrets in dieser Umgebung») gilt nicht mehr.

| Push auf | GitHub-Umgebung | Server | Freigabe |
|---|---|---|---|
| `dev` | `test` | `~/dev/apps/mollo-planer`, Branch `dev` | nein |
| `main` | `production` | `~/ch/apps/mollo-planer`, Branch `main` | ja, Oliver |
| anderer Branch | kein Deploy | | |

Beim manuellen Start (*Run workflow*) wählt man die Umgebung; sie muss zum Branch passen (`test` nur von `dev`, `production` nur von `main`), sonst bricht der Lauf ab: Der Server setzt sich auf **seinen** Branch, ein anderes Frontend würde zu fremdem Code passen.

### Docroot der API (`index.php`)

Der Docroot der API (`~/<dev|ch>/www/api.…`) ist ein von Hostpoint angelegter Ordner mit einer eigenen `index.php`, deren Pfade fest auf `<App-Ordner>/backend` zeigen (siehe Abschnitt 8, Punkt 5). **Vorschlag:** Der Deploy pflegt ihn **nicht** (er liegt ausserhalb des App-Ordners und gehört Hostpoint; ein Skript, das dort schreibt, könnte bei einem Pfadfehler die andere Umgebung treffen). Er wird beim Umzug und bei der Ersteinrichtung **einmal von Hand** angepasst und nur dann wieder, wenn sich `backend/public/index.php` ändert (selten). Entscheidet Oliver anders, ergänzt Bob das Skript um das Kopieren aus `backend/public/` mit Ersetzen der Pfade.

### Umzug TEST (Schrittliste für Oliver)

Voraussetzung: PR «Zwei Umgebungen» ist gemergt (noch **ohne** Deploy-Freigabe) und PROD ist vorbereitet (nächster Abschnitt, Punkt 1). Rückweg bei Problemen: Wartungsmodus, Ordner zurückverschieben, Docroots im Panel zurückstellen.

```bash
# 1. Wartungsmodus an (alter Pfad)
cd ~/apps/mollo-planer/backend && php artisan down --retry=60

# 2. Ordner verschieben (der Git-Checkout samt backend/.env zieht mit um)
mkdir -p ~/dev/apps ~/dev/backups ~/dev/www
mv ~/apps/mollo-planer ~/dev/apps/mollo-planer
mv ~/backups/planer ~/dev/backups/planer
# Docroots: im Hostpoint-Panel auf ~/dev/www/planer.mollo.dev und ~/dev/www/api.planer.mollo.dev umstellen
# (oder die Ordner mit mv verschieben und das Panel darauf zeigen lassen), dann Inhalt prüfen:
ls ~/dev/www/planer.mollo.dev ~/dev/www/api.planer.mollo.dev

# 3. index.php der API: die festen Pfade auf ~/dev/apps/mollo-planer/backend umstellen
nano ~/dev/www/api.planer.mollo.dev/index.php

# 4. backend/.env ergänzen (Werte aus der Tabelle oben)
nano ~/dev/apps/mollo-planer/backend/.env
#   APP_ENV=staging, APP_NAME="Mollo Planer (Test)", DEPLOY_BRANCH=dev,
#   DEPLOY_WEB_ROOT=~/dev/www/planer.mollo.dev, DEPLOY_BACKUP_DIR=~/dev/backups/planer

# 5. Branch des Checkouts auf dev stellen
cd ~/dev/apps/mollo-planer && git fetch origin dev && git checkout -f -B dev origin/dev

# 6. Caches neu, Wartungsmodus aus
cd backend
php artisan config:cache
php artisan lighthouse:clear-cache && php artisan lighthouse:cache
php artisan up
```

Danach: Cron-Eintrag im Panel auf `~/dev/apps/mollo-planer/backend` ändern, in `authorized_keys` den Pfad im `command=` auf `/home/<benutzer>/dev/apps/mollo-planer/deploy/deploy.sh` ändern (vorher sichern), im Browser `https://planer.mollo.dev` öffnen (Band «Testumgebung», Anmeldung, ein Projekt). **Wichtig:** Die alte `deploy.sh` kennt `DEPLOY_*` nicht; der Checkout in Schritt 5 bringt die neue mit, der erste Lauf aus GitHub nimmt also schon die neue.

### Ersteinrichtung PROD (Schrittliste für Oliver)

1. **Hostpoint-Panel:** Domains `planer.mollo.ch` (Docroot `~/ch/www/planer.mollo.ch`) und `api.planer.mollo.ch` (Docroot `~/ch/www/api.planer.mollo.ch`) mit SSL anlegen; eine **neue Datenbank mit eigenem Benutzer**; PHP 8.5 für beide Domains; `memory_limit` 256M und `max_execution_time` 60 (Abschnitt 12).
2. **Code holen:**
   ```bash
   mkdir -p ~/ch/apps/mollo-planer ~/ch/backups/planer && cd ~/ch/apps/mollo-planer
   git init -q && git remote add origin https://github.com/<owner>/<repo>.git
   git fetch -q origin main && git checkout -q -f -B main origin/main
   chmod 700 ~/ch/backups/planer
   ```
3. **`backend/.env`** neu anlegen (`chmod 600`): eigener `APP_KEY` (`php artisan key:generate --show`), eigene Datenbank, `APP_ENV=production`, `APP_NAME="Mollo Planer"`, `APP_URL=https://api.planer.mollo.ch`, `FRONTEND_URL=https://planer.mollo.ch`, `SANCTUM_STATEFUL_DOMAINS=planer.mollo.ch`, `SESSION_DOMAIN=.planer.mollo.ch`, `PASSKEYS_RELYING_PARTY_ID=planer.mollo.ch`, Mail, `DEPLOY_BRANCH=main`, `DEPLOY_WEB_ROOT=~/ch/www/planer.mollo.ch`, `DEPLOY_BACKUP_DIR=~/ch/backups/planer` (Rest wie Abschnitt 5).
4. **Installieren:**
   ```bash
   cd ~/ch/apps/mollo-planer/backend
   php /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction
   php artisan migrate --force
   php artisan planer:sync-templates
   php artisan config:cache && php artisan route:cache && php artisan event:cache
   php artisan lighthouse:cache
   php artisan app:create-admin "<Name>" "<E-Mail>"
   ```
5. **Docroot der API:** `index.php`, `.htaccess` (Hostpoint-Block behalten), `robots.txt`, `favicon.ico` wie in Abschnitt 8, Punkt 5, mit den Pfaden auf `~/ch/apps/mollo-planer/backend`.
6. **Cron** (Eintrag oben), **zweiter Deploy-Schlüssel** (`ssh-keygen -t ed25519 -N '' -C 'github-actions-deploy planer-prod' -f ~/.ssh/planer-prod-deploy`, öffentlicher Teil mit `command=` auf das PROD-Skript in `authorized_keys`).
7. **GitHub:** Umgebungen `test` und `production` wie oben anlegen.
8. **Erste Deploys:** Push auf `dev` (TEST, läuft sofort), danach Merge `dev` → `main` (PROD, Freigabe). Anschliessend die Nachkontrolle mit Stuart (`docs/onboarding-leitfaden.md` §6.4) für **beide** Umgebungen.

Die Rauchtests am Ende von `deploy.sh` (GraphQL, Passkey-RP-ID, `APP_NAME` auf TEST) laufen in beiden Umgebungen mit ihren eigenen Werten.
