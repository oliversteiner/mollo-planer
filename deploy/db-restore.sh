#!/usr/bin/env bash
# Restores a database dump for one environment on the server of the web host (Auftrag 37 C).
#
#   db-restore.sh <file>    <file> is a dump made by db-backup.sh (planer-*.sql.gz)
#
# The app folder is the folder above deploy/, everything else comes from backend/.env of that folder. The script replaces the
# WHOLE database, so it asks first: it shows the environment, the database, the file and its date, and goes on only when the
# name of the environment (APP_ENV) is typed. There is no switch to skip the question.
# Order: check the file -> ask -> back up the current state (db-backup.sh deploy vor-restore) -> maintenance on -> restore ->
# clear the caches and rebuild the Lighthouse schema -> maintenance off. If the safety backup fails, nothing is restored.
set -euo pipefail

APP="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="$APP/backend"
PHP="php"
maintenance=0
OPTIONS_FILE=""

# Reads one value from backend/.env without sourcing the file.
env_value() {
    grep -E "^$1=" "$BACKEND/.env" | tail -n 1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/'
}

fail() {
    echo "$1" >&2
    exit 1
}

on_exit() {
    local status=$?
    [ -z "$OPTIONS_FILE" ] || rm -f "$OPTIONS_FILE"
    if [ "$maintenance" = 1 ]; then
        "$PHP" "$BACKEND/artisan" up >/dev/null 2>&1 || true
    fi
    if [ "$status" -ne 0 ]; then
        echo "Zurückspielen fehlgeschlagen (Exit $status). Der Wartungsmodus wurde, falls aktiv, wieder aufgehoben. Die Sicherung des Stands von vorher liegt in DEPLOY_BACKUP_DIR/db/ (Name endet auf -vor-restore.sql.gz), falls sie schon angelegt war." >&2
    fi
}
trap on_exit EXIT

[ "$#" -eq 1 ] || fail "Aufruf: db-restore.sh <datei>  (eine Sicherung aus DEPLOY_BACKUP_DIR/db/)"
FILE="$1"
[ -f "$FILE" ] || fail "Die Datei $FILE gibt es nicht. Nichts wurde geändert."
[ -f "$BACKEND/.env" ] || fail "backend/.env fehlt in $APP. Nichts wurde geändert."

deploy_php=$(env_value DEPLOY_PHP || true)
if [ -n "$deploy_php" ]; then
    [ -x "$deploy_php" ] || fail "DEPLOY_PHP zeigt auf $deploy_php, die Datei ist nicht ausführbar. Nichts wurde geändert."
    PHP="$deploy_php"
fi

ENVIRONMENT=$(env_value APP_ENV || true)
host=$(env_value DB_HOST || true)
port=$(env_value DB_PORT || true)
name=$(env_value DB_DATABASE || true)
user=$(env_value DB_USERNAME || true)
password=$(env_value DB_PASSWORD || true)
[ -n "$ENVIRONMENT" ] || fail "APP_ENV fehlt in backend/.env. Nichts wurde geändert."
[ -n "$name" ] || fail "DB_DATABASE fehlt in backend/.env. Nichts wurde geändert."

# The file must be a complete dump: valid gzip, ending with "Dump completed" (as db-backup.sh checks it).
gzip -t "$FILE" 2>/dev/null || fail "$FILE ist kein gültiges gzip-Archiv. Nichts wurde geändert."
gzip -dc "$FILE" | tail -n 1 | grep -q 'Dump completed' || fail "$FILE ist kein vollständiger Dump (die letzte Zeile ist nicht «Dump completed»). Nichts wurde geändert."

modified=$(date -r "$FILE" '+%d.%m.%Y %H:%M' 2>/dev/null || stat -f '%Sm' -t '%d.%m.%Y %H:%M' "$FILE")
echo "Umgebung:   $ENVIRONMENT"
echo "Datenbank:  $name auf $host"
echo "Datei:      $FILE"
echo "Datum:      $modified, $(du -h "$FILE" | cut -f1)"
echo
echo "ACHTUNG: Die ganze Datenbank von $ENVIRONMENT wird durch diese Datei ersetzt. Alles, was seither dazukam, ist danach weg"
echo "(vorher wird der jetzige Stand gesichert)."
printf 'Zur Bestätigung den Namen der Umgebung eintippen (%s): ' "$ENVIRONMENT"
answer=""
read -r answer || true
if [ "$answer" != "$ENVIRONMENT" ]; then
    fail "Die Eingabe passt nicht zu «$ENVIRONMENT». Nichts wurde geändert."
fi

echo
echo "==> Jetzigen Stand sichern"
bash "$APP/deploy/db-backup.sh" deploy vor-restore || fail "Die Sicherung des jetzigen Stands ist fehlgeschlagen. Nichts wurde zurückgespielt."

echo "==> Wartungsmodus an"
"$PHP" "$BACKEND/artisan" down --retry=60 >/dev/null
maintenance=1

echo "==> Zurückspielen"
umask 077
OPTIONS_FILE=$(mktemp "${TMPDIR:-/tmp}/db-restore-options.XXXXXX")
chmod 600 "$OPTIONS_FILE"
{
    printf '[client]\nhost="%s"\nport="%s"\nuser="%s"\n' "$host" "$port" "$user"
    printf 'password="%s"\n' "$(printf '%s' "$password" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g')"
} > "$OPTIONS_FILE"
gzip -dc "$FILE" | mysql --defaults-extra-file="$OPTIONS_FILE" "$name"

echo "==> Caches leeren und GraphQL-Schema neu erzeugen"
"$PHP" "$BACKEND/artisan" optimize:clear
"$PHP" "$BACKEND/artisan" lighthouse:clear-cache
"$PHP" "$BACKEND/artisan" lighthouse:cache

echo "==> Wartungsmodus aus"
"$PHP" "$BACKEND/artisan" up >/dev/null
maintenance=0

echo
echo "Fertig. Die Datenbank von $ENVIRONMENT steht auf dem Stand von $modified."
echo "Stammt die Datei aus der Zeit vor neueren Migrationen, passt das Schema nicht zum Code: 'php artisan migrate:status' prüft es, 'php artisan migrate --force' holt es nach."
