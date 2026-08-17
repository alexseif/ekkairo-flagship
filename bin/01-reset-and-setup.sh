#!/bin/bash
# bin/01-reset-and-setup.sh
# [DEPRECATED FOR PRODUCTION] 
# This script contains destructive staging commands (db drop, rsync).
echo "CRITICAL ERROR: 01-reset-and-setup.sh is disabled. Use bin/deploy-production.sh for production cutover."
exit 1

# Staging Environment Reset, Theme Activation & Plugin Cleanup Script
# Preserves: wp-config.php, all flagship theme files (wp-content/themes/ekalexandria-flagship/***), etc.
# Targets: /var/www/backstage.ekalexandria.org (DB: backstage_eka)

STAGING_DIR="/var/www/backstage.ekalexandria.org"
WP_DIR="$STAGING_DIR/public"
THEME_DIR="$WP_DIR/wp-content/themes/ekalexandria-flagship"
LOG_DIR="$THEME_DIR/ai-work/logs"
MAIN_LOG="$LOG_DIR/01-reset-and-setup.log"
CPT_LOG="$LOG_DIR/cpt-migration.log"
CLEANUP_LOG="$LOG_DIR/cleanup-plugins.log"

mkdir -p "$LOG_DIR"
> "$MAIN_LOG"
> "$CPT_LOG"
> "$CLEANUP_LOG"

exec > >(tee -a "$MAIN_LOG") 2>&1

echo "=========================================="
echo "Resetting Staging Environment & Setting Up Theme: $(date)"
echo "Target DB: backstage_eka"
echo "=========================================="

# 1. Run Pre-flight Checks
if [ -x "$THEME_DIR/bin/pre-flight.sh" ]; then
    "$THEME_DIR/bin/pre-flight.sh" || { echo "ERROR: Pre-flight checks failed."; exit 1; }
else
    echo "Notice: pre-flight.sh not executable or missing, skipping."
fi

# 2. Database Export via WP-CLI / MySQL from Live Production
PROD_DIR="/var/www/ekalexandria.org"
DUMP_FILE="/tmp/prod_db.sql"

if [ -d "$PROD_DIR/public" ]; then
    echo "Exporting live production database snapshot from $PROD_DIR/public..."
    cd "$PROD_DIR/public" || exit 1
    php7.4 $(which wp) db export "$DUMP_FILE" --hex-blob --default-character-set=utf8mb4 --skip-plugins --allow-root || { echo "ERROR: Live DB export failed."; exit 1; }
else
    echo "ERROR: Production directory $PROD_DIR/public not found. Live DB export aborted."
    exit 1
fi

# 3. Drop, Recreate & Import Fresh Snapshot into backstage_eka
echo "Dropping residual database backstage_eka..."
cd "$WP_DIR" || exit 1
php7.4 $(which wp) db drop --yes --skip-plugins --allow-root || echo "Notice: Database drop returned warning/non-zero."

echo "Recreating clean database backstage_eka..."
php7.4 $(which wp) db create --skip-plugins --allow-root || { echo "ERROR: DB creation failed."; exit 1; }

echo "Importing clean database snapshot into backstage_eka..."
php7.4 $(which wp) db import "$DUMP_FILE" --skip-plugins --allow-root || { echo "ERROR: DB import failed."; exit 1; }
rm -f "$DUMP_FILE"

# 4. Synchronize Staging Files with Preservation Rules
if [ -d "$PROD_DIR/public" ]; then
    echo "Synchronizing staging files from production baseline ($PROD_DIR/public)..."
    rsync -av --delete \
        --exclude='wp-config.php' \
        --exclude='wp-content/uploads/***' \
        --exclude='wp-content/themes/ekalexandria-flagship/***' \
        "$PROD_DIR/public/" "$STAGING_DIR/public/"
fi

# 5. Fix Staging File Permissions
echo "Setting file permissions ownership to alexseif:www-data..."
chown -R alexseif:www-data "$STAGING_DIR"
chmod -R u+w "$STAGING_DIR/public/wp-content"

# 6. Search-Replace Domain Mapping
echo "Performing DB domain mapping (ekalexandria.org -> backstage.ekalexandria.org)..."
cd "$WP_DIR" || exit 1
php7.4 $(which wp) search-replace 'ekalexandria.org' 'backstage.ekalexandria.org' --all-tables --skip-plugins --allow-root
php7.4 $(which wp) search-replace 'www.ekalexandria.org' 'backstage.ekalexandria.org' --all-tables --skip-plugins --allow-root

# 7. Patch Known PHP 7.4/8.0+ Fatal Errors
VC_FILE="$WP_DIR/wp-content/plugins/js_composer/include/classes/editors/class-vc-frontend-editor.php"
if [ -f "$VC_FILE" ]; then
    echo "Patching WPBakery line 339 nested ternary operator error..."
    sed -i 's/\$mode === \$key \? '\'' vc_active'\'' : \$key === '\''default'\'' \&\& \$mode \!== '\''desktop'\'' \? '\'\'': '\'' vc_st_hidden'\''/((\$mode === \$key) ? '\'' vc_active'\'' : ((\$key === '\''default'\'' \&\& \$mode \!== '\''desktop'\'') ? '\'\'': '\'' vc_st_hidden'\''))/g' "$VC_FILE"
