<?php
/**
 * bin/scope-betheme-config.php
 *
 * Scopes legacy BeTheme configuration, theme options, MFN Builder pages,
 * customizer CSS, and dynamic sidebars.
 * Strictly read-only execution.
 *
 * Output Files:
 * - ai-work/scopings/betheme-config-scoping.json
 * - ai-work/scopings/mfn-pages.json
 * - ai-work/scopings/betheme-custom-css.css
 */

if (!defined('ABSPATH')) {
    // Standard WP eval script context
}

echo "Starting Phase 3 BeTheme Configuration & Layout Scoping...\n";

// Base paths
$base_dir = dirname(__DIR__);
$scoping_dir = $base_dir . '/ai-work/scopings';
if (!file_exists($scoping_dir)) {
    mkdir($scoping_dir, 0755, true);
}

$config_output_file = $scoping_dir . '/betheme-config-scoping.json';
$mfn_output_file    = $scoping_dir . '/mfn-pages.json';
$css_output_file    = $scoping_dir . '/betheme-custom-css.css';

$json_flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $json_flags |= JSON_INVALID_UTF8_SUBSTITUTE;
}

// ----------------------------------------------------
// 1. Extract BeTheme Options Array
// ----------------------------------------------------
echo "Extracting BeTheme Theme Options...\n";

$betheme_options = get_option('betheme');
if (empty($betheme_options)) {
    $betheme_options = get_option('mfn_theme_options');
}
if (!is_array($betheme_options)) {
    $betheme_options = [];
}

// Registered sidebars
global $wp_registered_sidebars;
$registered_sidebars_list = [];
if (!empty($wp_registered_sidebars) && is_array($wp_registered_sidebars)) {
    foreach ($wp_registered_sidebars as $sidebar_id => $sidebar) {
        $registered_sidebars_list[$sidebar_id] = [
            'id'          => $sidebar_id,
            'name'        => isset($sidebar['name']) ? $sidebar['name'] : $sidebar_id,
            'description' => isset($sidebar['description']) ? $sidebar['description'] : '',
        ];
    }
}

// Custom theme sidebars option
$custom_sidebars_option = get_option('sidebars');
if (empty($custom_sidebars_option)) {
    $custom_sidebars_option = isset($betheme_options['sidebars']) ? $betheme_options['sidebars'] : [];
}

$config_data = [
    'header' => [
        'header_style'   => isset($betheme_options['header-style']) ? $betheme_options['header-style'] : (isset($betheme_options['header_style']) ? $betheme_options['header_style'] : ''),
        'sticky_header'  => isset($betheme_options['sticky-header']) ? $betheme_options['sticky-header'] : '',
        'top_bar'        => isset($betheme_options['top-bar']) ? $betheme_options['top-bar'] : '',
        'logo_url'       => isset($betheme_options['logo-img']) ? $betheme_options['logo-img'] : (isset($betheme_options['logo_url']) ? $betheme_options['logo_url'] : ''),
        'logo_retina'    => isset($betheme_options['logo-retina']) ? $betheme_options['logo-retina'] : '',
        'logo_width'     => isset($betheme_options['logo-width']) ? $betheme_options['logo-width'] : '',
        'logo_height'    => isset($betheme_options['logo-height']) ? $betheme_options['logo-height'] : '',
        'action_button'  => isset($betheme_options['header-action-title']) ? $betheme_options['header-action-title'] : '',
        'action_link'    => isset($betheme_options['header-action-link']) ? $betheme_options['header-action-link'] : '',
    ],
    'typography' => [
        'body_font'      => isset($betheme_options['font-custom']) ? $betheme_options['font-custom'] : (isset($betheme_options['font-family']) ? $betheme_options['font-family'] : ''),
        'headings_font'  => isset($betheme_options['font-headings']) ? $betheme_options['font-headings'] : '',
        'menu_font'      => isset($betheme_options['font-menu']) ? $betheme_options['font-menu'] : '',
        'font_size_body' => isset($betheme_options['font-size-body']) ? $betheme_options['font-size-body'] : '',
        'line_height'    => isset($betheme_options['line-height-body']) ? $betheme_options['line-height-body'] : '',
    ],
    'colors' => [
        'theme_color'        => isset($betheme_options['color-theme']) ? $betheme_options['color-theme'] : '',
        'bg_body'            => isset($betheme_options['bg-body']) ? $betheme_options['bg-body'] : '',
        'bg_header'          => isset($betheme_options['bg-header']) ? $betheme_options['bg-header'] : '',
        'bg_footer'          => isset($betheme_options['bg-footer']) ? $betheme_options['bg-footer'] : '',
        'text_color'         => isset($betheme_options['color-text']) ? $betheme_options['color-text'] : '',
        'headings_color'     => isset($betheme_options['color-headings']) ? $betheme_options['color-headings'] : '',
        'links_color'        => isset($betheme_options['color-a']) ? $betheme_options['color-a'] : '',
    ],
    'sidebars' => [
        'custom_sidebars_option' => $custom_sidebars_option,
        'registered_sidebars'    => $registered_sidebars_list,
    ],
    'grid_and_spacing' => [
        'grid_width'     => isset($betheme_options['grid-width']) ? $betheme_options['grid-width'] : '',
        'container_padding' => isset($betheme_options['container-padding']) ? $betheme_options['container-padding'] : '',
    ],
    'raw_options' => $betheme_options,
];

file_put_contents($config_output_file, json_encode($config_data, $json_flags));
echo "Saved BeTheme config scoping to: " . $config_output_file . "\n";

