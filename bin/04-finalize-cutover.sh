#!/bin/bash
# bin/04-finalize-cutover.sh
# Stage 04: Plugin Cleanup, Theme Activation & Site Cutover Automation
# Targets: /var/www/backstage.ekkairo.org (DB: backstage_ekk)

set -euo pipefail

STAGING_DIR="/var/www/backstage.ekkairo.org"
WP_DIR="$STAGING_DIR/public"
THEME_DIR="$WP_DIR/wp-content/themes/ekkairo-flagship"
LOG_DIR="$THEME_DIR/ai-work/logs"
MAIN_LOG="$LOG_DIR/04-finalize-cutover.log"

mkdir -p "$LOG_DIR"
: > "$MAIN_LOG"

exec > >(tee -a "$MAIN_LOG") 2>&1

echo "=========================================="
echo "Starting Stage 04: Finalize Cutover: $(date)"
echo "Target WP Path: $WP_DIR"
echo "Theme Path: $THEME_DIR"
echo "=========================================="

WP_CLI="$(which wp)"

# 1. Deactivate Legacy & Unused Plugins (Disregard Matrix)
echo "Deactivating legacy plugins..."
LEGACY_PLUGINS=(
    "polylang"
    "polylang-theme-strings"
    "awesome-weather"
    "facebook-pixel"
    "LayerSlider"
    "js_composer"
    "pdf-image-generator"
    "php-compatibility-checker"
    "show-hide-author"
    "wp-missed-schedule-master"
    "mailchimp"
    "force-regenerate-thumbnails"
    "disable-comments"
    "manage-xml-rpc"
    "duplicate-post"
    "aryo-activity-log"
)

for plugin in "${LEGACY_PLUGINS[@]}"; do
    if php7.4 "$WP_CLI" plugin is-installed "$plugin" --path="$WP_DIR" --allow-root 2>/dev/null; then
        echo "Deactivating plugin: $plugin"
        php7.4 "$WP_CLI" plugin deactivate "$plugin" --path="$WP_DIR" --allow-root 2>/dev/null || true
    fi
done

# 2. Configure Locale to Greek (el_GR)
echo "Setting site locale to Greek (el_GR)..."
php7.4 "$WP_CLI" option update WPLANG el_GR --path="$WP_DIR" --allow-root

# 3. Activate Ekkairo Flagship FSE Block Theme
echo "Activating ekkairo-flagship FSE theme..."
php8.2 "$WP_CLI" theme activate ekkairo-flagship --path="$WP_DIR" --allow-root

# 4. Flush Transients, Object Cache, and Rewrite Rules
echo "Flushing transients, cache, and rewrite rules..."
php8.2 "$WP_CLI" transient delete --all --path="$WP_DIR" --allow-root || true
php8.2 "$WP_CLI" cache flush --path="$WP_DIR" --allow-root || true
php8.2 "$WP_CLI" rewrite flush --path="$WP_DIR" --allow-root || true

echo "=========================================="
echo "Stage 04 Finalize Cutover Completed Successfully at $(date)!"
echo "=========================================="
exit 0
