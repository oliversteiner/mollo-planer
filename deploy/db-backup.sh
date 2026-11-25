#!/usr/bin/env bash
# Database backup for one environment on the server of the web host (TEST holds the real planning data, PROD the same rule).
#
#   db-backup.sh deploy <commit>   before a migration, called by deploy.sh (keeps the last 20)
#   db-backup.sh daily             for the daily cron job (keeps 14 daily ones, plus the first one of each month for 6 months);
#                                  afterwards it calls files-backup.sh daily for the uploaded files (Auftrag 64), so the cron
#                                  entry stays the same. If the files fail, the dump stays and the exit is non-zero.
#
# The app folder is the folder above deploy/, everything else comes from backend/.env of that folder (DB_*, DEPLOY_BACKUP_DIR).
# The credentials never appear on a command line or in the output: they go into a temporary option file (rights 600, in the
# closed backup folder) that is deleted right after. The dump is written to a ".part" file and only gets its final name when it
# is a complete gzip archive ending with "Dump completed"; if anything fails, the half dump is removed and the script stops
# with a non-zero exit (deploy.sh then migrates nothing).
#
# Layout in DEPLOY_BACKUP_DIR/db/:  planer-<date>-<time>-<commit>.sql.gz   (before deployments)
#                                   daily/planer-<date>-<time>.sql.gz      (cron, newest 14)
#                                   monthly/planer-<year><month>.sql.gz    (first daily one of a month, newest 6)
set -euo pipefail

APP="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="$APP/backend"
KEEP_DEPLOY=20
KEEP_DAILY=14
KEEP_MONTHLY=6

usage() {
    echo "Aufruf: db-backup.sh deploy <commit> | db-backup.sh daily" >&2
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
COMMIT="${2:-}"
case "$KIND" in
    deploy) [[ "$COMMIT" =~ ^[A-Za-z0-9._-]+$ ]] || usage ;;
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

host=$(env_value DB_HOST || true)
port=$(env_value DB_PORT || true)
name=$(env_value DB_DATABASE || true)
user=$(env_value DB_USERNAME || true)
password=$(env_value DB_PASSWORD || true)

umask 077
db_dir="$BACKUP_DIR/db"
case "$KIND" in
    deploy) dir="$db_dir"; file="$dir/planer-$(date +%Y%m%d-%H%M%S)-$COMMIT.sql.gz" ;;
    daily) dir="$db_dir/daily"; file="$dir/planer-$(date +%Y%m%d-%H%M%S).sql.gz" ;;
esac
mkdir -p "$dir"
chmod 700 "$BACKUP_DIR" "$db_dir" "$dir"

PART="$file.part"
OPTIONS_FILE=$(mktemp "$dir/.options.XXXXXX")
cleanup() {
    rm -f "$OPTIONS_FILE" "$PART"
}
trap cleanup EXIT
chmod 600 "$OPTIONS_FILE"
{
    printf '[client]\nhost="%s"\nport="%s"\nuser="%s"\n' "$host" "$port" "$user"
    printf 'password="%s"\n' "$(printf '%s' "$password" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g')"
} > "$OPTIONS_FILE"

if ! mysqldump --defaults-extra-file="$OPTIONS_FILE" --single-transaction --no-tablespaces "$name" | gzip > "$PART" \
    || ! gzip -t "$PART" \
    || ! gzip -dc "$PART" | tail -n 1 | grep -q 'Dump completed'; then
    if [ "$KIND" = deploy ]; then
        echo "Die Datenbanksicherung ist fehlgeschlagen, es wird nichts migriert. Code und Datenbank sind unverändert; Wartungsmodus war nicht an." >&2
    else
        echo "Die tägliche Datenbanksicherung ist fehlgeschlagen." >&2
    fi
    exit 1
fi
mv "$PART" "$file"
echo "Backup: $file"

if [ "$KIND" = deploy ]; then
    keep_newest "$dir/planer-*.sql.gz" "$KEEP_DEPLOY"
    exit 0
fi

keep_newest "$dir/planer-*.sql.gz" "$KEEP_DAILY"
# The first daily backup of a month is kept for six months (it does not matter on which day the cron job first runs).
monthly="$db_dir/monthly"
mkdir -p "$monthly"
chmod 700 "$monthly"
month_file="$monthly/planer-$(date +%Y%m).sql.gz"
if [ ! -f "$month_file" ]; then
    cp "$file" "$month_file"
    echo "Monatssicherung: $month_file"
fi
keep_newest "$monthly/planer-*.sql.gz" "$KEEP_MONTHLY"

# The uploaded files (invoice PDFs, documents) go along with the daily dump.
bash "$APP/deploy/files-backup.sh" daily || {
    echo "Die tägliche Sicherung der Dateien ist fehlgeschlagen (die Datenbanksicherung ist fertig)." >&2
    exit 1
}