fi

# 8. Patch Plugin Vendor Autoloader Hash Mismatches
echo "Resolving plugin vendor autoloader class hash mismatches..."
MAILCHIMP_STATIC="$WP_DIR/wp-content/plugins/mailchimp/vendor/composer/autoload_static.php"
MAILCHIMP_REAL="$WP_DIR/wp-content/plugins/mailchimp/vendor/composer/autoload_real.php"
MAILCHIMP_AUTOLOAD="$WP_DIR/wp-content/plugins/mailchimp/vendor/autoload.php"
if [ -f "$MAILCHIMP_STATIC" ] && [ -f "$MAILCHIMP_REAL" ]; then
    echo "Patching Mailchimp static and real autoloader class hashes..."
    sed -i 's/ComposerStaticInit5b8fa284bf852263974f1227edb89665/ComposerStaticInitb4631e7ae4a2f6a3795a92a813440087/g' "$MAILCHIMP_STATIC"
    sed -i 's/5b8fa284bf852263974f1227edb89665/b4631e7ae4a2f6a3795a92a813440087/g' "$MAILCHIMP_REAL"
    if [ -f "$MAILCHIMP_AUTOLOAD" ]; then sed -i 's/5b8fa284bf852263974f1227edb89665/b4631e7ae4a2f6a3795a92a813440087/g' "$MAILCHIMP_AUTOLOAD"; fi
fi

RANKMATH_STATIC="$WP_DIR/wp-content/plugins/seo-by-rank-math/vendor/composer/autoload_static.php"
RANKMATH_REAL="$WP_DIR/wp-content/plugins/seo-by-rank-math/vendor/composer/autoload_real.php"
RANKMATH_AUTOLOAD="$WP_DIR/wp-content/plugins/seo-by-rank-math/vendor/autoload.php"
if [ -f "$RANKMATH_STATIC" ] && [ -f "$RANKMATH_REAL" ]; then
    echo "Patching Rank Math static and real autoloader class hashes..."
    sed -i 's/ComposerStaticInitc44c881a49042a2b69184cda4e913269/ComposerStaticInitfb8c499ed3b75d2fff76f9fff9e92982/g' "$RANKMATH_STATIC"
    sed -i 's/c44c881a49042a2b69184cda4e913269/fb8c499ed3b75d2fff76f9fff9e92982/g' "$RANKMATH_REAL"
    if [ -f "$RANKMATH_AUTOLOAD" ]; then sed -i 's/c44c881a49042a2b69184cda4e913269/fb8c499ed3b75d2fff76f9fff9e92982/g' "$RANKMATH_AUTOLOAD"; fi
fi

POLYLANG_STATIC="$WP_DIR/wp-content/plugins/polylang/vendor/composer/autoload_static.php"
POLYLANG_REAL="$WP_DIR/wp-content/plugins/polylang/vendor/composer/autoload_real.php"
POLYLANG_AUTOLOAD="$WP_DIR/wp-content/plugins/polylang/vendor/autoload.php"
if [ -f "$POLYLANG_STATIC" ] && [ -f "$POLYLANG_REAL" ]; then
    echo "Patching Polylang static and real autoloader class hashes..."
    sed -i 's/ComposerStaticInited5bec60c42d525a1c1222212c9f9cff/ComposerStaticInit8f862f0d8b75b7170c1f5eb4256b99b4/g' "$POLYLANG_STATIC"
    sed -i 's/ed5bec60c42d525a1c1222212c9f9cff/8f862f0d8b75b7170c1f5eb4256b99b4/g' "$POLYLANG_REAL"
    if [ -f "$POLYLANG_AUTOLOAD" ]; then sed -i 's/ed5bec60c42d525a1c1222212c9f9cff/8f862f0d8b75b7170c1f5eb4256b99b4/g' "$POLYLANG_AUTOLOAD"; fi
fi

# 9. Verify WP-CLI Connection
echo "Verifying WP-CLI connection post-reset..."
php7.4 $(which wp) core version --skip-plugins --allow-root || { echo "ERROR: WP-CLI verification failed."; exit 1; }

# 10. Activate Flagship Theme
echo "Activating ekalexandria-flagship theme..."
cd "$WP_DIR" || exit 1
php7.4 $(which wp) theme activate ekalexandria-flagship --path="$WP_DIR" --allow-root || { echo "ERROR: Theme activation failed."; exit 1; }

# 10b. Assign the requested site logo attachment ID
echo "Assigning the configured site logo..."
LOGO_ID="63053"

