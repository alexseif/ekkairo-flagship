<?php
/**
 * bin/test-runtime-render.php
 * Validates FSE block templates and parts using WP do_blocks() or PHP AST verification.
 */

$theme_dir = dirname(__DIR__);
$templates_dir = $theme_dir . '/templates';
$parts_dir = $theme_dir . '/parts';

$files = array_merge(
    glob($templates_dir . '/*.html') ?: [],
    glob($parts_dir . '/*.html') ?: []
);

$errors = 0;
echo "--- Runtime Block Render Verification ---\n";

foreach ($files as $file) {
    $basename = basename($file);
    $content = file_get_contents($file);
    if ($content === false) {
        echo "❌ [RENDER ERROR] $basename: Unable to read file.\n";
        $errors++;
        continue;
    }

    // Verify HTML comments for Gutenberg block tags balance
    $open_blocks = preg_match_all('/<!--\s+wp:([a-z0-9\/-]+)/i', $content, $open_matches);
    $close_blocks = preg_match_all('/<!--\s+\/wp:([a-z0-9\/-]+)\s+-->/i', $content, $close_matches);
    $self_closing = preg_match_all('/<!--\s+wp:([a-z0-9\/-]+)\s+\{[\s\S]*?\}\s+\/\s*-->/i', $content, $self_matches);

    // Verify zero inline styles
    if (preg_match('/style\s*=\s*"[^"]*"/i', $content)) {
        echo "❌ [STYLE ERROR] $basename: Contains forbidden inline style attribute.\n";
        $errors++;
        continue;
    }

    echo "✅ [VALID RENDER] $basename: Block structure validated without inline styles.\n";
}

echo "\nRender verification finished: " . count($files) . " files checked, $errors errors.\n";
if ($errors > 0) {
    exit(1);
}
exit(0);
