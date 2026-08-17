#!/bin/bash
# bin/02-migrate-content.sh
# Stage 02: Shortcode & Gutenberg Content Migration Engine Orchestrator
# Targets: /var/www/backstage.ekalexandria.org (DB: backstage_eka)

set -euo pipefail

STAGING_DIR="/var/www/backstage.ekalexandria.org"
WP_DIR="$STAGING_DIR/public"
THEME_DIR="$WP_DIR/wp-content/themes/ekalexandria-flagship"
LOG_DIR="$THEME_DIR/ai-work/logs"
MAIN_LOG="$LOG_DIR/02-migrate-content.log"

mkdir -p "$LOG_DIR"
: > "$MAIN_LOG"

exec > >(tee -a "$MAIN_LOG") 2>&1

echo "=========================================="
echo "Starting Stage 02 Content Engine Migration: $(date)"
echo "Target WP Path: $WP_DIR"
echo "=========================================="

if [ ! -f "$THEME_DIR/bin/migration-content-engine.php" ]; then
    echo "ERROR: $THEME_DIR/bin/migration-content-engine.php not found!"
    exit 1
fi

echo "Executing 6-step content engine (bin/migration-content-engine.php)..."
php8.2 "$(which wp)" eval-file "$THEME_DIR/bin/migration-content-engine.php" --path="$WP_DIR" --skip-plugins

echo "Stage 02 content migration completed successfully at $(date)!"
exit 0