if php7.4 $(which wp) post exists "$LOGO_ID" --path="$WP_DIR" --allow-root >/dev/null 2>&1; then
    php7.4 $(which wp) theme mod set custom_logo "$LOGO_ID" --path="$WP_DIR" --allow-root >/dev/null 2>&1
    echo "Assigned site logo attachment ID $LOGO_ID"
else
    echo "WARNING: Attachment $LOGO_ID was not found"
fi

# 11. Remove Legacy BeTheme Theme if Present
echo "Removing legacy BeTheme theme if present..."
THEME_SLUG="betheme"
THEME_PATH="$WP_DIR/wp-content/themes/$THEME_SLUG"

if [ -d "$THEME_PATH" ]; then
    echo "Deleting BeTheme theme via WP-CLI..." | tee -a "$CLEANUP_LOG"
    php7.4 $(which wp) theme delete "$THEME_SLUG" --path="$WP_DIR" --allow-root >> "$CLEANUP_LOG" 2>&1 || echo "WP-CLI theme delete returned a non-zero exit code; continuing with filesystem cleanup." | tee -a "$CLEANUP_LOG"
fi

if [ -d "$THEME_PATH" ]; then
    echo "Removing stale BeTheme theme directory: $THEME_PATH" | tee -a "$CLEANUP_LOG"
    rm -rf "$THEME_PATH"
fi

# 12. Execute CPT Migration (Tachydromos PDFs & Board Member Testimonials)
echo "Executing CPT Migration script (bin/migrate-cpts.php)..."
if [ -f "$THEME_DIR/bin/migrate-cpts.php" ]; then
    php7.4 $(which wp) eval-file "$THEME_DIR/bin/migrate-cpts.php" --path="$WP_DIR" --allow-root || { echo "WARNING: CPT migration returned non-zero exit code."; }
else
    echo "ERROR: bin/migrate-cpts.php not found!"
    exit 1
fi

# 12c. Deactivate Google Captcha Plugin (Without Deleting)
echo "Deactivating google-captcha plugin..."
php7.4 $(which wp) plugin deactivate google-captcha --path="$WP_DIR" --allow-root >> "$CLEANUP_LOG" 2>&1

# 13. Deactivate & Delete Legacy Plugins with Automated Fallback
echo "Cleaning up legacy plugins..."
LEGACY_PLUGINS=(
    "LayerSlider"
    "js_composer"
    "display-posts-shortcode"
    "force-regenerate-thumbnails"
    "ewww-image-optimizer"
    "wordpress-seo"
    "w3-total-cache"
)

PLUGINS_DIR="$WP_DIR/wp-content/plugins"

for plugin in "${LEGACY_PLUGINS[@]}"; do
    echo "Processing legacy plugin: $plugin..."
    php7.4 $(which wp) plugin deactivate "$plugin" --path="$WP_DIR" --allow-root >> "$CLEANUP_LOG" 2>&1
    php7.4 $(which wp) plugin uninstall "$plugin" --deactivate --path="$WP_DIR" --allow-root >> "$CLEANUP_LOG" 2>&1
    
    # Fallback enforcement if directory remains
    TARGET_DIR="$PLUGINS_DIR/$plugin"
    if [ -d "$TARGET_DIR" ]; then
        echo "WP-CLI deletion left directory behind ($plugin). Executing rm -rf fallback..." | tee -a "$CLEANUP_LOG"
        rm -rf "$TARGET_DIR"
    fi
done

# 14. Remove Legacy Drop-ins and Cache Directories
echo "Purging legacy cache drop-ins and configurations..."
DROPINS=(
    "$WP_DIR/wp-content/advanced-cache.php"
    "$WP_DIR/wp-content/object-cache.php"
)

for dropin in "${DROPINS[@]}"; do
    if [ -f "$dropin" ]; then
        echo "Removing drop-in file: $dropin" | tee -a "$CLEANUP_LOG"
        rm -f "$dropin"
    fi
done

CACHE_DIRS=(
    "$WP_DIR/wp-content/cache"
    "$WP_DIR/wp-content/w3tc-config"
)

for cdir in "${CACHE_DIRS[@]}"; do
    if [ -d "$cdir" ]; then
        echo "Removing legacy cache directory: $cdir" | tee -a "$CLEANUP_LOG"
        rm -rf "$cdir"
    fi
done

# 15. Flush Permalinks and Rewrite Rules
echo "Flushing rewrite rules post-setup..."
php7.4 $(which wp) rewrite flush --path="$WP_DIR" --allow-root

# 16. Transient Clean-Up & Cache Flush
echo "Flushing transient cache and object cache..."
cd "$WP_DIR" || exit 1
php7.4 $(which wp) transient delete --all --path="$WP_DIR"
php7.4 $(which wp) cache flush --path="$WP_DIR"

echo "Environment reset, theme setup, CPT migration, and legacy plugin cleanup completed successfully at $(date)!"
exit 0
