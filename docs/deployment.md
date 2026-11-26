# Deployment (zwei Umgebungen)

Automatisches Deployment mit GitHub Actions auf einen Webhoster mit SSH. Was der Server können muss und die erste Einrichtung von Hand: `installation.md`. Arbeiten auf dem laufenden Server (Nutzer, zweiter Faktor): `betrieb.md`. Alle Werte zum Ausfüllen: `deploy-template.md`.

Die Angaben der eigenen Installation stehen nicht im Repo. Adressen, Server und Pfade unten sind Platzhalter (`planer.example.com` für PROD, `planer-test.example.com` für TEST).

Es gibt zwei Umgebungen auf demselben Konto beim Webhoster: **TEST** (`https://planer-test.example.com`, API `https://api.planer-test.example.com`, Branch `dev`, ohne Freigabe) und **PROD** (`https://planer.example.com`, API `https://api.planer.example.com`, Branch `main`, mit Freigabe). Tabelle, Pflichtwerte in `backend/.env`, Schlüssel, Cron, GitHub-Umgebungen sowie die Schrittlisten «Umzug TEST» und «Ersteinrichtung PROD» stehen in `deploy-template.md`, Abschnitt 15. Die Beschreibung unten gilt für beide; wo ein Pfad oder eine Adresse steht, ist die von PROD gemeint; TEST hat eigene Ordner und Adressen.

## Ablauf

Die Datei `.github/workflows/deploy.yml` läuft bei jedem Push auf `dev` (TEST) und `main` (PROD) oder manuell, siehe unten (andere Branches deployen nicht):

1. **Tests:** Backend (`composer test`, SQLite) und Frontend (Lint, Typecheck, Unit-Tests). Schlägt ein Test fehl, wird nichts veröffentlicht.
2. **Freigabe:** Für `main` wartet der Job `deploy` auf eine Freigabe im Environment `production`, für `dev` läuft er sofort (Environment `test`).
3. **Build:** Das Frontend wird mit der Variablen `API_BASE` der GitHub-Umgebung als `NUXT_PUBLIC_API_BASE` gebaut.
4. **Übertragung:** Das Archiv `frontend/.output/public` geht per SSH an den Server. Der Schlüssel darf nur das Skript `deploy/deploy.sh` starten, keine Shell.
5. **Server:** `deploy/deploy.sh` prüft das Archiv, setzt den Code auf `origin/<DEPLOY_BRANCH>` (aus `backend/.env`), sichert die Datenbank, schaltet den Wartungsmodus ein, führt `composer install --no-dev`, `migrate --force`, `planer:sync-templates` (die mitgelieferten Vorlagen aus `spec/templates/`) und die Caches aus, kopiert das Frontend nach `DEPLOY_WEB_ROOT` und schaltet den Wartungsmodus wieder aus.

Die `.env` auf dem Server wird nie überschrieben und nie ins Repository übernommen. `CORS_ALLOWED_ORIGINS` bleibt dort leer (nur für die Entwicklung im lokalen Netz). Mails und Webhooks brauchen den Cron-Eintrag `schedule:run` jede Minute (siehe `deploy-template.md`, Abschnitt „Cron“). **Kein `php artisan migrate:rollback` in Produktion:** einige Rückwärts-Migrationen verlieren Daten, der Rückweg ist die Sicherung vor dem Deploy plus der vorherige Release.

## Deployment auslösen

- **Automatisch:** Push oder Merge auf `main`. Im GitHub-Tab **Actions** den Lauf öffnen und unter „Review deployments“ für `production` freigeben.
- **Manuell:** Actions → `deploy` → **Run workflow** (Branch `main`).

## Einrichtung (bereits erledigt, zur Dokumentation)

Für eine Neuinstallation oder bei einem Schlüsselwechsel:

