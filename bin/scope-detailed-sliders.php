<?php
/**
 * bin/scope-detailed-sliders.php
 *
 * Deep scoping script to extract LayerSlider database slides, static slider galleries,
 * image attachment IDs, original image URLs, and post/page permalinks.
 * Exports output to ai-work/scopings/detailed-sliders-inventory.json.
 */

if (!defined('ABSPATH')) {
    // WP eval context
}

echo "Starting Enhanced Detailed LayerSlider & Static Slider Scoping...\n";

$base_dir = dirname(__DIR__);
$scoping_dir = $base_dir . '/ai-work/scopings';
if (!file_exists($scoping_dir)) {
    mkdir($scoping_dir, 0755, true);
}
$output_file = $scoping_dir . '/detailed-sliders-inventory.json';

global $wpdb;

// ----------------------------------------------------
// 1. Extract LayerSliders from wp_layerslider DB table
// ----------------------------------------------------
echo "Extracting LayerSliders from wp_layerslider database table...\n";
$layerslider_table = $wpdb->prefix . 'layerslider';
$db_sliders = [];

if ($wpdb->get_var("SHOW TABLES LIKE '$layerslider_table'") === $layerslider_table) {
    $rows = $wpdb->get_results("SELECT id, name, slug, data FROM {$layerslider_table} WHERE flag_deleted = 0 ORDER BY id ASC");
    foreach ($rows as $row) {
        $slider_id = (int) $row->id;
        $raw_data  = $row->data;
        $decoded   = null;

        if (!empty($raw_data)) {
            if (is_serialized($raw_data)) {
                $decoded = @maybe_unserialize($raw_data);
            } else {
                $decoded = json_decode($raw_data, true);
                if (empty($decoded) && is_string($raw_data)) {
                    $decoded = @maybe_unserialize($raw_data);
                }
            }
        }

        $slides_info = [];
        if (is_array($decoded)) {
            // LayerSlider data structure: array of layers/slides
            $layers = isset($decoded['layers']) ? $decoded['layers'] : (isset($decoded['slides']) ? $decoded['slides'] : $decoded);
            if (is_array($layers)) {
                foreach ($layers as $idx => $slide) {
                    if (!is_array($slide)) continue;

                    $slide_bg_img = isset($slide['properties']['background']) ? $slide['properties']['background'] : (isset($slide['background']) ? $slide['background'] : '');
                    $slide_bg_id  = isset($slide['properties']['backgroundId']) ? (int)$slide['properties']['backgroundId'] : 0;

                    $sublayer_imgs = [];
                    $sublayers = isset($slide['sublayers']) ? $slide['sublayers'] : (isset($slide['items']) ? $slide['items'] : []);
                    if (is_array($sublayers)) {
                        foreach ($sublayers as $sub) {
                            if (!is_array($sub)) continue;
                            $image_src = isset($sub['image']) ? $sub['image'] : (isset($sub['src']) ? $sub['src'] : '');
                            $image_id  = isset($sub['imageId']) ? (int)$sub['imageId'] : 0;
                            if ($image_src || $image_id) {
                                $sublayer_imgs[] = [
                                    'image_url'     => $image_src,
                                    'attachment_id' => $image_id ?: null,
                                ];
                            }
                        }
                    }

                    $slides_info[] = [
                        'slide_index'         => $idx + 1,
                        'background_image_url'=> $slide_bg_img,
                        'background_att_id'   => $slide_bg_id ?: null,
                        'sublayer_images'     => $sublayer_imgs,
                    ];
                }
            }
        }

        $db_sliders[$slider_id] = [
            'slider_id'  => $slider_id,
            'name'       => $row->name,
            'slug'       => $row->slug,
            'slide_count'=> count($slides_info),
            'slides'     => $slides_info,
            'raw_data'   => $decoded,
        ];
    }
}
echo sprintf("Found %d total LayerSliders in database.\n", count($db_sliders));

