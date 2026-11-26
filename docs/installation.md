# Installation

Wie der Mollo Planer auf einen Server kommt: was der Server können muss, die Schritte beim ersten Mal, der erste Admin und der KI-Assistent. Für die Arbeit auf dem eigenen Rechner: `lokal.md`. Automatisches Deployment mit zwei Umgebungen: `deployment.md`. Arbeiten auf dem laufenden Server (Nutzer, zweiter Faktor): `betrieb.md`.

Die Angaben der eigenen Installation stehen nicht im Repo. Adressen wie `planer.example.com`, «Webhoster» und Pfade wie `~/apps/mollo-planer` sind Platzhalter.

**Wo ein Befehl läuft:** Jeder Befehlsblock beginnt mit `# LOKAL` (auf deinem Rechner) oder `# AUF DEM SERVER` (per SSH beim Webhoster angemeldet).

## Anforderungen

Der Planer läuft auf einfachem Webhosting: kein Node, kein Docker, keine Root-Rechte und kein Prozess, der dauernd läuft.

| Teil | Anforderung | Woher |
|---|---|---|
| PHP | 8.5 | `backend/composer.json` |
| PHP-Erweiterungen | `bcmath`, `ctype`, `dom`, `fileinfo`, `filter`, `gd`, `hash`, `iconv`, `json`, `libxml`, `mbstring`, `openssl`, `pcre`, `pdo_mysql`, `session`, `sodium`, `tokenizer`, `zlib` (bei den meisten Webhostern eingeschaltet) | `composer check-platform-reqs --no-dev` |
| Webserver | Apache mit `mod_rewrite` (Pflicht). `mod_headers`, `mod_deflate` und `mod_filter` sind freiwillig: ohne sie fehlen nur Cache-Header und Kompression | `backend/public/.htaccess`, `frontend/public/.htaccess` |
| Datenbank | MariaDB (im Betrieb 10.11, in der CI 11.5). MySQL ist nicht geprüft | `deploy-template.md`, `.github/workflows/backend.yml` |
| Cron | ein Eintrag, jede Minute: `php artisan schedule:run`. Er arbeitet auch die Warteschlange ab (Mails, Webhooks) | `backend/routes/console.php` |
| HTTPS | für beide Adressen (Frontend und API). Passkeys und sichere Cookies verlangen es | |
| Zwei Adressen | eine für das Frontend (`planer.example.com`), eine für die API (`api.planer.example.com`) | |
| SSH, Git, Composer | auf dem Server, für Einrichtung und Updates (siehe unten) | `deploy/deploy.sh` |

**Was auf den Server kommt:** Das Frontend ist eine reine Browser-App (`ssr: false`). Es wird vorher gebaut, lokal oder in GitHub Actions, und als statische Dateien hochgeladen. Das Backend ist eine Laravel-App; ihre Pakete (`vendor/`) installiert Composer auf dem Server.

**Ohne Shell geht es heute nicht.** Die Tabellen (`php artisan migrate`), der erste Admin (`php artisan app:create-admin`) und jedes Update laufen über die Kommandozeile. Einen Installer im Browser gibt es nicht. Der Webhoster muss also SSH anbieten.

**Lizenzen für das Frontend:** Der Build braucht Web Awesome Pro (privates Paket-Register, eigener Token). Font Awesome Pro ist freiwillig; ohne es nimmt der Build Font Awesome Free. Details: `../frontend/README.md`.

## Erste Einrichtung mit SSH

Kurzfassung. Die ausführliche Fassung mit allen Werten der `.env`, Docroots, Cron und GitHub steht in `deploy-template.md`; wer automatisch deployen will, folgt `deployment.md`.

1. **Datenbank** im Panel des Webhosters anlegen (Name, Benutzer, Passwort, Host notieren).
2. **Code holen:**
   ```bash
   # AUF DEM SERVER
   git clone https://github.com/<owner>/mollo-planer.git ~/apps/mollo-planer
   cd ~/apps/mollo-planer/backend
   composer install --no-dev --optimize-autoloader
   cp .env.example .env && chmod 600 .env
   php artisan key:generate
   php artisan passport:keys      # Schlüssel des Anmeldedienstes (OAuth), einmal
   php artisan storage:link
   ```
