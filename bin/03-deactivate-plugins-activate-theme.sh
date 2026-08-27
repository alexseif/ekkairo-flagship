#!/bin/bash
# bin/03-deactivate-plugins-activate-theme.sh
# Stage 03: Legacy Plugin Deactivation, Greek Locale Setting & FSE Theme Activation
# Targets: /var/www/backstage.ekkairo.org (DB: backstage_ekk)

set -euo pipefail

STAGING_DIR="/var/www/backstage.ekkairo.org"
WP_DIR="$STAGING_DIR/public"
THEME_DIR="$WP_DIR/wp-content/themes/ekkairo-flagship"
LOG_DIR="$THEME_DIR/ai-work/logs"
MAIN_LOG="$LOG_DIR/03-deactivate-plugins-activate-theme.log"

mkdir -p "$LOG_DIR"
: > "$MAIN_LOG"

exec > >(tee -a "$MAIN_LOG") 2>&1

echo "=========================================="
echo "Starting Stage 03: Plugin Cleanup & Theme Activation: $(date)"
echo "Target WP Path: $WP_DIR"
echo "Theme Path: $THEME_DIR"
echo "=========================================="

cd "$WP_DIR" || exit 1
WP_CLI="$(which wp)"

# 1. Deactivate Legacy & Unused Plugins (Disregard Matrix)
echo "Deactivating 16 legacy plugins..."
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
    "jetpack"
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

# 3. Install, Activate & Configure Rank Math SEO
echo "Installing, activating and configuring Rank Math SEO..."
if ! php8.2 "$WP_CLI" plugin is-installed seo-by-rank-math --path="$WP_DIR" --allow-root 2>/dev/null; then
    php8.2 "$WP_CLI" plugin install seo-by-rank-math --activate --path="$WP_DIR" --allow-root
else
    php8.2 "$WP_CLI" plugin activate seo-by-rank-math --path="$WP_DIR" --allow-root || true
fi

php8.2 "$WP_CLI" eval '
    $modules = array("sitemap", "rich-snippet", "seo-analysis", "link-counter", "instant-indexing");
    update_option("rank_math_modules", $modules);
    $titles = get_option("rank-math-options-titles", array());
    $titles["breadcrumbs"] = "off";
    $titles["knowledgegraph_type"] = "organization";
    $titles["knowledgegraph_name"] = "Ελληνική Κοινότητα Αλεξανδρείας";
    update_option("rank-math-options-titles", $titles);
' --path="$WP_DIR" --allow-root

# 4. Activate Ekkairo Flagship FSE Block Theme
echo "Activating ekkairo-flagship FSE theme..."
php8.2 "$WP_CLI" theme activate ekkairo-flagship --path="$WP_DIR" --allow-root

# 5. Transient Clean-Up & Cache Flush
echo "Flushing transient cache and object cache..."
cd "$WP_DIR" || exit 1
php8.2 "$WP_CLI" transient delete --all --path="$WP_DIR" --allow-root || true
php8.2 "$WP_CLI" cache flush --path="$WP_DIR" --allow-root || true
php8.2 "$WP_CLI" rewrite flush --path="$WP_DIR" --allow-root || true

echo "=========================================="
echo "Stage 03 Deactivate Plugins & Activate Theme Completed Successfully at $(date)!"
echo "=========================================="
exit 0
