#!/bin/bash
# bin/run-scopings.sh

echo "Running scoping scripts..."

mkdir -p ai-work/scopings

cat << 'EOF' > ai-work/scratch/dump-scopings.php
<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

// 1. Extract BeTheme Options
$betheme_options = get_option('betheme');
$betheme_scoping = [];
if (is_array($betheme_options)) {
    foreach ($betheme_options as $key => $val) {
        if ((strpos($key, 'grid') !== false || strpos($key, 'layout') !== false || strpos($key, 'spacing') !== false) && !empty($val)) {
            $betheme_scoping[$key] = $val;
        }
    }
}
file_put_contents(dirname(__FILE__) . '/../scopings/betheme-options.json', json_encode($betheme_scoping, JSON_PRETTY_PRINT));

// 2. Extract Legacy IDs (Sliders, WPBakery)
$legacy_ids = [
    'rev_sliders' => [],
    'wpbakery' => [],
    'pages' => []
];

// Sliders
$query_rev = new WP_Query([ 'post_type' => 'any', 'posts_per_page' => -1, 's' => '[rev_slider' ]);
if ($query_rev->have_posts()) {
    while ($query_rev->have_posts()) {
        $query_rev->the_post();
        $legacy_ids['rev_sliders'][] = get_the_ID();
    }
    wp_reset_postdata();
}

// WPBakery
$query_vc = new WP_Query([ 'post_type' => 'any', 'posts_per_page' => -1, 's' => '[vc_' ]);
if ($query_vc->have_posts()) {
    while ($query_vc->have_posts()) {
        $query_vc->the_post();
        $legacy_ids['wpbakery'][] = get_the_ID();
    }
    wp_reset_postdata();
}

// Pages
$pages = get_pages();
foreach ($pages as $p) {
    $legacy_ids['pages'][] = [
        'id' => $p->ID,
        'title' => $p->post_title,
        'slug' => $p->post_name,
        'template' => get_page_template_slug($p->ID)
    ];
}

file_put_contents(dirname(__FILE__) . '/../scopings/legacy-ids.json', json_encode($legacy_ids, JSON_PRETTY_PRINT));

echo "Successfully dumped betheme-options.json and legacy-ids.json\n";
EOF

php7.4 $(which wp) eval-file ai-work/scratch/dump-scopings.php --allow-root --skip-plugins || exit 1

echo "Scoping complete!"
exit 0
