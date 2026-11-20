# 28 · Zwei Umgebungen: TEST (planer.mollo.dev) und PROD (planer.mollo.ch)

Status: erledigt (Commits `fa97e60` A, `1f525b1` B, `c4aced7` C, `6943071` D, PR #20 gemergt am 2026-11-19). Teil S hat Oliver mit den Schrittlisten aus `docs/deploy-template.md` Abschnitt 15 ausgeführt: TEST (`planer.mollo.dev`) und PROD (`planer.mollo.ch`) laufen, beide Deploys grün (2026-11-20). Erkenntnisse aus dem Aufbau: `docs/deploy-template.md` Abschnitt 12. Nachkontrolle: `docs/nachkontrolle-zwei-umgebungen.md`.
Reihenfolge: nach dem Deploy von PR #19 (Auftrag 27). Vor Auftrag 17. Auftrag 16 (`feature/dokumente`) bleibt davon unberührt.
Branch: `chore/zwei-umgebungen` von `dev`. Push des Branches erlaubt, PR nach `dev` öffnen, **nicht mergen**: Der Merge hängt an Olivers Umbau auf dem Server (Teil S unten).

## Entscheide (Oliver, 2026-11-19)

| | TEST | PROD (neu) |
|---|---|---|
| Domains | `planer.mollo.dev`, `api.planer.mollo.dev` | `planer.mollo.ch`, `api.planer.mollo.ch` |
| Ordner der App | `~/dev/apps/mollo-planer` | `~/ch/apps/mollo-planer` |
| Docroots (**Abweichung, gewollt:** Hostpoint legt alle Docroots unter `~/www/<domain>` an, richtig ist `~/www/planer.mollo.dev` bzw. `~/www/planer.mollo.ch`; ursprünglicher Vorschlag) | `~/dev/www/planer.mollo.dev`, `~/dev/www/api.planer.mollo.dev` | `~/ch/www/planer.mollo.ch`, `~/ch/www/api.planer.mollo.ch` |
| Sicherungen | `~/dev/backups/planer` | `~/ch/backups/planer` |
| Branch auf dem Server | `dev` | `main` |
| Auslöser | Push auf `dev` → Deploy **ohne** Freigabe | Push auf `main` → Deploy **mit** Freigabe (Umgebung `production`) |
| Daten | heutige Daten (zieht um von `~/apps/mollo-planer`) | startet **leer**, Admin per `app:create-admin` |
| `APP_ENV` | `staging` (Vorschlag, siehe B3) | `production` |

Beide liegen auf demselben Hostpoint-Konto (nginx vor Apache, `.htaccess` gilt weiter), getrennte Datenbanken, getrennte `backend/.env`, getrennte Cron-Einträge, getrennte SSH-Schlüssel.

## A · `deploy/deploy.sh` ohne feste Pfade (hoch)

1. Das Skript ermittelt seinen App-Ordner aus dem eigenen Pfad (`APP` = Ordner über `deploy/`), nicht mehr fest `$HOME/apps/mollo-planer`. So zeigt jeder SSH-Schlüssel über `command=` auf «sein» Skript und damit auf seine Umgebung; der Client kann die Umgebung nicht wählen.
2. Alles, was je Umgebung anders ist, kommt aus `backend/.env` (wie `DEPLOY_PHP`, mit `env_value`), **vor** jeder Änderung gelesen und geprüft:
   - `DEPLOY_BRANCH` (Pflicht, `dev` oder `main`): ersetzt das feste `origin/main` bei `git fetch`/`git reset`.
   - `DEPLOY_WEB_ROOT` (Pflicht): Docroot des Frontends, ersetzt `WEB_ROOT`.
   - `DEPLOY_BACKUP_DIR` (Pflicht): ersetzt `BACKUP_DIR`.
   - Fehlt ein Pflichtwert oder zeigt `DEPLOY_WEB_ROOT` nicht auf einen vorhandenen Ordner: Abbruch «… fehlt in backend/.env. Nichts wurde geändert.»
   - **Schutz gegen Verwechslung:** `DEPLOY_WEB_ROOT` muss den Host von `FRONTEND_URL` als letzten Pfadteil haben (`…/planer.mollo.ch` bei `FRONTEND_URL=https://planer.mollo.ch`), sonst Abbruch. So kann eine falsche `.env` nie das Frontend der anderen Umgebung überschreiben.
3. Ausgabe am Anfang: Umgebung (`APP_ENV`), Branch, App-Ordner, Docroot, damit das Log jedes Laufs eindeutig ist.
4. Rauchtest und RP-ID-Prüfung bleiben (sie lesen schon `APP_URL` und `FRONTEND_URL`).
5. Der Docroot der API (`…/www/api.…/index.php`) zeigt mit festen Pfaden auf `backend/`. Bob prüft, wie er heute aussieht (`docs/deploy-template.md` §5), und schlägt vor, ob der Deploy ihn mitpflegt oder ob er einmalig von Hand angepasst wird. Kein Rätselraten: Vorschlag in den PR-Text, Oliver entscheidet.

## B · `.github/workflows/deploy.yml` mit zwei Umgebungen (hoch)

1. Auslöser: `push` auf `dev` und `main` (und `workflow_dispatch` mit Auswahl der Umgebung). Zuordnung: `dev` → GitHub-Umgebung `test`, `main` → `production`. Sonst kein Deploy.
2. Je Umgebung aus der GitHub-Umgebung (nicht aus dem Repo): Secret `DEPLOY_SSH_KEY`, Variablen `API_BASE` und `FRONTEND_URL` (für den Build und den Link der Umgebung). `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_KNOWN_HOSTS` bleiben gemeinsam (gleicher Server). Die feste Zeile `API_BASE: https://api.planer.mollo.dev` entfällt.
3. `concurrency` je Umgebung (`deploy-test`, `deploy-production`), nicht abbrechen.
4. Die Tests laufen vor beiden Deploys wie heute. Umgebung `test` **ohne** Reviewer (läuft sofort), `production` **mit** Oliver als Reviewer (stellt Oliver auf GitHub ein; Bob nennt es im PR).
5. Ein Frontend-Build je Lauf mit dem `API_BASE` der Umgebung. Prüfen, ob sonst etwas mit der Domain in den Build einfliesst (`frontend/.env.example`, Claude-Connector-Anleitung), und es über die Variable lösen.

## C · Kennzeichnung der Test-Umgebung (mittel)

1. **`APP_ENV=staging` für TEST:** Bob prüft jede Stelle, die `isProduction()`/`app()->environment('production')` abfragt (u. a. `AppSettings::twoFactorSwitchable`, Larastan-Hinweise, Seeder, Schema-Cache von Lighthouse, `Model::preventLazyLoading`), und listet im PR, was sich auf TEST dadurch ändert. Was die Sicherheit lockert (z. B. 2FA abschaltbar), bleibt auf TEST **trotzdem** wie in PROD, ausser Oliver entscheidet anders. Passt `staging` nicht sauber, bleibt TEST auf `production` und nur Punkt 2 gilt.
2. Ein sichtbarer Hinweis «Testumgebung» in der App, wenn das Backend nicht `production` ist (z. B. kleines Band im Kopf, Wert aus `appSettings`), und `APP_NAME` mit «(Test)» in den Mails von TEST. Prozess ui-consistency (`adjusting` → `implementing` → `verifying`).

## D · Doku (hoch)

- `docs/deploy-template.md`: zwei Umgebungen, Tabelle wie oben, neue Pflichtwerte in `backend/.env` (`DEPLOY_BRANCH`, `DEPLOY_WEB_ROOT`, `DEPLOY_BACKUP_DIR`, dazu `DEPLOY_PHP`, `PASSKEYS_RELYING_PARTY_ID`), je ein `authorized_keys`-Eintrag mit `command=` auf `~/<dev|ch>/apps/mollo-planer/deploy/deploy.sh`, je ein Cron-Eintrag, GitHub-Umgebungen.
- Neues Kapitel «Ersteinrichtung PROD» und «Umzug TEST» als Schrittliste für Oliver (Teil S), mit allen Befehlen.
- `backend/.env.example`: die neuen `DEPLOY_*`-Werte mit Kommentar.

## S · Was Oliver auf dem Server und auf GitHub macht (Bob schreibt die Anleitung, führt nichts aus)

Reihenfolge, damit nichts kaputtgeht:

1. **PROD vorbereiten** (unabhängig vom Code): im Hostpoint-Panel die Domains `planer.mollo.ch` und `api.planer.mollo.ch` mit Docroot wie oben und SSL anlegen; eine neue Datenbank mit eigenem Benutzer; PHP 8.5 für beide Domains; `memory_limit` 256M, `max_execution_time` 60 (Auftrag 16).
2. **PR mergen** (erst nach Olivers Freigabe), noch **ohne** Deploy-Freigabe.
3. **TEST umziehen:** Wartungsmodus, `~/apps/mollo-planer` → `~/dev/apps/mollo-planer`, Docroots im Panel umstellen oder Ordner verschieben, `index.php` der API anpassen (A5), Cron-Eintrag und `authorized_keys` auf den neuen Pfad, `backend/.env` um `DEPLOY_*` ergänzen, `config:cache`, `lighthouse:cache`, Wartungsmodus aus, im Browser prüfen.
4. **PROD erstmals einrichten:** Repo nach `~/ch/apps/mollo-planer` klonen (Branch `main`), `backend/.env` neu (eigener `APP_KEY`, eigene DB, `APP_URL=https://api.planer.mollo.ch`, `FRONTEND_URL=https://planer.mollo.ch`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `PASSKEYS_RELYING_PARTY_ID=planer.mollo.ch`, Mail, `DEPLOY_*`), `composer install`, `migrate --force`, `lighthouse:cache`, `app:create-admin`, Cron, zweiter Deploy-Schlüssel mit `command=` auf das PROD-Skript.
5. **GitHub:** Umgebung `test` (ohne Reviewer) und `production` (Reviewer Oliver) mit Secret `DEPLOY_SSH_KEY` und Variablen `API_BASE`, `FRONTEND_URL`.
6. Erster Deploy TEST (Push auf `dev`), dann erster Deploy PROD (Merge nach `main`, Freigabe). Danach Nachkontrolle mit Stuart (Liste aus `docs/onboarding-leitfaden.md` §6.4 für **beide** Umgebungen).

## Prüfung

- `bash -n deploy/deploy.sh`, `shellcheck`, falls vorhanden.
- Trockentest von `deploy.sh` in zwei Wegwerf-Ordnern (`…/dev/apps/x`, `…/ch/apps/x`) mit je einer `backend/.env`: richtige Werte, fehlender Pflichtwert, `DEPLOY_WEB_ROOT` passt nicht zu `FRONTEND_URL`. Nach den Prüfungen abbrechen (leere stdin), nie gegen echte Ordner. Ausgaben in die Abschlussmeldung.
- `actionlint` für `deploy.yml`, falls vorhanden; sonst die Zuordnung Branch → Umgebung im PR-Text als Tabelle.
- Backend- und Frontend-Tests, Lint, `composer analyse` grün.

## Abschluss

Commits je Teil (`… (task 28 A)` usw.), Push des Branches, PR nach `dev` «Zwei Umgebungen: TEST und PROD», nicht mergen. Status oben setzen, anhalten.
