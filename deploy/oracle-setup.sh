#!/usr/bin/env bash
#
# PDR Booking System - one-shot bootstrap for a fresh Ubuntu 22.04/24.04
# server (Oracle Cloud Always Free, GCP e2-micro, or any small VPS).
#
# What it does:
#   1. Installs Apache 2.4 + MariaDB + PHP 8 + Certbot.
#   2. Enables the Apache modules the project's .htaccess files rely on.
#   3. Creates the `pdrsystem` database and a dedicated `pdr` user.
#   4. Creates .env from .env.example and fills in the DB credentials
#      (generates PDR_LOOKUP_SECRET as well).
#   5. Writes and enables an Apache vhost for the domain.
#   6. Imports database/schema.sql + seeds the catalog when the DB is empty,
#      or imports /root/pdrsystem-export.sql if that dump exists.
#   7. Opens TCP 80/443 in the OS firewall.
#
# Usage:
#   sudo bash deploy/oracle-setup.sh shop.example.com
#   sudo bash deploy/oracle-setup.sh shop.example.com /var/www/pdrsystem
#
# Safe to re-run: an existing .env and existing database rows are preserved.

set -euo pipefail

DOMAIN="${1:-}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${2:-$(cd "$SCRIPT_DIR/.." && pwd)}"
DB_NAME="pdrsystem"
DB_USER="pdr"
CRED_FILE="/root/pdr-credentials.txt"

