<?php

/**
 * bin/assign-page-templates.php
 * Assigns FSE Page Templates (front-page, index, page) to target home, news, and translated pages.
 * Configures navigation menu locations and Polylang menu translation associations.
 * Performs transient and object cache invalidations to ensure clean FSE rendering.
 *
 * @package EKA_Alexandria_Flagship
 */

global $wpdb;

function eka_flush_all_caches()
{
    $stylesheet = get_stylesheet();
    delete_transient('wp_theme_files_' . $stylesheet);
    delete_transient('wp_theme_files_ekalexandria-flagship');
    delete_transient('pll_languages');

    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }

    if (function_exists('PLL') && isset(PLL()->model) && method_exists(PLL()->model, 'clean_language_cache')) {
        PLL()->model->clean_language_cache();
    }

    echo "Cleared theme transients and flushed object cache.\n";
}

// 1. Initial Cache Flush
eka_flush_all_caches();

// 2. Homepage publishing & template assignments
$en_home_id = 16894;
$ar_home_id = 16892;
$greek_homepage_id = 13236;

// Publish English and Arabic homepage pages before assignment
foreach ([$en_home_id, $ar_home_id, $greek_homepage_id] as $home_id) {
    $post_obj = get_post($home_id);
    if ($post_obj) {
        if ($post_obj->post_status !== 'publish') {
            wp_update_post([
                'ID'          => $home_id,
                'post_status' => 'publish',
            ]);
            echo "Published homepage (ID: $home_id, Title: '{$post_obj->post_title}')\n";
        }
        update_post_meta($home_id, '_wp_page_template', 'front-page');
        echo "Assigned template 'front-page' to Homepage (ID: $home_id, Title: '{$post_obj->post_title}')\n";
    }
}

$home_ids = array_filter([$greek_homepage_id, (int)$en_home_id, (int)$ar_home_id]);

// 3. News/posts page template assignments (Explicit IDs: 18, 16920, 16923 -> index)
$index_mappings = [
    18    => 'index',
    16920 => 'index',
    16923 => 'index'
];

foreach ($index_mappings as $idx_id => $tmpl_slug) {
    if (get_post($idx_id)) {
        update_post_meta($idx_id, '_wp_page_template', $tmpl_slug);
        echo "Assigned template '$tmpl_slug' to Posts Index page (ID: $idx_id)\n";
    }
}

// 4. Multilingual template assignments for standard pages (use canonical 'page' template)
if (function_exists('pll_get_post_language')) {
    $all_pages = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish'");
    $posts_page_ids = array_keys($index_mappings);
    foreach ($all_pages as $page) {
        $pid = (int)$page->ID;
        if (in_array($pid, $home_ids, true) || in_array($pid, $posts_page_ids, true)) {
            continue;
        }

        $existing_tmpl = get_post_meta($pid, '_wp_page_template', true);

        // Keep specific custom templates if set to tachydromos, board-members, news, etc.
        if (!empty($existing_tmpl) && in_array($existing_tmpl, ['tachydromos', 'board-members', 'news'], true)) {
            continue;
        }

        if ($existing_tmpl !== 'page' && $existing_tmpl !== 'default' && !empty($existing_tmpl)) {
            update_post_meta($pid, '_wp_page_template', 'page');
            echo "Assigned template 'page' to page ID: $pid ('{$page->post_title}')\n";
        }
    }
}

// 5. Final Cache Flush & Invalidation
eka_flush_all_caches();
