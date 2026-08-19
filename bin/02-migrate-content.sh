#!/bin/bash
# bin/02-migrate-content.sh
# Stage 02: Shortcode & Gutenberg Content Migration Engine Orchestrator
# Targets: /var/www/backstage.ekkairo.org (DB: backstage_ekk)

set -euo pipefail

STAGING_DIR="/var/www/backstage.ekkairo.org"
WP_DIR="$STAGING_DIR/public"
THEME_DIR="$WP_DIR/wp-content/themes/ekkairo-flagship"
LOG_DIR="$THEME_DIR/ai-work/logs"
MAIN_LOG="$LOG_DIR/02-migrate-content.log"
UNMAPPED_LOG="$LOG_DIR/unmapped-shortcodes.log"

mkdir -p "$LOG_DIR"
: > "$MAIN_LOG"
: > "$UNMAPPED_LOG"

exec > >(tee -a "$MAIN_LOG") 2>&1

echo "=========================================="
echo "Starting Stage 02 Content Engine Migration: $(date)"
echo "Target WP Path: $WP_DIR"
echo "Theme Path: $THEME_DIR"
echo "=========================================="

if [ ! -f "$THEME_DIR/bin/migration-content-engine.php" ]; then
    echo "ERROR: $THEME_DIR/bin/migration-content-engine.php not found!"
    exit 1
fi

cd "$WP_DIR" || exit 1
echo "Executing modular Gutenberg content engine (bin/migration-content-engine.php)..."
php7.4 "$(which wp)" eval-file "$THEME_DIR/bin/migration-content-engine.php" --path="$WP_DIR" --skip-plugins --allow-root

echo "Stage 02 content migration completed successfully at $(date)!"
exit 0
