<?php
/**
 * bin/migrate-classic-menus-to-fse.php
 * Converts Classic Nav Menus into Gutenberg FSE `wp_navigation` posts based on `ai-work/scoping/menus.json`.
 * Configures Polylang translations, menu locations, generates Top Bar Polylang Language Switcher,
 * and programmatically updates the generated Greek FSE navigation post IDs into `parts/header.html` and `parts/footer.html`.
 *
 * @package EKA_Alexandria_Flagship
 */

if (!defined('ABSPATH') && !defined('WP_CLI')) {
    echo "This script must be run within WordPress execution context.\n";
    exit(1);
}

require_once __DIR__ . '/migration-helpers.php';

echo "========================================\n";
echo "Migrating & Assigning Classic Menus to FSE wp_navigation\n";
echo "========================================\n";

$json_path = dirname(__DIR__) . '/ai-work/scoping/menus.json';
if (!file_exists($json_path)) {
    echo "ERROR: menus.json not found at $json_path\n";
    exit(1);
}

$config = json_decode(file_get_contents($json_path), true);
if (!$config || !isset($config['menu_groups'])) {
    echo "ERROR: Invalid JSON structure in menus.json\n";
    exit(1);
}

/**
 * 1. Classic Nav Menu Term Assignments & Polylang Linking
 */
$classic_menu_mappings = [
    'main' => [
        'el' => 13,   // Main Greek Menu
        'en' => 3315, // Main English Menu
        'ar' => 3316, // Main Arabic Menu
    ],
    'establishment' => [
        'el' => 70,   // Establishment Greek Menu
        'en' => 3377, // Establishment English Menu
        'ar' => 3378, // Establishment Arabic Menu
    ],
    'activity' => [
        'el' => 71,   // Activity Greek Menu
        'en' => 3944, // Activity English Menu
        'ar' => 3945, // Activity Arabic Menu
    ],
    'services' => [
        'el' => 117,  // Service Greek Menu
        'en' => 3707, // Services English Menu
        'ar' => 3716, // Services Arabic Menu
    ],
];

foreach ($classic_menu_mappings as $group_name => $langs) {
    $valid_translations = [];
    foreach ($langs as $lang => $term_id) {
        $term = get_term($term_id, 'nav_menu');
        if ($term && !is_wp_error($term)) {
            if (function_exists('pll_set_term_language')) {
                pll_set_term_language($term_id, $lang);
            }
            $valid_translations[$lang] = $term_id;
            echo "Assigned language '$lang' to nav_menu term ID $term_id ('{$term->name}')\n";
        }
    }

    if (!empty($valid_translations) && function_exists('pll_save_term_translations')) {
        pll_save_term_translations($valid_translations);
        echo "Saved Polylang term translations for group '$group_name': " . json_encode($valid_translations) . "\n";
    }
}

// 2. Set Theme Mod Nav Menu Locations
$locations = get_theme_mod('nav_menu_locations', []);
$locations['main-menu']          = 13;   // Main Greek Menu
$locations['main-menu___en']     = 3315; // Main English Menu
$locations['main-menu___ar']     = 3316; // Main Arabic Menu
$locations['footer-menu']        = 21;   // Footer Greek Menu
$locations['social-menu-bottom'] = 21;   // Footer / Social Menu

set_theme_mod('nav_menu_locations', $locations);
echo "Updated theme_mod nav_menu_locations: " . json_encode($locations) . "\n";

use EkaAlexandria\Migration\Navigation\MenuMigrator;

function eka_build_nav_blocks_markup(array $items, int $parent_id = 0, string $lang = 'el'): string
{
    $migrator = new MenuMigrator();
    return $migrator->buildNavBlocksMarkup($items, $parent_id, $lang);
}

$generated_greek_fse_ids = [
    'main_header' => 0,
    'top_bar'     => 0,
    'footer_menu' => 0,
];