3. **`.env` ausfüllen:** `APP_ENV=production`, `APP_URL` (Adresse der API), `FRONTEND_URL`, `DB_CONNECTION=mariadb` und die Zugangsdaten, `MAIL_*`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`. Pflichtwerte und Erklärungen: `deploy-template.md`, Abschnitt 5 und 15.
4. **Tabellen anlegen:**
   ```bash
   # AUF DEM SERVER, im Ordner backend/
   php artisan migrate --force
   php artisan permissions:sync-defaults --add-missing
   php artisan planer:sync-templates
   php artisan config:cache && php artisan route:cache && php artisan event:cache
   php artisan lighthouse:cache
   ```
   Dieselben Schritte macht bei jedem Update `deploy/deploy.sh` (`deployment.md`).
5. **API erreichbar machen:** Der Docroot der API-Adresse zeigt auf `backend/public/` oder enthält eine `index.php`, die dorthin zeigt (`deploy-template.md`, «Docroot der API»).
6. **Frontend bauen und hochladen:**
   ```bash
   # LOKAL, im Ordner frontend/ (Node 24)
   npm ci
   NUXT_PUBLIC_API_BASE=https://api.planer.example.com npm run build
   ```
   Den Inhalt von `frontend/.output/public/` (samt `.htaccess`) in den Docroot der Frontend-Adresse laden.
7. **Cron** im Panel des Webhosters eintragen, jede Minute: `cd ~/apps/mollo-planer/backend && php artisan schedule:run`.
8. **Ersten Admin anlegen** (nächster Abschnitt).

## Ersten Admin anlegen

Auf einer neuen Installation gibt es noch keinen Admin. Den ersten legt der Artisan-Befehl `app:create-admin` an. Ein Seeder dafür existiert bewusst nicht, damit nie ein Konto mit bekanntem Passwort entsteht. Im Ordner `backend/` ausführen:

```bash
# AUF DEM SERVER (oder LOKAL), im Ordner backend/
php artisan app:create-admin "<Name>" <E-Mail> --password
```

Der Befehl fragt das Passwort zweimal im Terminal ab. Es wird nie als Parameter übergeben und nirgends gespeichert.

Ohne `--password` verschickt der Befehl stattdessen eine Einladung per Mail mit Link zum Setzen des Passworts. Dafür muss der Mail-Versand in der `.env` eingerichtet sein. Mit `MAIL_MAILER=log` landet die Mail nur im Log, deshalb lokal `--password` verwenden.

## KI-Assistent einrichten

Admins finden in der Seitenleiste **KI-Assistent**: links ein Chat (auch mit Spracheingabe über die Mikrofon-Taste in Chrome und Edge), rechts die Vorschau des Plans mit Name, Beschreibung und Timeline (Milestones, aufklappbare ToDos, Tasks mit Personen und Daten). Mit weiteren Nachrichten lässt sich der Entwurf ändern. Gespeichert wird erst mit **Jetzt eintragen**.

Einrichten: einen API-Schlüssel unter https://console.anthropic.com erzeugen und in `backend/.env` eintragen (nie committen), danach das Backend neu starten:

```bash
# in backend/.env (AUF DEM SERVER bzw. LOKAL)
ANTHROPIC_API_KEY=sk-ant-...
# optional, Standard claude-opus-5-5
ANTHROPIC_MODEL=claude-opus-5-5
```

Jede Chat-Nachricht ist eine kostenpflichtige Anfrage an die Claude-API. Je Person gelten Grenzen (Standard 30 Entwürfe je Stunde und 200 je Tag, einstellbar mit `ASSISTANT_LIMIT_HOUR` und `ASSISTANT_LIMIT_DAY`). `php artisan assistant:ping` schickt eine kleine echte Anfrage mit dem echten Schema und meldet OK oder den Fehler der API; vor jeder Änderung am Assistenten oder an seinem Schema laufen lassen. Bei der Spracheingabe schickt der Browser die Aufnahme zur Erkennung an seinen Anbieter (Google bzw. Microsoft).
