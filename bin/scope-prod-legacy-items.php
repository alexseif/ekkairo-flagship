<?php
/**
 * bin/scope-prod-legacy-items.php
 *
 * Scopes legacy shortcodes, classic content, sliders, WPBakery, MFN items,
 * and custom shortcodes directly from the local production database (db207080_eka).
 *
 * Marks each item as 'implemented' or 'not_implemented' based on current CLI migration capabilities.
 */

$host = 'localhost';
$user = 'root';
$pass = '0024';
$dbname = 'db207080_eka';

$mysqli = new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error . "\n");
}
$mysqli->set_charset("utf8mb4");

echo "Connected to production database local: $dbname\n";

$scoping_dir = dirname(__DIR__) . '/ai-work/scopings';
if (!file_exists($scoping_dir)) {
    mkdir($scoping_dir, 0755, true);
}
$output_file = $scoping_dir . '/legacy-items-inventory.json';

// Query all pages, posts, and custom post types
$res = $mysqli->query("SELECT ID, post_title, post_type, post_name, post_content, post_status FROM wp_posts WHERE post_type IN ('page', 'post', 'testimonial') AND post_status IN ('publish', 'draft', 'private', 'pending', 'future')");

$total_posts = $res->num_rows;
echo "Found $total_posts total posts/pages/testimonials to analyze in $dbname.\n";

$inventory = [];
$stats = [
    'posts_analyzed' => $total_posts,
    'items_found' => 0,
    'implemented_count' => 0,
    'not_implemented_count' => 0,
    'by_type' => []
];

// Helper to evaluate implementation status
function evaluate_status($item_type, $tag, $raw_snippet, $post_id) {
    // 1. Slider replacement
    if ($item_type === 'rev_slider' || $item_type === 'layerslider') {
        $dynamic_pages = [13236, 8934, 16894, 16892, 17194, 17215, 17219, 16920, 16923];
        $static_pages = [7820, 17129, 17133, 7811, 17137, 17139, 3442, 17023, 17027, 17155, 7756, 17150, 7390, 17018, 17020];
        if (in_array($post_id, $dynamic_pages) || in_array($post_id, $static_pages)) {
            return ['implemented', 'wp eka replace-sliders', 'Replaced with core/query or core/gallery block via page-ID mapping'];
        }
        return ['not_implemented', null, 'Generic programmatic replacement of rev_slider/layerslider to core/gallery or core/cover block across all posts'];
    }

    // 2. Testimonials
    if ($item_type === 'testimonials' || $tag === 'testimonials' || $item_type === 'testimonial_cpt') {
        return ['implemented', 'wp eka migrate-board & wp eka remediate-shortcodes', 'Migrated testimonial CPT to board_member CPT and remediated shortcodes to board_member query loop'];
    }

    // 3. vc_posts_grid
    if ($tag === 'vc_posts_grid') {
        return ['implemented', 'wp eka remediate-shortcodes', 'Parsed by_id parameters and converted to core/query block'];
    }

    // 4. Standard WPBakery structural shortcodes (vc_row, vc_column, vc_column_text, etc.)
    if (strpos($tag, 'vc_') === 0) {
        if (in_array($tag, ['vc_row', 'vc_column', 'vc_column_text'])) {
            return ['implemented', 'wp eka remediate-shortcodes', 'Stripped WPBakery markup tags leaving raw text/HTML'];
        }
        if ($tag === 'vc_single_image') {
            return ['not_implemented', null, 'Convert vc_single_image (extracting image ID/URL) into native core/image block'];
        }
        if ($tag === 'vc_raw_html') {
            return ['not_implemented', null, 'Convert vc_raw_html shortcode contents into native core/html block'];
        }
        return ['not_implemented', null, 'Convert generic WPBakery element into equivalent native Gutenberg block'];
    }

    // 5. WP native [caption] shortcode
    if ($tag === 'caption') {
        return ['not_implemented', null, 'Programmatically transform classic [caption] shortcode into native core/image or core/figure block with caption'];
    }

    // 6. [our_team] shortcode
    if ($tag === 'our_team' || $tag === 'our_team_list') {
        return ['not_implemented', null, 'Transform [our_team] shortcodes into board_member query loop block'];
    }

    // 7. MFN Builder Postmeta
    if ($item_type === 'mfn_builder_item') {
        return ['not_implemented', null, 'Extract MFN content fields and convert to core/group, core/heading, core/paragraph blocks'];
    }

    // 8. Classic non-gutenberg content (post_content without Gutenberg markup)
    if ($item_type === 'classic_content') {
        return ['not_implemented', null, 'Programmatically run wp_gutenberg_post_content_filter / use_block_editor auto-conversion into native Gutenberg blocks (Paragraphs, Headings, Lists, Images)'];
    }

    // 9. Other shortcodes (map, gview, mc4wp_form, etc.)
    if (!empty($tag)) {
        return ['not_implemented', null, 'Remediate custom shortcode or wrap in native block'];
    }

    return ['not_implemented', null, 'Needs migration solution'];
}

