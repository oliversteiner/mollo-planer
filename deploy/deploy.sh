#!/usr/bin/env bash
# Deployment for one environment on the Hostpoint server (TEST: branch dev, PROD: branch main).
#
# Runs only through the restricted SSH key of GitHub Actions (see
# .github/workflows/deploy.yml and ~/.ssh/authorized_keys on the server). Every environment has its own
# key whose command= points to ITS copy of this script, so the client cannot choose the environment: the app
# folder is the folder above deploy/, everything else comes from backend/.env of that folder.
# The built frontend (.output/public) arrives as a tar.gz archive on stdin.
#
# Order: read and check backend/.env -> stage frontend -> update code -> back up DB -> maintenance on ->
# composer, migrations, caches (incl. the Lighthouse schema) -> publish frontend -> maintenance off ->
# smoke test of the API.
set -euo pipefail

APP="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="$APP/backend"
STAGE="$(dirname "$APP")/.planer-stage"
KEEP_BACKUPS=10
PHP="php" # overridden by DEPLOY_PHP in backend/.env (full path), see below
COMPOSER="/usr/local/bin/composer"

maintenance=0

log() {
    printf '\n==> %s\n' "$*"
}

# Reads one value from backend/.env without sourcing the file.
env_value() {
    grep -E "^$1=" "$BACKEND/.env" | tail -n 1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/'
}

# A path from backend/.env: "~/" stands for the home folder.
home_path() {
    case "$1" in
        "~/"*) printf '%s\n' "$HOME/${1#"~/"}" ;;
        *) printf '%s\n' "$1" ;;
    esac
}

fail() {
    echo "$1 Nichts wurde geändert." >&2
    exit 1
}

# Everything that differs per environment is read and checked BEFORE anything changes.
[ -f "$BACKEND/.env" ] || fail "backend/.env fehlt in $APP."

DEPLOY_BRANCH=$(env_value DEPLOY_BRANCH || true)
DEPLOY_WEB_ROOT=$(home_path "$(env_value DEPLOY_WEB_ROOT || true)")
DEPLOY_BACKUP_DIR=$(home_path "$(env_value DEPLOY_BACKUP_DIR || true)")
FRONTEND_URL=$(env_value FRONTEND_URL || true)

[ -n "$DEPLOY_BRANCH" ] || fail "DEPLOY_BRANCH fehlt in backend/.env (dev oder main)."
[ -n "$DEPLOY_WEB_ROOT" ] && [ "$DEPLOY_WEB_ROOT" != "$HOME/" ] || fail "DEPLOY_WEB_ROOT fehlt in backend/.env."
[ -n "$DEPLOY_BACKUP_DIR" ] && [ "$DEPLOY_BACKUP_DIR" != "$HOME/" ] || fail "DEPLOY_BACKUP_DIR fehlt in backend/.env."
[ -n "$FRONTEND_URL" ] || fail "FRONTEND_URL fehlt in backend/.env."
[[ "$DEPLOY_BRANCH" =~ ^[A-Za-z0-9._/-]+$ ]] || fail "DEPLOY_BRANCH '$DEPLOY_BRANCH' ist kein gültiger Branch-Name."
[ -d "$DEPLOY_WEB_ROOT" ] || fail "DEPLOY_WEB_ROOT zeigt auf $DEPLOY_WEB_ROOT, den Ordner gibt es nicht."

# Protection against mixing the environments up: the docroot must be named like the host of FRONTEND_URL.
FRONTEND_HOST=$(printf '%s' "$FRONTEND_URL" | sed -E 's#^[a-z]+://##; s#[/:].*$##')
if [ "$(basename "${DEPLOY_WEB_ROOT%/}")" != "$FRONTEND_HOST" ]; then
    fail "DEPLOY_WEB_ROOT ($DEPLOY_WEB_ROOT) passt nicht zu FRONTEND_URL ($FRONTEND_URL): der letzte Pfadteil muss '$FRONTEND_HOST' sein."
