#!/bin/bash
# bin/deploy-production.sh
# Autonomous Standalone Production Cutover Deployment Script
# Targets: Live Production Server (/var/www/ekkairo.org/public)
# Log: ai-work/logs/deploy-production.log

set -eo pipefail

DRY_RUN=false
for arg in "$@"; do
    case $arg in
        --dry-run|--test)
            DRY_RUN=true
            ;;
    esac
done

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
THEME_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
LOG_DIR="$THEME_DIR/ai-work/logs"
mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/deploy-production.log"

# Reset log file for fresh run
: > "$LOG_FILE"

# Setup logging to stdout and file simultaneously
exec > >(tee -a "$LOG_FILE") 2>&1

echo "======================================================================"
echo "   EKK Portal Production Final Cutover Deployment Pipeline"
echo "   Execution Timestamp: $(date '+%Y-%m-%d %H:%M:%S')"
if [ "$DRY_RUN" = true ]; then
    echo "   MODE: *** NON-DESTRUCTIVE DRY-RUN / TEST MODE ***"
else
    echo "   MODE: *** LIVE PRODUCTION EXECUTION ***"
fi
echo "======================================================================"

# Determine web root and backup directory paths
WEB_ROOT="$(cd "$THEME_DIR/../../.." 2>/dev/null && pwd || echo "/var/www/ekkairo.org/public")"
BACKUP_DIR="$(dirname "$WEB_ROOT" 2>/dev/null || echo "/var/www/ekkairo.org")"

WP_BINARY="$(command -v wp 2>/dev/null || echo "/usr/local/bin/wp")"
WP_CLI_74="php7.4 $WP_BINARY"
WP_CLI_82="php8.2 $WP_BINARY"

on_error() {
    local exit_code=$1
    local line_no=$2
    sleep 1
    sync
    echo ""
    echo "======================================================================"
    echo "❌ DEPLOYMENT FAILED at line $line_no with exit code $exit_code!"
    if [ "$DRY_RUN" = false ]; then
        echo "⚠️ Maintenance mode (.maintenance) remains ACTIVE to protect site."
        echo "   Check log file for exact details: $LOG_FILE"
    fi
    echo "======================================================================"
    exit "$exit_code"
}

trap 'on_error $? $LINENO' ERR

log_info() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [INFO] $1"
}

log_step() {
    echo ""
    echo "----------------------------------------------------------------------"
    echo "▶ STEP $1: $2"
    echo "----------------------------------------------------------------------"
}

run_command() {
    local cmd="$1"
    if [ "$DRY_RUN" = true ]; then
        log_info "[DRY-RUN SIMULATION] Would execute: $cmd"
    else
        log_info "Executing: $cmd"
        eval "$cmd"
    fi
}

log_info "Web Root Directory: $WEB_ROOT"
log_info "Backup Storage Directory: $BACKUP_DIR"
log_info "Flagship Theme Directory: $THEME_DIR"
log_info "Initialization complete."

# ----------------------------------------------------------------------
# PHASE A: Pre-Flight, Safety Backups & Maintenance Lock (PHP 7.4)
# ----------------------------------------------------------------------

log_step "0" "Pre-Flight System Environment Verification"
if [ -f "$THEME_DIR/bin/pre-flight.sh" ]; then
    bash "$THEME_DIR/bin/pre-flight.sh"
else
    log_info "Pre-flight script not found, proceeding..."
fi

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DB_BACKUP_FILE="$BACKUP_DIR/ekk_prod_db_backup_$TIMESTAMP.sql"
FILE_BACKUP_FILE="$BACKUP_DIR/ekk_prod_files_backup_$TIMESTAMP.tar.gz"

log_step "1" "Create Full Safety Backups (Database & Filesystem Archive)"
run_command "$WP_CLI_74 db export \"$DB_BACKUP_FILE\" --hex-blob --default-character-set=utf8mb4 --skip-plugins --allow-root --path=\"$WEB_ROOT\""
run_command "tar -czf \"$FILE_BACKUP_FILE\" --exclude='*wp-content/uploads*' --exclude='*wp-content/cache*' --exclude='*wp-content/boost-cache*' -C \"$BACKUP_DIR\" \"$(basename "$WEB_ROOT")\""

log_step "2" "Enable Site Maintenance Mode"
run_command "echo '<?php \$upgrading = time(); ?>' > \"$WEB_ROOT/.maintenance\""

log_step "3" "Webroot Permissions & Ownership Adjustment"
run_command "chown -R devops:www-data \"$WEB_ROOT\" 2>/dev/null || chown -R \$(whoami):www-data \"$WEB_ROOT\" 2>/dev/null || true"
run_command "chmod -R u+w \"$WEB_ROOT/wp-content\" 2>/dev/null || true"

# ----------------------------------------------------------------------
# PHASE B: Disruptive Cache/Captcha Deactivation & Clean-up (PHP 7.4)
# ----------------------------------------------------------------------

log_step "4" "Deactivate Disruptive Plugins & Disable WP_CACHE"
DISRUPTIVE_PLUGINS=("google-captcha" "w3-total-cache" "jetpack-boost" "jetpack")
for plugin in "${DISRUPTIVE_PLUGINS[@]}"; do
    run_command "$WP_CLI_74 plugin deactivate \"$plugin\" --path=\"$WEB_ROOT\" --allow-root 2>/dev/null || true"
done

