#!/usr/bin/env bash
# Deployment for the production server (Hostpoint).
#
# Runs only through the restricted SSH key of GitHub Actions (see
# .github/workflows/deploy.yml and ~/.ssh/authorized_keys on the server).
# The built frontend (.output/public) arrives as a tar.gz archive on stdin.
#
# Order: stage frontend -> update code -> back up DB -> maintenance on ->
# composer, migrations, caches -> publish frontend -> maintenance off.
set -euo pipefail

APP="$HOME/apps/mollo-planer"
BACKEND="$APP/backend"
WEB_ROOT="$HOME/www/planer.mollo.dev"
STAGE="$HOME/apps/.planer-stage"
BACKUP_DIR="$HOME/backups/planer"
KEEP_BACKUPS=10
PHP="/usr/local/php85/bin/php"
COMPOSER="/usr/local/bin/composer"

maintenance=0

log() {
    printf '\n==> %s\n' "$*"
}

# Reads one value from backend/.env without sourcing the file.
env_value() {
    grep -E "^$1=" "$BACKEND/.env" | tail -n 1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/'
}

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
git fetch --quiet origin main
git reset --hard --quiet origin/main
echo "Commit: $(git rev-parse --short HEAD)"

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

log "Caches erzeugen"
"$PHP" "$BACKEND/artisan" config:cache
"$PHP" "$BACKEND/artisan" route:cache
"$PHP" "$BACKEND/artisan" event:cache

log "Frontend veröffentlichen"
rsync -a --delete "$STAGE/" "$WEB_ROOT/"

log "Wartungsmodus aus"
"$PHP" "$BACKEND/artisan" up >/dev/null
maintenance=0
rm -rf "$STAGE"

log "Deployment abgeschlossen"