while ($row = $res->fetch_assoc()) {
    $id = (int)$row['ID'];
    $title = $row['post_title'];
    $type = $row['post_type'];
    $slug = $row['post_name'];
    $content = $row['post_content'];

    $has_gutenberg = (strpos($content, '<!-- wp:') !== false);
    $detected_in_post = [];

    // Check sliders & shortcodes
    if (preg_match_all('/\[([a-zA-Z0-9_]+)([^\]]*)\]/', $content, $matches, PREG_SET_ORDER)) {
        $seen_tags = [];
        foreach ($matches as $match) {
            $tag = strtolower($match[1]);
            $raw_snippet = $match[0];

            if (in_array($tag, ['endif', 'if', 'the', 'general', 'this', 'list', 'in', 'it', 'on', 'was', 'who', 'f'])) {
                continue; // Ignore false positives (regular text enclosed in brackets)
            }

            $item_type = 'shortcode';
            if ($tag === 'rev_slider' || $tag === 'rev_slider_vc') {
                $item_type = 'rev_slider';
            } elseif ($tag === 'layerslider') {
                $item_type = 'layerslider';
            } elseif (strpos($tag, 'vc_') === 0) {
                $item_type = 'wpbakery_shortcode';
            } elseif ($tag === 'testimonials' || $tag === 'testimonial') {
                $item_type = 'testimonials';
            } elseif ($tag === 'caption') {
                $item_type = 'caption';
            } elseif ($tag === 'our_team' || $tag === 'our_team_list') {
                $item_type = 'our_team';
            }

            // Group duplicate tags per page to prevent log explosion while preserving counts
            $key = $item_type . ':' . $tag;
            if (!isset($seen_tags[$key])) {
                $seen_tags[$key] = [
                    'tag' => $tag,
                    'item_type' => $item_type,
                    'raw_snippet' => mb_strlen($raw_snippet) > 200 ? mb_substr($raw_snippet, 0, 200) . '...' : $raw_snippet,
                    'count' => 1
                ];
            } else {
                $seen_tags[$key]['count']++;
            }
        }

        foreach ($seen_tags as $st) {
            list($status, $handler, $remediation) = evaluate_status($st['item_type'], $st['tag'], $st['raw_snippet'], $id);
            $detected_in_post[] = [
                'page_id' => $id,
                'page_title' => $title,
                'post_type' => $type,
                'item_type' => $st['item_type'],
                'item_identifier' => $st['tag'],
                'count' => $st['count'],
                'raw_snippet' => $st['raw_snippet'],
                'status' => $status,
                'implementation_handler' => $handler,
                'proposed_remediation' => $remediation
            ];
        }
    }

    // Check Classic Content (HTML without Gutenberg blocks)
    if (!$has_gutenberg && !empty(trim(strip_tags($content)))) {
        list($status, $handler, $remediation) = evaluate_status('classic_content', null, mb_substr($content, 0, 100), $id);
        $detected_in_post[] = [
            'page_id' => $id,
            'page_title' => $title,
            'post_type' => $type,
            'item_type' => 'classic_content',
            'item_identifier' => 'classic_post_content',
            'count' => 1,
            'raw_snippet' => 'Classic HTML content (Length: ' . mb_strlen($content) . ' characters)',
            'status' => $status,
            'implementation_handler' => $handler,
            'proposed_remediation' => $remediation
        ];
    }

    foreach ($detected_in_post as $item) {
        $inventory[] = $item;
        $stats['items_found']++;
        if ($item['status'] === 'implemented') {
            $stats['implemented_count']++;
        } else {
            $stats['not_implemented_count']++;
        }
        $stats['by_type'][$item['item_type']] = ($stats['by_type'][$item['item_type']] ?? 0) + 1;
    }
}

// Check postmeta for MFN items
$mfn_res = $mysqli->query("SELECT post_id, meta_value FROM wp_postmeta WHERE meta_key = '_mfn-builder-items' AND meta_value != '' AND meta_value != 'a:0:{}'");
while ($mfn_row = $mfn_res->fetch_assoc()) {
    $pid = (int)$mfn_row['post_id'];
    $post_title_res = $mysqli->query("SELECT post_title, post_type FROM wp_posts WHERE ID = $pid");
    if ($pt_row = $post_title_res->fetch_assoc()) {
        list($status, $handler, $remediation) = evaluate_status('mfn_builder_item', null, 'MFN builder postmeta', $pid);
        $inventory[] = [
            'page_id' => $pid,
            'page_title' => $pt_row['post_title'],
            'post_type' => $pt_row['post_type'],
            'item_type' => 'mfn_builder_item',
            'item_identifier' => '_mfn-builder-items',
            'count' => 1,
            'raw_snippet' => 'MFN builder postmeta payload',
            'status' => $status,
            'implementation_handler' => $handler,
            'proposed_remediation' => $remediation
        ];
        $stats['items_found']++;
        if ($status === 'implemented') {
            $stats['implemented_count']++;
        } else {
            $stats['not_implemented_count']++;
        }
        $stats['by_type']['mfn_builder_item'] = ($stats['by_type']['mfn_builder_item'] ?? 0) + 1;
    }
}

$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
file_put_contents($output_file, json_encode($inventory, $flags));

echo sprintf("\nScoping from production database local ($dbname) completed successfully!\n");
echo sprintf("Total legacy items scoped: %d\n", $stats['items_found']);
echo sprintf(" - Implemented: %d items\n", $stats['implemented_count']);
echo sprintf(" - Not Implemented: %d items\n", $stats['not_implemented_count']);
echo "\nBreakdown by Item Type:\n";
foreach ($stats['by_type'] as $type => $count) {
    echo sprintf(" - %s: %d\n", $type, $count);
}
echo "\nUpdated inventory written to: $output_file\n";

$mysqli->close();