foreach ($config['menu_groups'] as $group_key => $group_data) {
    $includes_language_switcher = !empty($group_data['includes_language_switcher']);
    $single_shared_menu         = !empty($group_data['single_shared_menu']);
    $translations               = $group_data['translations'] ?? [];

    if ($single_shared_menu) {
        $title = $group_data['title'] ?? "Top Bar Language Switcher";
        $block_content = "<!-- wp:polylang/navigation-language-switcher /-->\n";

        $existing = get_page_by_title($title, OBJECT, 'wp_navigation');
        $post_data = [
            'post_title'   => $title,
            'post_content' => trim($block_content),
            'post_status'  => 'publish',
            'post_type'    => 'wp_navigation',
        ];

        if ($existing) {
            $post_data['ID'] = $existing->ID;
            wp_update_post($post_data);
            $fse_id = $existing->ID;
            echo "Updated existing single shared wp_navigation post ID $fse_id ('$title')\n";
        } else {
            $fse_id = wp_insert_post($post_data);
            echo "Created new single shared wp_navigation post ID $fse_id ('$title')\n";
        }

        if ($fse_id && !is_wp_error($fse_id)) {
            $generated_greek_fse_ids[$group_key] = $fse_id;
        }
        continue;
    }

    $group_fse_posts = [];
    $fallback_classic_id = 0;

    foreach ($translations as $t_info) {
        if (!empty($t_info['classic_menu_id'])) {
            $fallback_classic_id = (int)$t_info['classic_menu_id'];
            break;
        }
    }

    foreach ($translations as $lang => $trans_info) {
        $classic_id    = $trans_info['classic_menu_id'] ?? $fallback_classic_id;
        $title         = $trans_info['title'] ?? "Navigation Menu ({$lang})";
        $block_content = '';

        if ($classic_id > 0) {
            $items = wp_get_nav_menu_items($classic_id);
            if ($items && !is_wp_error($items)) {
                $block_content = eka_build_nav_blocks_markup($items, 0, $lang);
            }
        }

        if ($includes_language_switcher) {
            $block_content .= "<!-- wp:polylang/navigation-language-switcher /-->\n";
        }

        $existing = get_page_by_title($title, OBJECT, 'wp_navigation');
        $post_data = [
            'post_title'   => $title,
            'post_content' => trim($block_content),
            'post_status'  => 'publish',
            'post_type'    => 'wp_navigation',
        ];

        if ($existing) {
            $post_data['ID'] = $existing->ID;
            wp_update_post($post_data);
            $fse_id = $existing->ID;
            echo "Updated existing wp_navigation post ID $fse_id ('$title') for lang '$lang'\n";
        } else {
            $fse_id = wp_insert_post($post_data);
            echo "Created new wp_navigation post ID $fse_id ('$title') for lang '$lang'\n";
        }

        if ($fse_id && !is_wp_error($fse_id)) {
            if (function_exists('pll_set_post_language')) {
                pll_set_post_language($fse_id, $lang);
            }
            $group_fse_posts[$lang] = $fse_id;
            if ($lang === 'el') {
                $generated_greek_fse_ids[$group_key] = $fse_id;
            }
        }
    }

    // Save Polylang post translations for this group
    if (!empty($group_fse_posts) && function_exists('pll_save_post_translations')) {
        pll_save_post_translations($group_fse_posts);
        echo "Saved Polylang post translations for group '$group_key': " . json_encode($group_fse_posts) . "\n";
    }
}

/**
 * 4. Update canonical parts/header.html and parts/footer.html with actual generated Greek wp_navigation post IDs.
 */
function eka_sync_template_part_navigation_refs(array $generated_ids)
{
    $parts_dir = dirname(__DIR__) . '/parts';
    if (!is_dir($parts_dir)) {
        return;
    }

    $top_bar_ref     = $generated_ids['top_bar'] ?? 0;
    $main_header_ref = $generated_ids['main_header'] ?? 0;
    $footer_ref      = $generated_ids['footer_menu'] ?? 0;

    // Header file update
    $header_path = $parts_dir . '/header.html';
    if (file_exists($header_path) && $top_bar_ref > 0 && $main_header_ref > 0) {
        $content = file_get_contents($header_path);
        // Top bar block replacement
        $content = preg_replace(
            '/<!-- wp:navigation \{"ref":\d+.*?\} \/-->/',
            '<!-- wp:navigation {"ref":' . $top_bar_ref . '} /-->',
            $content,
            1
        );
        // Main header navigation replacement
        $content = preg_replace(
            '/<!-- wp:navigation \{"ref":\d+,"layout":\{"type":"flex","justifyContent":"right"\}\} \/-->/',
            '<!-- wp:navigation {"ref":' . $main_header_ref . ',"layout":{"type":"flex","justifyContent":"right"}} /-->',
            $content,
            1
        );
        file_put_contents($header_path, $content);
        echo "Synchronized generated Greek FSE navigation refs (top_bar: $top_bar_ref, main_header: $main_header_ref) in header.html\n";
    }

    // Footer file update
    $footer_path = $parts_dir . '/footer.html';
    if (file_exists($footer_path) && $footer_ref > 0) {
        $content = file_get_contents($footer_path);
        $content = preg_replace(
            '/<!-- wp:navigation \{(?:"menuSlug":"[^"]*"|"ref":\d+).*?\} \/-->/',
            '<!-- wp:navigation {"ref":' . $footer_ref . ',"overlayMenu":"never","layout":{"type":"flex","justifyContent":"right"}} /-->',
            $content,
            1
        );
        file_put_contents($footer_path, $content);
        echo "Synchronized generated Greek FSE navigation ref (footer: $footer_ref) in footer.html\n";
    }
}

eka_sync_template_part_navigation_refs($generated_greek_fse_ids);

echo "Classic to FSE Menu migration & assignment completed successfully!\n";
