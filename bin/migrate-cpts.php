<?php
/**
 * bin/migrate-cpts.php
 * CPT Migration Script: Tachydromos PDFs & Board Member Testimonials
 * Executable in WP-CLI context: php7.4 $(which wp) eval-file bin/migrate-cpts.php --allow-root
 */

if (!defined('ABSPATH')) {
    if (defined('WP_CLI') && WP_CLI) {
        // Already in WP-CLI eval environment
    } else {
        die("Must be run within WordPress environment or via WP-CLI eval-file.\n");
    }
}

require_once __DIR__ . '/migration-helpers.php';

function eka_cpt_logger($log_filename) {
    $log_dir = get_template_directory() . '/ai-work/logs';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $log_file = $log_dir . '/' . $log_filename;
    return function($message, $type = 'INFO', $reasoning = '') use ($log_file) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = sprintf("[%s] [%s] %s %s\n", $timestamp, $type, $message, $reasoning ? "Reasoning: $reasoning" : '');
        file_put_contents($log_file, $log_entry, FILE_APPEND);
        if (class_exists('WP_CLI')) {
            if ($type === 'ERROR') {
                WP_CLI::warning("ERROR: " . $message);
            } elseif ($type === 'WARNING') {
                WP_CLI::warning($message);
            } else {
                WP_CLI::line($message);
            }
        } else {
            echo $log_entry;
        }
    };
}

$log = eka_cpt_logger('cpt-migration.log');
$log("==========================================", "INFO");
$log("Starting CPT Migration Pipeline: " . date('Y-m-d H:i:s'), "INFO");
$log("==========================================", "INFO");