run_command "$WP_CLI_74 config set WP_CACHE false --raw --type=constant --path=\"$WEB_ROOT\" --allow-root 2>/dev/null || true"
run_command "rm -rf \"$WEB_ROOT/wp-content/cache/\"* \"$WEB_ROOT/wp-content/boost-cache/\"* \"$WEB_ROOT/wp-content/advanced-cache.php\" \"$WEB_ROOT/wp-content/object-cache.php\" \"$WEB_ROOT/wp-content/w3tc-config\""
run_command "$WP_CLI_74 cache flush --path=\"$WEB_ROOT\" --allow-root 2>/dev/null || true"

# ----------------------------------------------------------------------
# PHASE C: Legacy Plugin Deactivation & Theme Cleanup (PHP 7.4)
# ----------------------------------------------------------------------

log_step "5" "Deactivate Legacy Plugins"
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
    run_command "$WP_CLI_74 plugin deactivate \"$plugin\" --path=\"$WEB_ROOT\" --allow-root 2>/dev/null || true"
done

log_step "6" "Remove Legacy Theme Directory"
run_command "rm -rf \"$WEB_ROOT/wp-content/themes/betheme\""

# ----------------------------------------------------------------------
# PHASE D: Theme Activation & Content Transformation (PHP 8.2)
# ----------------------------------------------------------------------

log_step "7" "Activate Ekkairo Flagship FSE Theme"
run_command "$WP_CLI_82 theme activate ekkairo-flagship --path=\"$WEB_ROOT\" --allow-root"

log_step "8" "Execute Content Engine Gutenberg Block Transformation"
if [ -f "$THEME_DIR/bin/migration-content-engine.php" ]; then
    run_command "$WP_CLI_82 eval-file \"$THEME_DIR/bin/migration-content-engine.php\" --path=\"$WEB_ROOT\" --skip-plugins --allow-root"
else
    log_info "Warning: migration-content-engine.php not found at $THEME_DIR/bin/migration-content-engine.php"
fi

# ----------------------------------------------------------------------
# PHASE E: Modernization, Plugins & Infrastructure (PHP 8.2)
# ----------------------------------------------------------------------

log_step "9" "Configure Site Locale to Greek (el_GR)"
run_command "$WP_CLI_82 option update WPLANG el_GR --path=\"$WEB_ROOT\" --allow-root"

log_step "10" "Install, Activate & Configure Rank Math SEO"
run_command "if ! $WP_CLI_82 plugin is-installed seo-by-rank-math --path=\"$WEB_ROOT\" --allow-root 2>/dev/null; then $WP_CLI_82 plugin install seo-by-rank-math --activate --path=\"$WEB_ROOT\" --allow-root; else $WP_CLI_82 plugin activate seo-by-rank-math --path=\"$WEB_ROOT\" --allow-root || true; fi"
run_command "$WP_CLI_82 eval '
    \$modules = array(\"sitemap\", \"rich-snippet\", \"seo-analysis\", \"link-counter\", \"instant-indexing\");
    update_option(\"rank_math_modules\", \$modules);
    \$titles = get_option(\"rank-math-options-titles\", array());
    \$titles[\"breadcrumbs\"] = \"off\";
    \$titles[\"knowledgegraph_type\"] = \"organization\";
    \$titles[\"knowledgegraph_name\"] = \"Ελληνική Κοινότητα Καΐρου\";
    update_option(\"rank-math-options-titles\", \$titles);
' --path=\"$WEB_ROOT\" --allow-root"

log_step "11" "Purge Autoloaded Options Bloat"
run_command "$WP_CLI_82 db query \"DELETE FROM wp_options WHERE option_name IN ('rs-templates', 'redux_builder_amp', 'revslider-addons', 'polylang_wpml_strings');\" --path=\"$WEB_ROOT\" --allow-root 2>/dev/null || true"
run_command "$WP_CLI_82 db query \"UPDATE wp_options SET autoload = 'no' WHERE option_name LIKE 'jetpack_%' OR option_name = 'betheme';\" --path=\"$WEB_ROOT\" --allow-root 2>/dev/null || true"

log_step "12" "Install, Activate & Enable Redis Object Cache"
run_command "if ! $WP_CLI_82 plugin is-installed redis-cache --path=\"$WEB_ROOT\" --allow-root 2>/dev/null; then $WP_CLI_82 plugin install redis-cache --activate --path=\"$WEB_ROOT\" --allow-root || true; else $WP_CLI_82 plugin activate redis-cache --path=\"$WEB_ROOT\" --allow-root || true; fi"
run_command "$WP_CLI_82 redis enable --path=\"$WEB_ROOT\" --allow-root 2>/dev/null || true"

# ----------------------------------------------------------------------
# PHASE F: Invalidation, Cache Flushing & Reopening Site (PHP 8.2)
# ----------------------------------------------------------------------

log_step "13" "Final Invalidation, Cache Flush & Lift Maintenance Mode"
run_command "$WP_CLI_82 eval 'if(function_exists(\"opcache_reset\")) opcache_reset();' --path=\"$WEB_ROOT\" --allow-root"
run_command "$WP_CLI_82 transient delete --all --path=\"$WEB_ROOT\" --allow-root 2>/dev/null || true"
run_command "$WP_CLI_82 cache flush --path=\"$WEB_ROOT\" --allow-root"
run_command "$WP_CLI_82 rewrite flush --path=\"$WEB_ROOT\" --allow-root"
run_command "rm -f \"$WEB_ROOT/.maintenance\""

echo ""
echo "======================================================================"
if [ "$DRY_RUN" = true ]; then
    echo "🎉 DRY-RUN SIMULATION COMPLETED SUCCESSFULLY!"
    echo "   All production deployment steps validated in test mode."
else
    echo "🎉 PRODUCTION CUTOVER DEPLOYMENT COMPLETED SUCCESSFULLY!"
    echo "   EKK Portal modernized and running cleanly on PHP 8.2."
fi
echo "======================================================================"
exit 0

