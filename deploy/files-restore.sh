#!/usr/bin/env bash
# Restores the uploaded files for one environment on the server of the web host (Auftrag 64 B).
#
#   files-restore.sh <file>    <file> is an archive made by files-backup.sh (planer-files-*.tar.gz)
#
# The script replaces the WHOLE folder backend/storage/app, so it asks first: it shows the environment, the file and its date,
# and goes on only when the name of the environment (APP_ENV) is typed. There is no switch to skip the question.
# Order: check the file -> ask -> back up the current state (files-backup.sh deploy vor-restore) -> unpack beside the folder ->
# maintenance on -> swap -> maintenance off. If the safety copy fails, nothing is restored; if the swap fails, the old folder
# comes back.
set -euo pipefail

APP="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="$APP/backend"
STORAGE="$BACKEND/storage"
PHP="php"
maintenance=0
STAGING=""
REPLACED=""

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
    # A swap that stopped halfway: the old folder goes back.
    if [ -n "$REPLACED" ] && [ -d "$REPLACED" ] && [ ! -d "$STORAGE/app" ]; then
        mv "$REPLACED" "$STORAGE/app" || true
    fi
    [ -z "$STAGING" ] || rm -rf "$STAGING"
    if [ "$maintenance" = 1 ]; then
        "$PHP" "$BACKEND/artisan" up >/dev/null 2>&1 || true
    fi
    if [ "$status" -ne 0 ]; then
        echo "Zurückspielen fehlgeschlagen (Exit $status). Der Wartungsmodus wurde, falls aktiv, wieder aufgehoben. Die Sicherung des Stands von vorher liegt in DEPLOY_BACKUP_DIR/files/ (Name endet auf -vor-restore.tar.gz), falls sie schon angelegt war." >&2
    fi
}
trap on_exit EXIT

[ "$#" -eq 1 ] || fail "Aufruf: files-restore.sh <datei>  (eine Sicherung aus DEPLOY_BACKUP_DIR/files/)"
FILE="$1"
[ -f "$FILE" ] || fail "Die Datei $FILE gibt es nicht. Nichts wurde geändert."
[ -f "$BACKEND/.env" ] || fail "backend/.env fehlt in $APP. Nichts wurde geändert."

deploy_php=$(env_value DEPLOY_PHP || true)
if [ -n "$deploy_php" ]; then
    [ -x "$deploy_php" ] || fail "DEPLOY_PHP zeigt auf $deploy_php, die Datei ist nicht ausführbar. Nichts wurde geändert."
    PHP="$deploy_php"
fi

ENVIRONMENT=$(env_value APP_ENV || true)
[ -n "$ENVIRONMENT" ] || fail "APP_ENV fehlt in backend/.env. Nichts wurde geändert."

# The file must be a complete archive of the folder «app» (as files-backup.sh checks it), nothing outside of it.
# The listing is read once into a variable and checked there: a pipe from tar into "grep -q" ends the reader at the first
# hit while tar still writes, and under pipefail the SIGPIPE of tar fails the whole pipe (Auftrag 73). With a pipe like that
# a valid archive was sometimes refused, and the check for paths outside of «app» sometimes did not run at all.
listing=$(tar -tzf "$FILE" 2>/dev/null) || fail "$FILE ist kein gültiges tar.gz-Archiv. Nichts wurde geändert."
outside=$(grep -Ev '^(\./)?app(/|$)' <<<"$listing" || true)
if [ -n "$outside" ]; then
    fail "$FILE enthält Pfade ausserhalb von «app». Das ist keine Sicherung von files-backup.sh. Nichts wurde geändert."
fi
app_entries=$(grep -Ec '^(\./)?app/?$' <<<"$listing" || true)
[ "$app_entries" -gt 0 ] || fail "$FILE enthält den Ordner «app» nicht. Nichts wurde geändert."

modified=$(date -r "$FILE" '+%d.%m.%Y %H:%M' 2>/dev/null || stat -f '%Sm' -t '%d.%m.%Y %H:%M' "$FILE")
echo "Umgebung:   $ENVIRONMENT"
echo "Ordner:     $STORAGE/app"
echo "Datei:      $FILE"
echo "Datum:      $modified, $(du -h "$FILE" | cut -f1)"
echo
echo "ACHTUNG: Alle hochgeladenen Dateien von $ENVIRONMENT werden durch diese Datei ersetzt. Alles, was seither dazukam, ist danach weg"
echo "(vorher wird der jetzige Stand gesichert)."
printf 'Zur Bestätigung den Namen der Umgebung eintippen (%s): ' "$ENVIRONMENT"
answer=""
read -r answer || true
if [ "$answer" != "$ENVIRONMENT" ]; then
    fail "Die Eingabe passt nicht zu «$ENVIRONMENT». Nichts wurde geändert."
fi

echo
echo "==> Jetzigen Stand sichern"
bash "$APP/deploy/files-backup.sh" deploy vor-restore || fail "Die Sicherung des jetzigen Stands ist fehlgeschlagen. Nichts wurde zurückgespielt."

echo "==> Entpacken"
umask 077
STAGING=$(mktemp -d "$STORAGE/.restore.XXXXXX")
tar -xzf "$FILE" -C "$STAGING" || fail "Das Entpacken ist fehlgeschlagen. Nichts wurde zurückgespielt."
[ -d "$STAGING/app" ] || fail "Nach dem Entpacken fehlt der Ordner «app». Nichts wurde zurückgespielt."

echo "==> Wartungsmodus an"
"$PHP" "$BACKEND/artisan" down --retry=60 >/dev/null
maintenance=1

echo "==> Ordner austauschen"
REPLACED="$STORAGE/app.replaced.$$"
mv "$STORAGE/app" "$REPLACED"
mv "$STAGING/app" "$STORAGE/app"
rm -rf "$REPLACED"
REPLACED=""

echo "==> Wartungsmodus aus"
"$PHP" "$BACKEND/artisan" up >/dev/null
maintenance=0

echo
echo "Fertig. Die Dateien von $ENVIRONMENT stehen auf dem Stand von $modified."
