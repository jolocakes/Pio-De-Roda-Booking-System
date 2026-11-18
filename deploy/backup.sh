#!/usr/bin/env bash
#
# Nightly database + .env backup for the PDR Booking System.
#
# Usage:
#   sudo bash deploy/backup.sh
#   sudo bash deploy/backup.sh /var/www/pdrsystem /var/backups/pdrsystem
#
# Install it as a cron job (2:00 AM daily):
#   sudo crontab -e
#   0 2 * * * /usr/bin/env bash /var/www/pdrsystem/deploy/backup.sh >> /var/log/pdr-backup.log 2>&1
#
# Restore:
#   gunzip -c /var/backups/pdrsystem/pdrsystem-YYYY-MM-DD_HHMM.sql.gz | mysql -u pdr -p pdrsystem

set -euo pipefail

APP_DIR="${1:-/var/www/pdrsystem}"
BACKUP_DIR="${2:-/var/backups/pdrsystem}"
KEEP_DAYS="${KEEP_DAYS:-14}"
ENV_FILE="$APP_DIR/.env"

[[ -f "$ENV_FILE" ]] || { echo "Missing $ENV_FILE" >&2; exit 1; }

get() {
  grep -E "^$1=" "$ENV_FILE" | head -n1 | cut -d'=' -f2- | sed -e 's/^"//' -e 's/"$//'
}

DB_HOST="$(get PDR_DB_HOST)"; DB_HOST="${DB_HOST:-localhost}"
DB_PORT="$(get PDR_DB_PORT)"; DB_PORT="${DB_PORT:-3306}"
DB_NAME="$(get PDR_DB_NAME)"; DB_NAME="${DB_NAME:-pdrsystem}"
DB_USER="$(get PDR_DB_USER)"; DB_USER="${DB_USER:-root}"
DB_PASS="$(get PDR_DB_PASS)"

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

STAMP="$(date +%F_%H%M)"
OUT="$BACKUP_DIR/pdrsystem-$STAMP.sql.gz"

echo "[$(date '+%F %T')] dumping $DB_NAME to $OUT"
MYSQL_PWD="$DB_PASS" mysqldump \
  -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" \
  --single-transaction --quick --default-character-set=utf8mb4 \
  "$DB_NAME" | gzip -9 > "$OUT"

[[ -s "$OUT" ]] || { echo "Dump is empty - aborting" >&2; exit 1; }

# Keep a copy of the environment (contains credentials: same 700/600 protection).
cp "$ENV_FILE" "$BACKUP_DIR/env-$STAMP.bak"
chmod 600 "$OUT" "$BACKUP_DIR/env-$STAMP.bak"

find "$BACKUP_DIR" -type f -name '*.gz' -mtime +"$KEEP_DAYS" -delete
find "$BACKUP_DIR" -type f -name 'env-*.bak' -mtime +"$KEEP_DAYS" -delete

echo "[$(date '+%F %T')] done: $(du -h "$OUT" | cut -f1)"