// ----------------------------------------------------
// 2. Catalog MFN Builder Pages
// ----------------------------------------------------
echo "Cataloging MFN Builder Pages...\n";

global $wpdb;
$mfn_posts_query = "
    SELECT p.ID as post_id, p.post_title, p.post_type, p.post_status, m.meta_key, m.meta_value 
    FROM {$wpdb->posts} p 
    INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id 
    WHERE m.meta_key IN ('mfn-page-items', '_mfn-builder-items', 'mfn_builder_items') AND p.post_status != 'trash'
    ORDER BY p.ID ASC
";

$mfn_results = $wpdb->get_results($mfn_posts_query);
$mfn_catalog = [];

foreach ($mfn_results as $row) {
    $post_id    = (int) $row->post_id;
    $raw_meta   = $row->meta_value;
    $builder_data = null;

    if (!empty($raw_meta)) {
        if (is_array($raw_meta)) {
            $builder_data = $raw_meta;
        } else {
            // Check for base64 encoding or serialized PHP string
            $decoded = @base64_decode($raw_meta, true);
            if ($decoded !== false && is_serialized($decoded)) {
                $builder_data = @maybe_unserialize($decoded);
            } else {
                $builder_data = @maybe_unserialize($raw_meta);
            }
        }
    }

    $section_count = 0;
    $wrap_count    = 0;
    $item_count    = 0;

    if (is_array($builder_data)) {
        foreach ($builder_data as $section) {
            if (is_array($section)) {
                $section_count++;
                if (isset($section['wraps']) && is_array($section['wraps'])) {
                    foreach ($section['wraps'] as $wrap) {
                        $wrap_count++;
                        if (isset($wrap['items']) && is_array($wrap['items'])) {
                            $item_count += count($wrap['items']);
                        }
                    }
                } elseif (isset($section['items']) && is_array($section['items'])) {
                    $item_count += count($section['items']);
                }
            }
        }
    }

    $mfn_catalog[] = [
        'post_id'       => $post_id,
        'title'         => $row->post_title,
        'post_type'     => $row->post_type,
        'post_status'   => $row->post_status,
        'permalink'     => get_permalink($post_id),
        'metrics'       => [
            'sections' => $section_count,
            'wraps'    => $wrap_count,
            'items'    => $item_count,
        ],
        'raw_meta_size_bytes' => strlen((string) $raw_meta),
        'builder_structure'   => is_array($builder_data) ? $builder_data : null,
    ];
}

file_put_contents($mfn_output_file, json_encode($mfn_catalog, $json_flags));
echo sprintf("Saved %d MFN Builder pages to: %s\n", count($mfn_catalog), $mfn_output_file);

// ----------------------------------------------------
// 3. Extract Custom CSS
// ----------------------------------------------------
echo "Extracting Database & Customizer Custom CSS...\n";

$css_blocks = [];

// Header comment
$css_blocks[] = "/* ==========================================================================\n   BeTheme Custom CSS Export - Generated by Phase 3 Scoping Script\n   Date: " . date('Y-m-d H:i:s') . "\n   ========================================================================== */\n";

// Theme options custom CSS
$custom_css_opt = isset($betheme_options['custom-css']) ? $betheme_options['custom-css'] : (isset($betheme_options['custom_css']) ? $betheme_options['custom_css'] : '');
if (!empty($custom_css_opt)) {
    $css_blocks[] = "/* --- BeTheme Options Custom CSS --- */\n" . trim($custom_css_opt) . "\n";
}

$custom_css_imp = isset($betheme_options['custom-css-imp']) ? $betheme_options['custom-css-imp'] : '';
if (!empty($custom_css_imp)) {
    $css_blocks[] = "/* --- BeTheme Options Custom CSS (!important) --- */\n" . trim($custom_css_imp) . "\n";
}

$mfn_custom_css = isset($betheme_options['mfn_custom_css']) ? $betheme_options['mfn_custom_css'] : '';
if (!empty($mfn_custom_css)) {
    $css_blocks[] = "/* --- BeTheme MFN Custom CSS --- */\n" . trim($mfn_custom_css) . "\n";
}

// WordPress Core Customizer CSS
if (function_exists('wp_get_custom_css')) {
    $core_custom_css = wp_get_custom_css();
    if (!empty($core_custom_css)) {
        $css_blocks[] = "/* --- WordPress Core Customizer CSS --- */\n" . trim($core_custom_css) . "\n";
    }
}

// Post specific custom CSS postmeta
$page_css_query = "
    SELECT p.ID as post_id, p.post_title, m.meta_value 
    FROM {$wpdb->posts} p 
    INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id 
    WHERE m.meta_key IN ('_mfn-post-css', '_mfn-builder-css', 'mfn-post-css') 
      AND p.post_status != 'trash'
      AND CHAR_LENGTH(m.meta_value) > 0
";
$page_css_results = $wpdb->get_results($page_css_query);
if (!empty($page_css_results)) {
    foreach ($page_css_results as $css_row) {
        $css_blocks[] = sprintf("/* --- Page Specific Custom CSS (Post #%d: %s) --- */\n%s\n", $css_row->post_id, $css_row->post_title, trim($css_row->meta_value));
    }
}

$combined_css = implode("\n\n", $css_blocks);
file_put_contents($css_output_file, $combined_css);
echo sprintf("Saved extracted custom CSS (%d bytes) to: %s\n", strlen($combined_css), $css_output_file);

echo "BeTheme Configuration Scoping Completed Successfully!\n";
