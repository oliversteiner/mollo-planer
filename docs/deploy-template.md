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
SANCTUM_STATEFUL_DOMAINS=<frontend-domain>
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

ANTHROPIC_API_KEY=             # nur, falls der KI-Assistent genutzt wird
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

**Settings → Secrets and variables → Actions → Repository secrets**

- `DEPLOY_SSH_KEY`: Inhalt von `~/.ssh/<projekt>-deploy` (`pbcopy < ~/.ssh/<projekt>-deploy`)
- `DEPLOY_KNOWN_HOSTS`: Ausgabe des Host-Schlüssel-Befehls aus Abschnitt 6
- `FONTAWESOME_NPM_AUTH_TOKEN`: nur bei Font Awesome Pro
- `WEBAWESOME_NPM_AUTH_TOKEN`: nur bei Web Awesome Pro

**Settings → Secrets and variables → Actions → Variables** (keine Secrets):

- `DEPLOY_HOST`: Hostname des Hostpoint-Servers, z. B. `<servername.hostpoint.ch>`. Muss zum Eintrag in `DEPLOY_KNOWN_HOSTS` passen.
- `DEPLOY_USER`: SSH-Benutzer, z. B. `<username>`

**Settings → Environments → New environment:** Name `production`, unter *Required reviewers* den eigenen Account eintragen.

---

## 8. Einmalige Einrichtung auf dem Server

1. Projekt holen:
   ```bash
   mkdir -p ~/apps/<projekt> && cd ~/apps/<projekt>
   git init -q && git remote add origin https://github.com/<owner>/<repo>.git
   git fetch -q origin main && git checkout -q -f -B main origin/main
   ```
2. `backend/.env` aus Abschnitt 5 anlegen, `chmod 600`.
3. Backend-Abhängigkeiten mit PHP 8.5:
   ```bash
   export PATH=/usr/local/php85/bin:$PATH
   cd ~/apps/<projekt>/backend
   php /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction
   php artisan storage:link
   ```
4. **Migration nur nach Freigabe und mit Backup:** `php artisan migrate --force`.
5. Docroot der API (`~/www/<api-domain>`): Da Hostpoint diesen Ordner selbst anlegt, wird er gefüllt statt verlinkt:
   - `index.php` kopieren und die Pfade auf `~/apps/<projekt>/backend` umstellen (`__DIR__.'/../` durch den absoluten Pfad ersetzen).
   - Die Hostpoint-Zeilen oben in der `.htaccess` (`Use php-fpm latest` und `#@__HCP_END__@#`) behalten, darunter die Laravel-Regeln aus `backend/public/.htaccess`.
   - `robots.txt`, `favicon.ico` und den Symlink `storage` kopieren.
6. Docroot des Frontends (`~/www/<frontend-domain>`): leer lassen, der Deploy befüllt ihn.

---

## 9. Skript und Workflow anpassen

Vorlage: `deploy/deploy.sh` und `.github/workflows/deploy.yml` aus dem Projekt `mollo-planer`.

- In `deploy.sh` die Variablen oben anpassen: `APP`, `BACKEND`, `WEB_ROOT`, `PHP`, `COMPOSER`.
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

---

## 12. Stolpersteine

- **DB-Host:** Nicht `localhost` verwenden. Der Socket existiert auf Hostpoint nicht.
- **PHP-Version:** `php` in der Shell ist 8.3. Für das Projekt immer den vollen Pfad `/usr/local/php85/bin/php` nutzen oder `PATH` setzen (`~/.bash_profile`).
- **Erster Lauf:** Liegt `deploy/deploy.sh` schon unverwaltet auf dem Server, vorher `git fetch origin main && git reset --hard origin/main` ausführen.
- **Hostpoint-Block in `.htaccess`:** Nicht löschen, sonst wechselt die PHP-Version des Docroots.
- **Frontend-Build:** Die API-Adresse wird beim Build eingebaut (`NUXT_PUBLIC_API_BASE`). Ändert sich die Domain, neu bauen.
- **Passwörter:** Nie im Chat oder im Repo. Nach versehentlicher Weitergabe ändern.