// ----------------------------------------------------------------------
// 1. Migrate Alexandrinos Tachydromos PDFs
// ----------------------------------------------------------------------
function eka_migrate_tachydromos_cpt($log) {
    $log("Starting Alexandrinos Tachydromos migration...", "INFO");

    $json_file = get_template_directory() . '/ai-work/scopings/tachydromos-scoping.json';
    if (!file_exists($json_file)) {
        $log("Scoping file not found at: $json_file", "WARNING", "Skipping Tachydromos migration.");
        return;
    }

    $items = json_decode(file_get_contents($json_file), true);
    if (!$items || !is_array($items)) {
        $log("Invalid JSON scoping data in tachydromos-scoping.json.", "WARNING");
        return;
    }

    $log("Loaded " . count($items) . " items from tachydromos-scoping.json.");

    $months = [
        'Ιανουάριος' => '01', 'Ιανουαρίου' => '01',
        'Φεβρουάριος' => '02', 'Φεβρουαρίου' => '02',
        'Μάρτιος' => '03', 'Μαρτίου' => '03',
        'Απρίλιος' => '04', 'Απριλίου' => '04', 'ΑΠΡΛΙΟΣ' => '04',
        'Μάιος' => '05', 'Μαΐου' => '05',
        'Ιούνιος' => '06', 'Ιουνίου' => '06',
        'Ιούλιος' => '07', 'Ιουλίου' => '07',
        'Αύγουστος' => '08', 'Αυγούστου' => '08',
        'Σεπτέμβριος' => '09', 'Σεπτεμβρίου' => '09',
        'Οκτώβριος' => '10', 'Οκτωβρίου' => '10',
        'Νοέμβριος' => '11', 'Νοεμβρίου' => '11',
        'Δεκέμβριος' => '12', 'Δεκεμβρίου' => '12',
    ];

    $month_title_casing = [
        '01' => 'Ιανουάριος',
        '02' => 'Φεβρουάριος',
        '03' => 'Μάρτιος',
        '04' => 'Απρίλιος',
        '05' => 'Μάιος',
        '06' => 'Ιούνιος',
        '07' => 'Ιούλιος',
        '08' => 'Αύγουστος',
        '09' => 'Σεπτέμβριος',
        '10' => 'Οκτώβριος',
        '11' => 'Νοέμβριος',
        '12' => 'Δεκέμβριος',
    ];

    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    global $wpdb;

    $migrated_count = 0;
    $skipped_count = 0;

    foreach ($items as $item) {
        $pdf_url = $item['pdf_url'] ?? null;
        $img_url = $item['img_url'] ?? null;
        $unscaled_img_url = $item['unscaled_img_url'] ?? null;
        $raw_title = $item['extracted_title'] ?? null;

        if (!$pdf_url && !$raw_title) {
            continue;
        }

        $title = $raw_title ? trim(strip_tags(html_entity_decode($raw_title, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) : '';
        $title = preg_replace('/\s+/', ' ', $title);

        if (!$title && $pdf_url) {
            $title = wp_basename($pdf_url, '.pdf');
        }

        $pdf_filename = $pdf_url ? wp_basename($pdf_url) : ($img_url ? wp_basename($img_url) : 'item-' . time());

        $existing = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_eka_pdf_filename' AND meta_value=%s", $pdf_filename));
        if ($existing) {
            $skipped_count++;
            continue;
        }

        $year = date('Y');
        if (preg_match('/(20\d{2})/', $title . ' ' . $pdf_url, $m)) {
            $year = $m[1];
        }
        $month_num = '01';
        foreach ($months as $m_name => $m_num) {
            if (mb_stripos($title, $m_name) !== false) {
                $month_num = $m_num;
                break;
            }
        }
        $post_date = sprintf('%04d-%02d-01 00:00:00', $year, $month_num);

        if (isset($month_title_casing[$month_num]) && preg_match('/^[A-Z\x{0370}-\x{03FF}\s\d]+$/u', $title)) {
            $title = $month_title_casing[$month_num] . ' ' . $year;
        }

        $pdf_attachment_id = 0;
        $pdf_attachment_url = $pdf_url;

        if ($pdf_url && strtolower(pathinfo($pdf_url, PATHINFO_EXTENSION)) === 'pdf') {
            $existing_att = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_wp_attached_file' AND meta_value LIKE %s", '%' . $pdf_filename));
            if ($existing_att) {
                $pdf_attachment_id = $existing_att;
                $pdf_attachment_url = wp_get_attachment_url($pdf_attachment_id);
            } else {
                $tmp_pdf = download_url($pdf_url);
                if (!is_wp_error($tmp_pdf)) {
                    $pdf_file_array = ['name' => $pdf_filename, 'tmp_name' => $tmp_pdf];
                    $pdf_attachment_id = media_handle_sideload($pdf_file_array, 0);
                    if (!is_wp_error($pdf_attachment_id)) {
                        $pdf_attachment_url = wp_get_attachment_url($pdf_attachment_id);
                    } else {
                        @unlink($tmp_pdf);
                        $pdf_attachment_id = 0;
                    }
                }
            }
        }

        if ($pdf_attachment_id && $pdf_attachment_url) {
            $block_content = sprintf(
                '<!-- wp:file {"id":%d,"href":"%s","displayPreview":true} -->
<div class="wp-block-file"><object class="wp-block-file__embed" data="%s" type="application/pdf" style="width:100%%;height:600px" aria-label="Embed of %s"></object><a href="%s">%s</a><a href="%s" class="wp-block-file__button wp-element-button" download aria-label="Λήψη %s">Λήψη</a></div>
<!-- /wp:file -->',
                $pdf_attachment_id, esc_url($pdf_attachment_url), esc_url($pdf_attachment_url),
                esc_attr($title), esc_url($pdf_attachment_url), esc_html($title),
                esc_url($pdf_attachment_url), esc_attr($title)
            );
        } else {
            $block_content = sprintf(
                '<!-- wp:paragraph --><p><a href="%s" target="_blank">%s</a></p><!-- /wp:paragraph -->',
                esc_url($pdf_url ?: $img_url), esc_html($title)
            );
        }

        $post_id = wp_insert_post([
            'post_title' => $title ?: 'Tachydromos',
            'post_content' => $block_content,
            'post_status' => 'publish',
            'post_type' => 'alx_tachydromos',
            'post_date' => $post_date,
        ]);

        if (is_wp_error($post_id)) {
            $log("Failed to insert Tachydromos post: $title", "WARNING", $post_id->get_error_message());
            continue;
        }

        update_post_meta($post_id, '_eka_pdf_filename', $pdf_filename);
        if ($pdf_attachment_id) {
            update_post_meta($post_id, '_eka_pdf_attachment_id', $pdf_attachment_id);
        }

        $target_img = $unscaled_img_url ?: $img_url;
        if ($target_img) {
            $img_filename = wp_basename($target_img);
            $clean_filename = preg_replace('/-\d+x\d+(\.[a-zA-Z0-9]+)$/', '$1', $img_filename);
            $img_att_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_wp_attached_file' AND meta_value LIKE %s", '%' . $clean_filename));
            if ($img_att_id) {
                set_post_thumbnail($post_id, $img_att_id);
            } else {
                $tmp_img = download_url($target_img);
                if (!is_wp_error($tmp_img)) {
                    $img_file_array = ['name' => $img_filename, 'tmp_name' => $tmp_img];
                    $sideload_img_id = media_handle_sideload($img_file_array, $post_id);
                    if (!is_wp_error($sideload_img_id)) {
                        set_post_thumbnail($post_id, $sideload_img_id);
                    } else {
                        @unlink($tmp_img);
                    }
                }
            }
        }

        $migrated_count++;
    }

    $log("Tachydromos migration complete. Converted: $migrated_count, Skipped (Existing): $skipped_count.", "INFO");
}

// ----------------------------------------------------------------------
// 2. Migrate Board Member Testimonials
// ----------------------------------------------------------------------
function eka_migrate_board_members_cpt($log) {
    $log("Starting Board Members CPT migration...", "INFO");

    $scoping_file = get_template_directory() . '/ai-work/scopings/board-scoping.json';
    $scoping_data = file_exists($scoping_file) ? json_decode(file_get_contents($scoping_file), true) : null;

    global $wpdb;
    $legacy_db = $wpdb;

    $testimonials = $legacy_db->get_results("SELECT ID, post_title, post_content, menu_order FROM wp_posts WHERE post_type='testimonial' AND post_status='publish'");
    if (empty($testimonials)) {
        $log("No legacy testimonials found in db207080_eka.", "INFO");
        return;
    }

    $languages = $legacy_db->get_results("
        SELECT tr.object_id, t.slug as language
        FROM wp_term_relationships tr
        JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        JOIN wp_terms t ON tt.term_id = t.term_id
        WHERE tt.taxonomy = 'language'
    ");
    $post_languages = [];
    foreach ($languages as $l) {
        $post_languages[$l->object_id] = $l->language;
    }

    $groups = $scoping_data['translation_groups'] ?? [];
    if (empty($groups)) {
        $translations = $legacy_db->get_results("
            SELECT t.description as serialized_group
            FROM wp_term_taxonomy tt
            JOIN wp_terms t ON tt.term_id = t.term_id
            WHERE tt.taxonomy = 'post_translations'
        ");
        foreach ($translations as $t) {
            $group = @unserialize($t->serialized_group);
            if (is_array($group)) {
                $groups[] = $group;
            }
        }
    }

    $thumbnails = $legacy_db->get_results("
        SELECT post_id, meta_value as thumbnail_id
        FROM wp_postmeta
        WHERE meta_key='_thumbnail_id'
    ");
    $legacy_thumbs = [];
    foreach ($thumbnails as $t) {
        $legacy_thumbs[$t->post_id] = $t->thumbnail_id;
    }

    global $wpdb;
    $migrated_map = [];
    $migrated_count = 0;
    $skipped_count = 0;

    foreach (['el', 'en', 'ar'] as $lang) {
        foreach ($testimonials as $t) {
            $post_lang = isset($post_languages[$t->ID]) ? $post_languages[$t->ID] : 'el';
            if ($post_lang !== $lang) continue;

            $existing = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_legacy_testimonial_id' AND meta_value=%d", $t->ID));
            if ($existing) {
                $migrated_map[$t->ID] = $existing;
                $skipped_count++;
                continue;
            }

            $clean_content = preg_replace('/<img[^>]*>/i', '', $t->post_content);
            $clean_content = preg_replace('/\[\/?vc_[^\]]+\]/', '', $clean_content);
            $clean_content = trim($clean_content);

            $post_id = wp_insert_post([
                'post_title' => $t->post_title,
                'post_content' => $clean_content,
                'post_status' => 'publish',
                'post_type' => 'board_member',
                'menu_order' => $t->menu_order,
            ]);

            if (is_wp_error($post_id)) {
                $log("Failed to insert board member: {$t->post_title}", "WARNING", $post_id->get_error_message());
                continue;
            }

            $migrated_map[$t->ID] = $post_id;
            update_post_meta($post_id, '_legacy_testimonial_id', $t->ID);

            $thumbnail_id = 0;
            if (isset($legacy_thumbs[$t->ID])) {
                $legacy_thumb_id = $legacy_thumbs[$t->ID];
                $legacy_attachment = $legacy_db->get_row($legacy_db->prepare("SELECT meta_value FROM wp_postmeta WHERE post_id=%d AND meta_key='_wp_attached_file'", $legacy_thumb_id));
                
                if ($legacy_attachment) {
                    $filename = wp_basename($legacy_attachment->meta_value);
                    $unscaled_filename = preg_replace('/-\d+x\d+(\.[a-zA-Z0-9]+)$/', '$1', $filename);
                    
                    $existing_attachment = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_wp_attached_file' AND meta_value LIKE %s", '%' . $unscaled_filename));
                    
                    if ($existing_attachment) {
                        $thumbnail_id = $existing_attachment;
                    } else {
                        $legacy_url = "https://ekalexandria.org/wp-content/uploads/" . $legacy_attachment->meta_value;
                        $upload_dir = wp_upload_dir();
                        $local_filename = 'board-' . md5($legacy_url) . '-' . $filename;
                        $local_path = $upload_dir['path'] . '/' . $local_filename;
                        
                        $img_content = @file_get_contents($legacy_url);
                        if ($img_content) {
                            file_put_contents($local_path, $img_content);
                            $filetype = wp_check_filetype($local_filename, null);
                            $attachment = array(
                                'post_mime_type' => $filetype['type'],
                                'post_title'     => sanitize_file_name($filename),
                                'post_content'   => '',
                                'post_status'    => 'inherit'
                            );
                            $thumbnail_id = wp_insert_attachment($attachment, $local_path, $post_id);
                            require_once(ABSPATH . 'wp-admin/includes/image.php');
                            $attach_data = wp_generate_attachment_metadata($thumbnail_id, $local_path);
                            wp_update_attachment_metadata($thumbnail_id, $attach_data);
                        }
                    }
                }
            }
            
            if ($thumbnail_id) {
                set_post_thumbnail($post_id, $thumbnail_id);
            }

            if (function_exists('pll_set_post_language')) {
                pll_set_post_language($post_id, $lang);
            }

            $migrated_count++;
        }
    }

    if (function_exists('pll_save_post_translations')) {
        foreach ($groups as $group) {
            $new_group = [];
            foreach ($group as $lang => $legacy_id) {
                if (isset($migrated_map[$legacy_id])) {
                    $new_group[$lang] = $migrated_map[$legacy_id];
                }
            }
            if (count($new_group) > 1) {
                pll_save_post_translations($new_group);
            }
        }
    }

    $log("Board Members migration complete. Converted: $migrated_count, Skipped (Existing): $skipped_count.", "INFO");
}

// Execute migration functions
eka_migrate_tachydromos_cpt($log);
eka_migrate_board_members_cpt($log);

$log("CPT Migration finished successfully.", "INFO");