fi
WEB_ROOT="${DEPLOY_WEB_ROOT%/}"
BACKUP_DIR="${DEPLOY_BACKUP_DIR%/}"

# Which PHP runs: DEPLOY_PHP in backend/.env (full path), otherwise "php".
deploy_php=$(env_value DEPLOY_PHP || true)
if [ -n "$deploy_php" ]; then
    if [ ! -x "$deploy_php" ]; then
        fail "DEPLOY_PHP zeigt auf $deploy_php, die Datei ist nicht ausführbar."
    fi
    PHP="$deploy_php"
fi

# backend/composer.json requires PHP 8.5; stop before anything changes.
if ! "$PHP" -r 'exit(PHP_VERSION_ID >= 80500 ? 0 : 1);'; then
    fail "PHP 8.5 oder neuer nötig, gefunden: $("$PHP" -r 'echo PHP_VERSION;') ($PHP). DEPLOY_PHP in backend/.env auf PHP 8.5 setzen, z. B. /usr/local/php85/bin/php."
fi

log "Umgebung: APP_ENV=$(env_value APP_ENV || true), Branch $DEPLOY_BRANCH"
echo "App-Ordner: $APP"
echo "Docroot Frontend: $WEB_ROOT"
echo "Sicherungen: $BACKUP_DIR"

on_exit() {
    local status=$?
    if [ "$maintenance" = 1 ]; then
        "$PHP" "$BACKEND/artisan" up >/dev/null 2>&1 || true
    fi
    if [ "$status" -ne 0 ]; then
        echo "Deployment fehlgeschlagen (Exit $status). Wartungsmodus wurde, falls aktiv, wieder aufgehoben." >&2
    fi
}
trap on_exit EXIT

log "Frontend-Archiv entpacken"
rm -rf "$STAGE"
mkdir -p "$STAGE"
tar -xzf - -C "$STAGE"
if [ ! -f "$STAGE/index.html" ]; then
    echo "index.html fehlt im Archiv, Abbruch ohne Änderungen." >&2
    exit 1
fi

log "Code aktualisieren"
cd "$APP"
git fetch --quiet origin "$DEPLOY_BRANCH"
git reset --hard --quiet "origin/$DEPLOY_BRANCH"
echo "Commit: $(git rev-parse --short HEAD)"
# Werkzeuge und Testdaten für planer-v2 gehören nicht auf den Server (dort gibt es kein Node).
# spec/planer-v2.schema.json bleibt: die App liest es.
rm -rf "$APP/tools" "$APP/spec/fixtures"

log "Datenbank sichern"
DB_HOST=$(env_value DB_HOST)
DB_PORT=$(env_value DB_PORT)
DB_DATABASE=$(env_value DB_DATABASE)
DB_USERNAME=$(env_value DB_USERNAME)
DB_PASSWORD=$(env_value DB_PASSWORD)
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
BACKUP="$BACKUP_DIR/planer-$(date +%Y%m%d-%H%M%S)-$(git rev-parse --short HEAD).sql.gz"
MYSQL_PWD="$DB_PASSWORD" mysqldump --single-transaction --no-tablespaces \
    -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$DB_DATABASE" | gzip > "$BACKUP"
chmod 600 "$BACKUP"
echo "Backup: $BACKUP"
ls -1t "$BACKUP_DIR"/planer-*.sql.gz | tail -n +$((KEEP_BACKUPS + 1)) | xargs -r rm -f --

log "Wartungsmodus an"
"$PHP" "$BACKEND/artisan" down --retry=60 >/dev/null
maintenance=1

log "Abhängigkeiten installieren"
cd "$BACKEND"
"$PHP" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --no-progress --no-ansi

log "Migrationen ausführen"
"$PHP" "$BACKEND/artisan" migrate --force

# Die mitgelieferten Vorlagen (spec/templates) in die Datenbank schreiben; ändert nichts, wenn die Dateien gleich blieben.
log "Vorlagen abgleichen"
"$PHP" "$BACKEND/artisan" planer:sync-templates

