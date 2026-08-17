<?php
/**
 * bin/scope-legacy-items.php
 *
 * Scopes legacy items (LayerSlider, Revolution Slider, WPBakery grids/shortcodes,
 * Testimonials, MFN Builder items, static image sliders) across all pages and posts.
 * Exports structured JSON to ai-work/scopings/legacy-items-inventory.json.
 */

if (!defined('ABSPATH')) {
    // Standard WP eval script context
}

echo "Starting Legacy Items & Page URL Scoping...\n";

// Define base theme path and output directory
$base_dir = dirname(__DIR__);
$scoping_dir = $base_dir . '/ai-work/scopings';
if (!file_exists($scoping_dir)) {
    mkdir($scoping_dir, 0755, true);
}
$output_file = $scoping_dir . '/legacy-items-inventory.json';

// Query all pages and posts
$args = [
    'post_type'        => ['page', 'post'],
    'post_status'      => ['publish', 'draft', 'private', 'pending', 'future'],
    'posts_per_page'   => -1,
    'suppress_filters' => true,
];

$posts = get_posts($args);
echo sprintf("Found %d total pages/posts to analyze.\n", count($posts));

$inventory = [];
$stats = [
    'pages_analyzed' => count($posts),
    'items_found'    => 0,
    'by_type'        => []
];

foreach ($posts as $post) {
    $post_id   = $post->ID;
    $title     = $post->post_title;
    $post_type = $post->post_type;
    $url       = get_permalink($post_id);
    $content   = $post->post_content;

    $detected_items = [];

    // 1. LayerSlider / RevSlider shortcodes
    if (preg_match_all('/\[(layerslider|rev_slider|slider)[^\]]*\]/i', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $shortcode_name = strtolower($match[1]);
            $raw_snippet    = $match[0];
            
            $id = '';
            if (preg_match('/(?:id|alias)=["\']?([^"\']+)["\']?/i', $raw_snippet, $id_match)) {
                $id = $id_match[1];
            }

            $type = ($shortcode_name === 'rev_slider') ? 'rev_slider' : 'layerslider';
            $detected_items[] = [
                'item_type'            => $type,
                'item_identifier'      => $id ?: $shortcode_name,
                'raw_snippet'          => $raw_snippet,
                'proposed_remediation' => 'Replace with core/gallery, core/cover, or custom FSE block'
            ];
        }
    }

    // 2. WPBakery components (vc_posts_grid, vc_row, etc.)
    if (preg_match_all('/\[(vc_[a-zA-Z0-9_]+)[^\]]*\]/i', $content, $matches, PREG_SET_ORDER)) {
        $found_vc_types = [];
        foreach ($matches as $match) {
            $shortcode_name = strtolower($match[1]);
            $raw_snippet    = $match[0];

            if ($shortcode_name === 'vc_posts_grid' || $shortcode_name === 'vc_basic_grid' || $shortcode_name === 'vc_media_grid' || !isset($found_vc_types[$shortcode_name])) {
                $found_vc_types[$shortcode_name] = true;
                $detected_items[] = [
                    'item_type'            => ($shortcode_name === 'vc_posts_grid' || $shortcode_name === 'vc_basic_grid' || $shortcode_name === 'vc_media_grid') ? 'vc_posts_grid' : 'wpbakery_shortcode',
                    'item_identifier'      => $shortcode_name,
                    'raw_snippet'          => strlen($raw_snippet) > 200 ? substr($raw_snippet, 0, 200) . '...' : $raw_snippet,
                    'proposed_remediation' => ($shortcode_name === 'vc_posts_grid') ? 'Replace with core/query block' : 'Remediate WPBakery shortcode to native Gutenberg block'
                ];
            }
        }
    }

    // 3. Testimonials shortcode / listings
    if (preg_match_all('/\[(testimonials|testimonial)[^\]]*\]/i', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $raw_snippet = $match[0];
            $detected_items[] = [
                'item_type'            => 'testimonials',
                'item_identifier'      => 'testimonials_listing',
                'raw_snippet'          => $raw_snippet,
                'proposed_remediation' => 'Migrate to board_member CPT and core/query block'
            ];
        }
    }

    // 4. WordPress core gallery / static image slider shortcodes
    if (preg_match_all('/\[(gallery|slider_static)[^\]]*\]/i', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $raw_snippet = $match[0];
            $detected_items[] = [
                'item_type'            => 'static_slider',
                'item_identifier'      => $match[1],
                'raw_snippet'          => $raw_snippet,
                'proposed_remediation' => 'Replace with core/gallery block'
            ];
        }
    }

    // 5. Check postmeta for BeTheme MFN Builder components (`_mfn-builder-items`)
    $mfn_items = get_post_meta($post_id, '_mfn-builder-items', true);
    if (!empty($mfn_items)) {
        $detected_items[] = [
            'item_type'            => 'mfn_builder_item',
            'item_identifier'      => '_mfn-builder-items',
            'raw_snippet'          => sprintf('MFN Builder postmeta (size: %s)', is_array($mfn_items) ? count($mfn_items) . ' items' : strlen($mfn_items) . ' bytes'),
            'proposed_remediation' => 'Extract MFN content sections and remediate to core Gutenberg blocks'
        ];
    }

    // 6. Check postmeta for MFN post slider
    $mfn_slider = get_post_meta($post_id, 'mfn-post-slider', true);
    if (!empty($mfn_slider)) {
        $detected_items[] = [
            'item_type'            => 'mfn_header_slider',
            'item_identifier'      => (string)$mfn_slider,
            'raw_snippet'          => sprintf('mfn-post-slider postmeta: %s', $mfn_slider),
            'proposed_remediation' => 'Replace theme header slider with FSE template cover/header block'
        ];
    }

    // Append to inventory if legacy items found
    foreach ($detected_items as $item) {
        $inventory[] = [
            'page_id'              => $post_id,
            'page_title'           => $title,
            'page_url'             => $url,
            'post_type'            => $post_type,
            'item_type'            => $item['item_type'],
            'item_identifier'      => $item['item_identifier'],
            'raw_snippet'          => $item['raw_snippet'],
            'proposed_remediation' => $item['proposed_remediation'],
        ];

        $stats['items_found']++;
        if (!isset($stats['by_type'][$item['item_type']])) {
            $stats['by_type'][$item['item_type']] = 0;
        }
        $stats['by_type'][$item['item_type']]++;
    }
}

// Write JSON output
$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
}
$json_content = json_encode($inventory, $flags);
if ($json_content === false) {
    echo "ERROR: json_encode failed: " . json_last_error_msg() . "\n";
} else {
    file_put_contents($output_file, $json_content);
}

echo sprintf("Scoping complete!\n");
echo sprintf("Extracted %d legacy items across analyzed pages.\n", $stats['items_found']);
echo "Breakdown by item type:\n";
foreach ($stats['by_type'] as $type => $count) {
    echo sprintf(" - %s: %d\n", $type, $count);
}
echo sprintf("Saved inventory to: %s\n", $output_file);
