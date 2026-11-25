#!/usr/bin/env bash
# Backup of the uploaded files (backend/storage/app: documents, letterheads, issued invoice PDFs) for one environment on the
# server of the web host (Auftrag 64 A). Issued invoices are business records and are never made again, so they are backed up
# like the database.
#
#   files-backup.sh daily            for the daily cron job, called by db-backup.sh daily (keeps 14 daily ones, plus the first
#                                    one of each month for 6 months). Without a change since the last daily archive nothing is
#                                    packed (the files change rarely): the message says so and the exit is 0.
#   files-backup.sh deploy <label>   a safety copy before something replaces the files (files-restore.sh uses "vor-restore"),
#                                    keeps the last 20
#
# Only backend/storage/app is packed: storage/framework, storage/logs, the caches and the OAuth keys (storage/oauth-*.key, kept
# apart in the password manager) lie outside of it. The archive is written to a ".part" file, checked with tar -tzf and only
# then renamed; if anything fails, the half archive is removed and the script stops with a non-zero exit.
#
# Layout in DEPLOY_BACKUP_DIR/files/:  planer-files-<date>-<time>-<label>.tar.gz   (safety copies, newest 20)
#                                      daily/planer-files-<date>-<time>.tar.gz     (cron, newest 14)
#                                      monthly/planer-files-<year><month>.tar.gz   (first daily one of a month, newest 6)
set -euo pipefail

APP="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="$APP/backend"
SOURCE="$BACKEND/storage/app"
KEEP_DEPLOY=20
KEEP_DAILY=14
KEEP_MONTHLY=6

usage() {
    echo "Aufruf: files-backup.sh daily | files-backup.sh deploy <name>" >&2
    exit 64
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

# Deletes everything but the newest $2 files matching the glob $1.
keep_newest() {
    # shellcheck disable=SC2012
    ls -1t $1 2>/dev/null | tail -n +$(($2 + 1)) | xargs -r rm -f -- || true
}

KIND="${1:-}"
LABEL="${2:-}"
case "$KIND" in
    deploy) [[ "$LABEL" =~ ^[A-Za-z0-9._-]+$ ]] || usage ;;
    daily) ;;
    *) usage ;;
esac

[ -f "$BACKEND/.env" ] || { echo "backend/.env fehlt in $APP." >&2; exit 1; }
BACKUP_DIR=$(home_path "$(env_value DEPLOY_BACKUP_DIR || true)")
BACKUP_DIR="${BACKUP_DIR%/}"
if [ -z "$BACKUP_DIR" ] || [ "$BACKUP_DIR" = "$HOME" ]; then
    echo "DEPLOY_BACKUP_DIR fehlt in backend/.env." >&2
    exit 1
fi
[ -d "$SOURCE" ] || { echo "Der Ordner $SOURCE fehlt, es gibt nichts zu sichern." >&2; exit 1; }

umask 077
files_dir="$BACKUP_DIR/files"
case "$KIND" in
    deploy) dir="$files_dir"; file="$dir/planer-files-$(date +%Y%m%d-%H%M%S)-$LABEL.tar.gz" ;;
    daily) dir="$files_dir/daily"; file="$dir/planer-files-$(date +%Y%m%d-%H%M%S).tar.gz" ;;
esac
mkdir -p "$dir"
chmod 700 "$BACKUP_DIR" "$files_dir" "$dir"

# Adds the first archive of a month to monthly/ and trims it.
keep_monthly() {
    local monthly="$files_dir/monthly" month_file
    mkdir -p "$monthly"
    chmod 700 "$monthly"
    month_file="$monthly/planer-files-$(date +%Y%m).tar.gz"
    if [ ! -f "$month_file" ]; then
        cp "$1" "$month_file"
        echo "Monatssicherung: $month_file"
    fi
    keep_newest "$monthly/planer-files-*.tar.gz" "$KEEP_MONTHLY"
}

if [ "$KIND" = daily ]; then
    # shellcheck disable=SC2012
    latest=$(ls -1t "$dir"/planer-files-*.tar.gz 2>/dev/null | head -n 1 || true)
    if [ -n "$latest" ] && [ -z "$(find "$SOURCE" -type f -newer "$latest" -print -quit)" ]; then
        echo "Keine Änderung an den Dateien seit $latest, nichts neu gesichert."
        keep_monthly "$latest"
        exit 0
    fi
fi

PART="$file.part"
MARKER=$(mktemp "$dir/.start.XXXXXX")
cleanup() {
    rm -f "$PART" "$MARKER"
}
trap cleanup EXIT

if ! tar -czf "$PART" -C "$BACKEND/storage" app \
    || ! tar -tzf "$PART" > /dev/null; then
    echo "Die Sicherung der Dateien ist fehlgeschlagen." >&2
    exit 1
fi
mv "$PART" "$file"
# The archive carries the time the packing began: a file changed while it was packed is newer and comes with the next one.
touch -r "$MARKER" "$file"
echo "Dateisicherung: $file"

if [ "$KIND" = deploy ]; then
    keep_newest "$dir/planer-files-*.tar.gz" "$KEEP_DEPLOY"
    exit 0
fi

keep_newest "$dir/planer-files-*.tar.gz" "$KEEP_DAILY"
keep_monthly "$file"