log()  { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m[!] %s\033[0m\n' "$*"; }
die()  { printf '\033[1;31m[x] %s\033[0m\n' "$*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Run with sudo: sudo bash deploy/oracle-setup.sh <domain>"
[[ -n "$DOMAIN" ]] || die "Usage: sudo bash deploy/oracle-setup.sh <domain> [app_dir]"
[[ -f "$APP_DIR/index.php" ]] || die "No index.php in $APP_DIR - pass the app directory as the 2nd argument."
[[ -f "$APP_DIR/database/schema.sql" ]] || die "No database/schema.sql in $APP_DIR - wrong app directory?"

export DEBIAN_FRONTEND=noninteractive

log "Installing Apache, MariaDB, PHP and Certbot (this takes a few minutes)"
apt-get update -y
apt-get install -y --no-install-recommends \
  apache2 mariadb-server php php-cli php-mysql php-curl php-mbstring php-xml \
  php-zip libapache2-mod-php certbot python3-certbot-apache unzip rsync
systemctl enable --now apache2 mariadb

PHPV="$(php -r 'echo PHP_VERSION;' | cut -d. -f1,2)"
log "PHP $PHPV detected"

log "Enabling Apache modules used by .htaccess"
a2enmod rewrite headers deflate expires >/dev/null
if ! a2query -m "php$PHPV" >/dev/null 2>&1; then
  a2dismod mpm_event >/dev/null 2>&1 || true
  a2enmod mpm_prefork >/dev/null 2>&1 || true
  a2enmod "php$PHPV" >/dev/null 2>&1 || true
fi

# ---------------------------------------------------------------- database ---
ENV_FILE="$APP_DIR/.env"
EXISTING_PASS=""
if [[ -f "$ENV_FILE" ]]; then
  EXISTING_PASS="$(grep -E '^PDR_DB_PASS=' "$ENV_FILE" | head -n1 | cut -d'=' -f2- | tr -d '"' || true)"
fi

if [[ -n "$EXISTING_PASS" ]]; then
  DB_PASS="$EXISTING_PASS"
  log "Reusing the database password already stored in .env"
else
  DB_PASS="$(openssl rand -hex 16)"
  log "Generated a new database password"
fi

log "Creating database '$DB_NAME' and user '$DB_USER'"
mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if mysql -N -B -e "SELECT 1 FROM mysql.user WHERE user='$DB_USER' AND host='localhost';" | grep -q 1; then
  mysql -e "ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
else
  mysql -e "CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
fi
mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;"

# --------------------------------------------------------------------- env ---
if [[ ! -f "$ENV_FILE" ]]; then
  log "Creating .env from .env.example"
  cp "$APP_DIR/.env.example" "$ENV_FILE"
  LOOKUP_SECRET="$(openssl rand -hex 32)"
  sed -i \
    -e "s|^PDR_DB_HOST=.*|PDR_DB_HOST=localhost|" \
    -e "s|^PDR_DB_PORT=.*|PDR_DB_PORT=3306|" \
    -e "s|^PDR_DB_NAME=.*|PDR_DB_NAME=$DB_NAME|" \
    -e "s|^PDR_DB_USER=.*|PDR_DB_USER=$DB_USER|" \
    -e "s|^PDR_DB_PASS=.*|PDR_DB_PASS=$DB_PASS|" \
    -e "s|^PDR_LOOKUP_SECRET=.*|PDR_LOOKUP_SECRET=$LOOKUP_SECRET|" \
    "$ENV_FILE"
else
  log ".env already exists - leaving the other values untouched"
  if [[ -z "$EXISTING_PASS" ]]; then
    sed -i -e "s|^PDR_DB_PASS=.*|PDR_DB_PASS=$DB_PASS|" "$ENV_FILE"
    warn "Filled in the empty PDR_DB_PASS in .env"
  fi
fi

# ------------------------------------------------------------------ import ---
TABLE_COUNT="$(mysql -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';")"
if [[ "$TABLE_COUNT" -gt 0 ]]; then
  log "Database already has $TABLE_COUNT tables - skipping import"
else
  DUMP=""
  for candidate in /root/pdrsystem-export.sql /tmp/pdrsystem-export.sql "$APP_DIR/pdrsystem-export.sql"; do
    if [[ -f "$candidate" ]]; then DUMP="$candidate"; break; fi
  done

  if [[ -n "$DUMP" ]]; then
    if [[ "$DUMP" == "$APP_DIR/"* ]]; then
      mv "$DUMP" /root/pdrsystem-export.sql
      DUMP=/root/pdrsystem-export.sql
      warn "SQL dump was inside the web root - moved it to $DUMP"
    fi
    log "Importing $DUMP"
    mysql "$DB_NAME" < "$DUMP"
  else
    log "Importing database/schema.sql"
    mysql "$DB_NAME" < "$APP_DIR/database/schema.sql"
    log "Seeding the default service catalog"
    php "$APP_DIR/database/seed_catalog.php"
  fi
fi

# ------------------------------------------------------------------- vhost ---
log "Writing the Apache vhost for $DOMAIN"
cat > /etc/apache2/sites-available/pdrsystem.conf <<EOF
# Generated by deploy/oracle-setup.sh - re-run the script instead of editing.
<VirtualHost *:80>
    ServerName $DOMAIN
    DocumentRoot $APP_DIR

    <Directory $APP_DIR>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php index.html
    </Directory>

    # Defence in depth: the root .htaccess already denies these, but a future
    # edit must not be able to expose a dump or the .env file.
    <FilesMatch "(?i)\.(env|sql|log|sh|conf|bak|old|orig|ini|yml|yaml)\$">
        Require all denied
    </FilesMatch>

    ErrorLog \${APACHE_LOG_DIR}/pdrsystem-error.log
    CustomLog \${APACHE_LOG_DIR}/pdrsystem-access.log combined
</VirtualHost>

ServerTokens Prod
ServerSignature Off
EOF

a2ensite pdrsystem >/dev/null
a2dissite 000-default >/dev/null 2>&1 || true
apache2ctl configtest
systemctl reload apache2

# ------------------------------------------------------------------- php.ini --
log "Applying production PHP settings"
INI_DIR="/etc/php/$PHPV/apache2/conf.d"
mkdir -p "$INI_DIR"
cat > "$INI_DIR/99-pdr.ini" <<'EOF'
; Generated by deploy/oracle-setup.sh
; Flip session.cookie_secure to 1 after HTTPS works (see DEPLOY-ORACLE.md).
display_errors = Off
display_startup_errors = Off
log_errors = On
expose_php = Off
session.cookie_httponly = 1
session.cookie_samesite = "Lax"
upload_max_filesize = 8M
post_max_size = 8M
max_execution_time = 60
EOF
systemctl reload apache2

# --------------------------------------------------------------- permissions --
log "Setting file ownership and permissions"
chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} +
find "$APP_DIR" -type f -exec chmod 644 {} +
chmod 640 "$ENV_FILE"
mkdir -p "$APP_DIR/api/mail/outbox"
chmod 775 "$APP_DIR/api/mail/outbox"

# ------------------------------------------------------------------ firewall --
if command -v iptables >/dev/null 2>&1; then
  for port in 80 443; do
    iptables -C INPUT -p tcp --dport "$port" -m state --state NEW -j ACCEPT 2>/dev/null \
      || iptables -I INPUT 1 -p tcp --dport "$port" -m state --state NEW -j ACCEPT
  done
  mkdir -p /etc/iptables
  iptables-save > /etc/iptables/rules.v4
  log "Opened TCP 80/443 in the OS firewall"
  warn "You still need the same two rules in the Oracle VCN Security List."
fi

# ---------------------------------------------------------------- credentials --
umask 077
{
  echo "PDR deployment credentials - $(date -u '+%Y-%m-%d %H:%M UTC')"
  echo "Domain:  $DOMAIN"
  echo "App dir: $APP_DIR"
  echo "DB name: $DB_NAME"
  echo "DB user: $DB_USER"
  echo "DB pass: $DB_PASS"
  echo ""
  echo "Env file: $ENV_FILE (chmod 640, owned by www-data)"
} > "$CRED_FILE"
chmod 600 "$CRED_FILE"

cat <<EOF

============================================================
 Bootstrap finished
============================================================
 Domain:   http://$DOMAIN  (HTTPS next)
 App dir:  $APP_DIR
 Database: $DB_NAME / $DB_USER
 Password: saved to $CRED_FILE

 Next steps:
   1. Point DNS ($DOMAIN) at this server's public IP.
   2. Enable HTTPS:
        sudo certbot --apache -d $DOMAIN
   3. Fill in SMTP in $ENV_FILE, then:
        sudo systemctl reload apache2
   4. Create the admin account:
        cd $APP_DIR
        sudo -u www-data php create_admin.php admin 'A-Long-Password-12+'
   5. Verify everything:
        sudo -u www-data php deploy/check.php

 To re-run this script later (updates vhost + modules only):
   sudo bash deploy/oracle-setup.sh $DOMAIN
============================================================
EOF
