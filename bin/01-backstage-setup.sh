#!/bin/bash
# bin/01-backstage-setup.sh
# EKK Portal Staging Environment Reset, Theme Activation & Setup Script
# Targets: /var/www/backstage.ekkairo.org (DB: backstage_ekk)

set -euo pipefail

STAGING_DIR="/var/www/backstage.ekkairo.org"
WP_DIR="$STAGING_DIR/public"
THEME_DIR="$WP_DIR/wp-content/themes/ekkairo-flagship"
LOG_DIR="$THEME_DIR/ai-work/logs"
TMP_DIR="$THEME_DIR/ai-work/tmp"
MAIN_LOG="$LOG_DIR/01-backstage-setup.log"

mkdir -p "$LOG_DIR" "$TMP_DIR"
chmod 700 "$TMP_DIR"
: > "$MAIN_LOG"

exec > >(tee -a "$MAIN_LOG") 2>&1

DUMP_FILE="$TMP_DIR/ekk_prod_db_$(date +%s).sql"
trap 'rm -f "$DUMP_FILE"' EXIT

echo "=========================================="
echo "Resetting Staging Environment: $(date)"
echo "Target DB: backstage_ekk"
echo "=========================================="

# Guardrail 1: Confirm WP_DIR is staging webroot
if [ "$WP_DIR" != "/var/www/backstage.ekkairo.org/public" ]; then
    echo "FATAL ERROR: Target WP_DIR is invalid: $WP_DIR"
    exit 1
fi

# Guardrail 2: Confirm Target Database is backstage_ekk and NOT production (db207080_ekk)
TARGET_DB=$(cd "$WP_DIR" && php7.4 /usr/local/bin/wp config get DB_NAME --allow-root 2>/dev/null || echo "unknown")
echo "Verified Target DB Name: $TARGET_DB"

if [ "$TARGET_DB" = "db207080_ekk" ] || [[ "$TARGET_DB" == *"prod"* ]]; then
    echo "FATAL ERROR: Target database '$TARGET_DB' is PRODUCTION! Aborting reset."
    exit 1
fi

if [ "$TARGET_DB" != "backstage_ekk" ]; then
    echo "FATAL ERROR: Unexpected DB '$TARGET_DB' (expected 'backstage_ekk'). Aborting."
    exit 1
fi

# 1. Run Pre-flight Checks
if [ -x "$THEME_DIR/bin/pre-flight.sh" ]; then
    "$THEME_DIR/bin/pre-flight.sh" || { echo "Notice: Pre-flight checks returned warnings."; }
fi

# 2. Database Export from Live Production Snapshot via WP-CLI
PROD_DIR="/var/www/ekkairo.org"

if [ -d "$PROD_DIR/public" ]; then
    echo "Exporting live database snapshot from $PROD_DIR/public via WP-CLI..."
    (cd "$PROD_DIR/public" && php7.4 /usr/local/bin/wp db export "$DUMP_FILE" --hex-blob --default-character-set=utf8mb4 --skip-plugins --allow-root) || { echo "ERROR: Live DB export failed."; exit 1; }
else
    echo "ERROR: Production directory $PROD_DIR/public not found."
    exit 1
fi

# 3. Reset Staging DB & Import Snapshot via WP-CLI
cd "$WP_DIR" || exit 1
echo "Resetting staging database $TARGET_DB and importing snapshot..."
php7.4 /usr/local/bin/wp db reset --yes --skip-plugins --allow-root || { echo "ERROR: DB reset failed."; exit 1; }
php7.4 /usr/local/bin/wp db import "$DUMP_FILE" --skip-plugins --allow-root || { echo "ERROR: DB import failed."; exit 1; }

# 4. Synchronize Staging Files with Preservation Rules
if [ -d "$PROD_DIR/public" ]; then
    echo "Synchronizing staging files from production baseline..."
    rsync -av --delete \
        --exclude='wp-config.php' \
        --exclude='wp-content/uploads/' \
        --exclude='wp-content/themes/ekkairo-flagship/' \
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
