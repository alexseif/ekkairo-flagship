<?php
/**
 * Stage 2: Idempotent Classic HTML to Gutenberg Block Converter Script
 *
 * Programmatically wraps bare HTML elements (<p>, <hN>, <ul>/<ol>, <table>, <blockquote>)
 * into native Gutenberg block markup while sanitizing inline CSS via FSE property allowlist.
 */

require_once __DIR__ . '/migration-helpers.php';

$log_file = dirname(__DIR__) . '/ai-work/logs/convert-classic-to-gutenberg.log';
eka_init_log_file($log_file);

function log_msg($msg, $level = 'INFO') {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $formatted = "[{$timestamp}] [{$level}] {$msg}\n";
    file_put_contents($log_file, $formatted, FILE_APPEND);
    echo $formatted;
}

log_msg("Starting Stage 2: Idempotent Classic HTML to Gutenberg Block Converter Script");

$db_config = eka_get_db_config();
$mysqli = new mysqli($db_config['host'], $db_config['user'], $db_config['pass'], $db_config['name']);
if ($mysqli->connect_error) {
    log_msg("Database connection failed: " . $mysqli->connect_error, "ERROR");
    die("Connection failed: " . $mysqli->connect_error . "\n");
}
$mysqli->set_charset("utf8mb4");
log_msg("Connected to target database: $dbname");

use EkaAlexandria\Migration\Content\ContentTransformer;

function clean_html_inline_styles($html) {
    $transformer = new ContentTransformer();
    return $transformer->cleanHtmlInlineStyles((string)$html);
}

function convert_html_elements_to_blocks($html) {
    $transformer = new ContentTransformer();
    return $transformer->convertHtmlElementsToBlocks((string)$html);
}

function process_post_content_idempotent($content) {
    $transformer = new ContentTransformer();
    return $transformer->processClassicHtml((string)$content);
}

// Fetch posts
$sql = "SELECT ID, post_title, post_content FROM wp_posts WHERE post_type IN ('page', 'post', 'testimonial') AND post_status IN ('publish', 'draft', 'private', 'pending', 'future')";
$res = $mysqli->query($sql);

if (!$res) {
    log_msg("Query failed: " . $mysqli->error, "ERROR");
    exit(1);
}

$total_posts = $res->num_rows;
log_msg("Analyzing {$total_posts} posts for classic HTML to Gutenberg conversion.");

$updated_count = 0;
$skipped_count = 0;

while ($row = $res->fetch_assoc()) {
    $id = (int)$row['ID'];
    $original_content = $row['post_content'];

    $converted_content = process_post_content_idempotent($original_content);

    if ($converted_content === $original_content) {
        continue; // Idempotent skip (no changes needed)
    }

    // AST Validation check
    if (!eka_validate_blocks_ast($converted_content)) {
        log_msg("AST validation failed for post ID {$id} ('{$row['post_title']}'). Skipping update.", "WARNING");
        $skipped_count++;
        continue;
    }

    // Save update to backstage_eka
    $stmt = $mysqli->prepare("UPDATE wp_posts SET post_content = ? WHERE ID = ?");
    if ($stmt) {
        $stmt->bind_param("si", $converted_content, $id);
        if ($stmt->execute()) {
            $updated_count++;
            log_msg("Successfully converted classic HTML to blocks in Post ID {$id} ('{$row['post_title']}').");
        } else {
            log_msg("Failed to update Post ID {$id}: " . $stmt->error, "ERROR");
            $skipped_count++;
        }
        $stmt->close();
    }
}

log_msg("Stage 2 Migration Summary:");
log_msg(" - Posts updated: {$updated_count}");
log_msg(" - Posts skipped/unchanged: " . ($total_posts - $updated_count));

$mysqli->close();
log_msg("Stage 2 completed successfully.");
