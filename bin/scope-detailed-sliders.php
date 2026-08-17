<?php
/**
 * bin/scope-detailed-sliders.php
 *
 * Deep scoping script to extract Revolution Slider aliases, LayerSlider IDs,
 * page permalinks, and associated media attachment IDs / URLs across all WordPress posts & pages.
 * Cross-checks with existing media library assets for direct assignment.
 * Exports output to ai-work/scopings/rev-sliders-scoping.json.
 */

if (!defined('ABSPATH')) {
    // WP eval context
}

echo "Starting Detailed Revolution & LayerSlider Scoping...\n";

$base_dir = dirname(__DIR__);
$scoping_dir = $base_dir . '/ai-work/scopings';
if (!file_exists($scoping_dir)) {
    mkdir($scoping_dir, 0755, true);
}
$output_file = $scoping_dir . '/rev-sliders-scoping.json';

global $wpdb;

// 1. Query all posts and pages
$args = [
    'post_type'        => ['page', 'post', 'testimonial', 'board_member', 'alx_tachydromos'],
    'post_status'      => ['publish', 'draft', 'private', 'pending', 'future'],
    'posts_per_page'   => -1,
    'suppress_filters' => true,
];

$posts = get_posts($args);
echo sprintf("Analyzing %d posts/pages for slider aliases and attached media...\n", count($posts));

$slider_inventory = [];
$stats = [
    'rev_sliders_found'   => 0,
    'layer_sliders_found' => 0,
    'pages_with_sliders'  => 0,
];

foreach ($posts as $post) {
    $post_id   = $post->ID;
    $title     = $post->post_title;
    $post_type = $post->post_type;
    $url       = get_permalink($post_id);
    $content   = $post->post_content;

    $rev_matches   = [];
    $layer_matches = [];

    // Scan for rev_slider / rev_slider_vc shortcodes
    if (preg_match_all('/\[(rev_slider|rev_slider_vc)[^\]]*\]/i', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $raw = $match[0];
            $alias = '';
            if (preg_match('/(?:alias|title)=["\']?([^"\']+)["\']?/i', $raw, $amatch)) {
                $alias = $amatch[1];
            } elseif (preg_match('/\[rev_slider\s+([a-zA-Z0-9_\-]+)\]/i', $raw, $amatch)) {
                $alias = $amatch[1];
            }

            $rev_matches[] = [
                'raw_shortcode' => $raw,
                'alias'         => $alias ?: 'unknown',
            ];
            $stats['rev_sliders_found']++;
        }
    }

    // Scan for layerslider shortcodes
    if (preg_match_all('/\[layerslider[^\]]*\]/i', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $raw = $match[0];
            $slider_id = '';
            if (preg_match('/id=["\']?([^"\']+)["\']?/i', $raw, $imatch)) {
                $slider_id = $imatch[1];
            }

            $layer_matches[] = [
                'raw_shortcode' => $raw,
                'slider_id'     => $slider_id ?: 'unknown',
            ];
            $stats['layer_sliders_found']++;
        }
    }

    if (!empty($rev_matches) || !empty($layer_matches)) {
        $stats['pages_with_sliders']++;

        // Extract attached media IDs and URLs for this page
        $attachments = get_attached_media('image/', $post_id);
        $media_list = [];
        foreach ($attachments as $att) {
            $att_url = wp_get_attachment_url($att->ID);
            $media_list[] = [
                'attachment_id' => $att->ID,
                'url'           => $att_url,
                'title'         => $att->post_title,
                'filename'      => wp_basename($att_url),
            ];
        }

        // Also check if content has embedded image URLs or gallery IDs
        $gallery_ids = [];
        if (preg_match('/\[gallery[^\]]*ids=["\']?([0-9,]+)["\']?/i', $content, $gmatch)) {
            $gallery_ids = array_map('intval', explode(',', $gmatch[1]));
        }

        $embedded_images = [];
        if (preg_match_all('/src=["\']([^"\']+\.(?:jpg|jpeg|png|gif|webp))["\']/i', $content, $img_matches)) {
            foreach ($img_matches[1] as $img_src) {
                $clean_src = strtok($img_src, '?');
                $filename  = wp_basename($clean_src);
                $unscaled  = preg_replace('/-\d+x\d+(\.[a-zA-Z0-9]+)$/', '$1', $filename);
                
                // Lookup attachment ID in local WP database
                $existing_att_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_wp_attached_file' AND meta_value LIKE %s", '%' . $unscaled));

                $embedded_images[] = [
                    'src_url'         => $clean_src,
                    'filename'        => $filename,
                    'unscaled'        => $unscaled,
                    'db_attachment_id'=> $existing_att_id ? (int)$existing_att_id : null,
                ];
            }
        }

        $slider_inventory[] = [
            'page_id'           => $post_id,
            'page_title'        => $title,
            'page_url'          => $url,
            'post_type'         => $post_type,
            'rev_sliders'       => $rev_matches,
            'layer_sliders'     => $layer_matches,
            'attached_media'    => $media_list,
            'gallery_image_ids' => $gallery_ids,
            'embedded_images'   => $embedded_images,
        ];
    }
}

// Write JSON output
$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
}

$json_content = json_encode($slider_inventory, $flags);
if ($json_content === false) {
    echo "ERROR: json_encode failed: " . json_last_error_msg() . "\n";
} else {
    file_put_contents($output_file, $json_content);
}

echo "Detailed Slider Scoping Complete!\n";
echo sprintf("Pages with Sliders: %d\n", $stats['pages_with_sliders']);
echo sprintf("RevSliders Found: %d\n", $stats['rev_sliders_found']);
echo sprintf("LayerSliders Found: %d\n", $stats['layer_sliders_found']);
echo sprintf("Saved detailed scoping output to: %s\n", $output_file);
