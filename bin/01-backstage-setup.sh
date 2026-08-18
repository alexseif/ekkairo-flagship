#!/bin/bash
# bin/01-backstage-setup.sh
# EKK Portal Staging Environment Reset, Theme Activation & Setup Script
# Targets: /var/www/backstage.ekkairo.org (DB: backstage_ekk)

set -eo pipefail

STAGING_DIR="/var/www/backstage.ekkairo.org"
WP_DIR="$STAGING_DIR/public"
THEME_DIR="$WP_DIR/wp-content/themes/ekkairo-flagship"
LOG_DIR="$THEME_DIR/ai-work/logs"
MAIN_LOG="$LOG_DIR/01-backstage-setup.log"

mkdir -p "$LOG_DIR"
: > "$MAIN_LOG"

exec > >(tee -a "$MAIN_LOG") 2>&1

echo "=========================================="
echo "Resetting Staging Environment: $(date)"
echo "Target DB: backstage_ekk"
echo "=========================================="

# 1. Run Pre-flight Checks
if [ -x "$THEME_DIR/bin/pre-flight.sh" ]; then
    "$THEME_DIR/bin/pre-flight.sh" || { echo "Notice: Pre-flight checks returned warnings."; }
fi

# 2. Database Export from Live Production Snapshot via WP-CLI
PROD_DIR="/var/www/ekkairo.org"
DUMP_FILE="/tmp/ekk_prod_db.sql"

if [ -d "$PROD_DIR/public" ]; then
    echo "Exporting live database snapshot from $PROD_DIR/public via WP-CLI..."
    (cd "$PROD_DIR/public" && php7.4 /usr/local/bin/wp db export "$DUMP_FILE" --hex-blob --default-character-set=utf8mb4 --skip-plugins --allow-root) || { echo "ERROR: Live DB export failed."; exit 1; }
else
    echo "ERROR: Production directory $PROD_DIR/public not found."
    exit 1
fi

# 3. Reset Staging DB & Import Snapshot via WP-CLI
echo "Resetting database backstage_ekk and importing snapshot via WP-CLI..."
(cd "$WP_DIR" && php7.4 /usr/local/bin/wp db reset --yes --skip-plugins --allow-root) || { echo "ERROR: DB reset failed."; exit 1; }
(cd "$WP_DIR" && php7.4 /usr/local/bin/wp db import "$DUMP_FILE" --skip-plugins --allow-root) || { echo "ERROR: DB import failed."; exit 1; }
rm -f "$DUMP_FILE"

# 4. Synchronize Staging Files with Preservation Rules
if [ -d "$PROD_DIR/public" ]; then
    echo "Synchronizing staging files from production baseline..."
    rsync -av --delete \
        --exclude='wp-config.php' \
        --exclude='wp-content/uploads***' \
        --exclude='wp-content/themes/ekkairo-flagship***' \
        "$PROD_DIR/public/" "$STAGING_DIR/public/"
fi

# 5. Search-Replace Domain Mapping
echo "Performing DB domain mapping (ekkairo.org -> backstage.ekkairo.org)..."
php7.4 /usr/local/bin/wp search-replace 'https://ekkairo.org' 'https://backstage.ekkairo.org' --path="$WP_DIR" --all-tables --skip-plugins --allow-root || true
php7.4 /usr/local/bin/wp search-replace 'http://ekkairo.local' 'https://backstage.ekkairo.org' --path="$WP_DIR" --all-tables --skip-plugins --allow-root || true

# 6. Deactivate Captcha & Cache Plugins, Purge Cache & Disable WP_CACHE Directive
echo "Deactivating captcha and cache plugins..."
php7.4 /usr/local/bin/wp plugin deactivate google-captcha w3-total-cache jetpack-boost --path="$WP_DIR" --allow-root || true

echo "Disabling WP_CACHE in wp-config.php..."
php7.4 /usr/local/bin/wp config set WP_CACHE false --raw --type=constant --path="$WP_DIR" --allow-root || true

echo "Purging drop-in cache files and static cache directories..."
rm -rf "$WP_DIR/wp-content/cache/"* "$WP_DIR/wp-content/boost-cache/"* "$WP_DIR/wp-content/advanced-cache.php" "$WP_DIR/wp-content/object-cache.php"
php7.4 /usr/local/bin/wp cache flush --path="$WP_DIR" --allow-root || true

echo "Staging environment setup completed successfully at $(date)!"
exit 0