log "Caches erzeugen"
"$PHP" "$BACKEND/artisan" config:cache
"$PHP" "$BACKEND/artisan" route:cache
"$PHP" "$BACKEND/artisan" event:cache

# The compiled GraphQL schema lives in bootstrap/cache (not in Git, git reset leaves it alone). Outside APP_ENV=local
# Lighthouse reads only that file, so an old one keeps serving an old schema (and resolver classes that no longer
# exist). Rebuild it on every deploy. The query cache (query_cache, mode "store") holds parsed query documents keyed
# by the hash of the query text; a parsed query does not depend on the schema (it is validated on every request), so
# it needs no clearing here.
log "GraphQL-Schema neu erzeugen"
"$PHP" "$BACKEND/artisan" lighthouse:clear-cache
"$PHP" "$BACKEND/artisan" lighthouse:cache

log "Frontend veröffentlichen"
rsync -a --delete "$STAGE/" "$WEB_ROOT/"

log "Wartungsmodus aus"
"$PHP" "$BACKEND/artisan" up >/dev/null
maintenance=0
rm -rf "$STAGE"

# The code is live now, so a failing smoke test turns the run red but does not roll anything back.
# A request without Origin/Referer is not treated as a stateful SPA request by Sanctum, so no CSRF cookie is needed.
# Expected without a session: the answer says "Unauthenticated." (the schema loads, the resolver class exists).
log "Rauchtest GraphQL"
API_URL=$(env_value APP_URL)
SMOKE=$(curl -sS -m 30 -X POST -H 'Content-Type: application/json' -H 'Accept: application/json' \
    -d '{"query":"{ viewerRights }"}' "${API_URL%/}/api" 2>&1 || true)
if [[ "$SMOKE" != *"Unauthenticated."* ]]; then
    echo "Rauchtest GraphQL fehlgeschlagen: ${SMOKE:0:300}" >&2
    echo "Der neue Code ist bereits live (kein automatisches Zurückrollen). Log prüfen: backend/storage/logs." >&2
    exit 1
fi
echo "Rauchtest GraphQL: ok"

# Passkeys belong to the relying party ID: it must be the host of FRONTEND_URL (no single sign-on between apps).
# Only a warning, no abort.
RP_ID=$("$PHP" "$BACKEND/artisan" config:show fortify.passkeys.relying_party_id 2>/dev/null | awk 'NF {print $NF}' | tail -n 1 || true)
if [ "$RP_ID" != "$FRONTEND_HOST" ]; then
    echo "WARNUNG: PASSKEYS_RELYING_PARTY_ID ist '$RP_ID', der Host von FRONTEND_URL ist '$FRONTEND_HOST'. Passkeys würden nicht passen (siehe docs/deploy-template.md)." >&2
fi

# Standard roles and functions (config/permissions.php) reach an existing database only with permissions:sync-defaults
# (e.g. the project role Agent, task 31). Not run here on purpose: it resets the standard assignments. Only a warning.
if ! "$PHP" "$BACKEND/artisan" permissions:sync-defaults --check >/dev/null 2>&1; then
    echo "WARNUNG: Standardrollen oder -funktionen fehlen in der Datenbank (z. B. die Projektrolle Agent). Auf dem Server 'php artisan permissions:sync-defaults --check' zeigt sie, 'php artisan permissions:sync-defaults' holt sie (setzt die Standardbelegung zurück)." >&2
fi

# The test environment says so in its mails: APP_NAME carries "(Test)". Only a warning.
if [ "$(env_value APP_ENV || true)" = "staging" ] && [[ "$(env_value APP_NAME || true)" != *Test* ]]; then
    echo "WARNUNG: APP_ENV ist staging, aber APP_NAME enthält nicht «Test» (z. B. \"Mollo Planer (Test)\"): Mails der Testumgebung wären nicht als Test erkennbar." >&2
fi

log "Deployment abgeschlossen"