- **Deploy-Schlüssel** nur für CI: `ssh-keygen -t ed25519 -N '' -C 'github-actions-deploy planer' -f ~/.ssh/mollo-planer-deploy`. Den öffentlichen Teil auf dem Server in `~/.ssh/authorized_keys` mit Einschränkung eintragen (eine Zeile):
  ```
  command="/home/username/apps/mollo-planer/deploy/deploy.sh",restrict ssh-ed25519 AAAA… github-actions-deploy planer
  ```
- **GitHub-Variablen** unter *Settings → Secrets and variables → Actions → Variables* (keine Secrets, aber auch nicht im Repo):
  - `DEPLOY_HOST`: Hostname des Servers beim Webhoster
  - `DEPLOY_USER`: SSH-Benutzer auf dem Server
- **GitHub-Secrets** unter *Settings → Secrets and variables → Actions → Secrets*:
  - `DEPLOY_SSH_KEY`: Inhalt der privaten Datei `~/.ssh/mollo-planer-deploy`
  - `DEPLOY_KNOWN_HOSTS`: Ausgabe von `ssh-keygen -F <DEPLOY_HOST> | grep -v '^#'`, damit der Server fest geprüft wird
  - `WEBAWESOME_NPM_AUTH_TOKEN` und `PRO_ASSETS_TOKEN` (wie bei der Frontend-CI; Font Awesome braucht kein npm-Token mehr)
- **Environment `production`** unter *Settings → Environments* mit mindestens einem Required Reviewer.
- **Server:** `~/apps/mollo-planer` ist ein Git-Checkout von `origin/main`. Die Datei `backend/.env` mit den Produktionswerten liegt dort mit Rechten `600`.
- **Docroots:** `~/www/planer-test.example.com` ist das Frontend. `~/www/api.planer-test.example.com` ist ein eigener Ordner mit einer `index.php`, die auf `~/apps/mollo-planer/backend` zeigt, und der Laravel-`.htaccess` unter dem Webhoster-Block. Ändert sich `backend/public/index.php` im Repo, muss die Kopie im Docroot von Hand angepasst werden.
- **PHP:** Die Standard-`php` des Servers ist 8.5, das Skript nutzt sie. `composer.json` verlangt PHP 8.5; ist `php` älter, bricht das Skript ab, bevor es etwas ändert.

## Fehlersuche

- **Tests rot:** Im Log des Jobs `test-backend` oder `test-frontend` steht der Fehler. Es wurde nichts veröffentlicht.
- **Deploy abgebrochen:** Im Log zeigt die letzte Zeile mit `==>` den Schritt. Bricht das Skript während des Wartungsmodus ab, hebt es ihn selbst wieder auf. Falls die App trotzdem hängen bleibt, auf dem Server ausführen:
  ```bash
  cd ~/apps/mollo-planer/backend && php artisan up
  ```
- **Stand prüfen:** `ssh example.com` (persönlicher Schlüssel), dann `cd ~/apps/mollo-planer && git log --oneline -1`.

## Zurückrollen

Jedes Deploy sichert die Datenbank vorher nach `~/backups/planer/planer-<Zeit>-<Commit>.sql.gz` (die letzten 10 bleiben erhalten).

- **Code:** Auf `main` den fehlerhaften Commit mit `git revert` rückgängig machen und den Workflow neu starten. `deploy.sh` setzt immer auf `origin/main`, ein erneuter Lauf eines alten Workflows bringt also den alten Code nicht zurück.
- **Datenbank:** Nur bei einer kaputten Migration. Ein Backup einspielen überschreibt alle Daten, die seit dem Backup entstanden sind. Die Zugangsdaten stehen in `backend/.env`:
  ```bash
  gunzip -c ~/backups/planer/<Datei>.sql.gz | MYSQL_PWD='<DB_PASSWORD>' mysql -h <DB_HOST> -u <DB_USERNAME> <DB_DATABASE>
  ```

## Bekannte Punkte

- Die CI-Workflows (`backend.yml`, `deploy.yml`) testen das Backend mit PHP 8.5, passend zum Server.
- Das Skript kopiert das Frontend per `rsync --delete`. Eigene Dateien im Docroot `~/www/planer-test.example.com` gehen dabei verloren.