// ----------------------------------------------------
// 2. Query all posts/pages for LayerSliders & Static Sliders
// ----------------------------------------------------
$args = [
    'post_type'        => ['page', 'post'],
    'post_status'      => ['publish', 'draft', 'private', 'pending', 'future'],
    'posts_per_page'   => -1,
    'suppress_filters' => true,
];

$posts = get_posts($args);
echo sprintf("Scanning %d posts/pages for slider shortcodes...\n", count($posts));

$layerslider_usages = [];
$static_slider_usages = [];

foreach ($posts as $post) {
    $post_id   = $post->ID;
    $title     = $post->post_title;
    $post_type = $post->post_type;
    $url       = get_permalink($post_id);
    $content   = $post->post_content;
    $is_front  = ((int)get_option('page_on_front') === $post_id);

    // Scan for LayerSliders
    if (preg_match_all('/\[layerslider[^\]]*\]/i', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $raw = $match[0];
            $slider_id = 0;
            if (preg_match('/id=["\']?([0-9]+)["\']?/i', $raw, $imatch)) {
                $slider_id = (int)$imatch[1];
            }

            $slider_details = isset($db_sliders[$slider_id]) ? $db_sliders[$slider_id] : null;

            $layerslider_usages[] = [
                'page_id'         => $post_id,
                'page_title'      => $title,
                'page_url'        => $url,
                'post_type'       => $post_type,
                'is_homepage'     => $is_front,
                'layerslider_id'  => $slider_id,
                'raw_shortcode'   => $raw,
                'slider_details'  => $slider_details,
            ];
        }
    }

    // Scan for Static Gallery Sliders ([gallery type="slideshow" ...] or [gallery ids="..."])
    if (preg_match_all('/\[gallery[^\]]*\]/i', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $raw = $match[0];
            $gallery_ids = [];
            if (preg_match('/ids=["\']?([0-9,]+)["\']?/i', $raw, $gmatch)) {
                $gallery_ids = array_map('intval', explode(',', $gmatch[1]));
            }

            $is_slideshow = (strpos($raw, 'slideshow') !== false);

            $images = [];
            foreach ($gallery_ids as $att_id) {
                $att_url = wp_get_attachment_url($att_id);
                $att_post = get_post($att_id);
                $images[] = [
                    'attachment_id' => $att_id,
                    'url'           => $att_url,
                    'filename'      => wp_basename($att_url),
                    'title'         => $att_post ? $att_post->post_title : '',
                    'caption'       => $att_post ? $att_post->post_excerpt : '',
                ];
            }

            $static_slider_usages[] = [
                'page_id'        => $post_id,
                'page_title'     => $title,
                'page_url'       => $url,
                'post_type'      => $post_type,
                'is_homepage'    => $is_front,
                'raw_shortcode'  => $raw,
                'is_slideshow'   => $is_slideshow,
                'image_count'    => count($images),
                'images'         => $images,
            ];
        }
    }
}

$inventory = [
    'summary' => [
        'total_db_layersliders'    => count($db_sliders),
        'layerslider_occurrences'  => count($layerslider_usages),
        'static_slider_occurrences'=> count($static_slider_usages),
    ],
    'all_database_layersliders'   => array_values($db_sliders),
    'layerslider_page_usages'     => $layerslider_usages,
    'static_slider_page_usages'   => $static_slider_usages,
];

// Write JSON output
$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
}

$json_content = json_encode($inventory, $flags);
file_put_contents($output_file, $json_content);

echo "Detailed Slider Scoping Completed Successfully!\n";
echo sprintf("Total Database LayerSliders: %d\n", count($db_sliders));
echo sprintf("LayerSlider Page Embeds: %d\n", count($layerslider_usages));
echo sprintf("Static Gallery Sliders: %d\n", count($static_slider_usages));
echo sprintf("Saved detailed inventory to: %s\n", $output_file);
