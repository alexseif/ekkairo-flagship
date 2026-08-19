<?php
/**
 * Shared Helpers for Gutenberg Migration & FSE Style Sanitization
 * Targets: ekkairo-flagship / backstage_ekk DB
 */

if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

require_once __DIR__ . '/src/Utils/Logger.php';
require_once __DIR__ . '/src/Utils/StyleSanitizer.php';
require_once __DIR__ . '/src/Content/ContentTransformer.php';
require_once __DIR__ . '/src/Cpt/CptMigrator.php';
require_once __DIR__ . '/src/Navigation/MenuMigrator.php';

use EkaAlexandria\Migration\Utils\StyleSanitizer;
use EkaAlexandria\Migration\Content\ContentTransformer;

if (!function_exists('eka_get_db_config')) {
    /**
     * Dynamically extracts database credentials from WordPress environment constants,
     * environment variables, or wp-config.php without hardcoded values.
     *
     * @return array Array with keys 'host', 'user', 'pass', 'name'.
     */
    function eka_get_db_config() {
        if (defined('DB_NAME')) {
            return [
                'host' => defined('DB_HOST') ? DB_HOST : 'localhost',
                'user' => defined('DB_USER') ? DB_USER : 'root',
                'pass' => defined('DB_PASSWORD') ? DB_PASSWORD : '',
                'name' => DB_NAME,
            ];
        }

        if (getenv('DB_NAME')) {
            return [
                'host' => getenv('DB_HOST') ?: 'localhost',
                'user' => getenv('DB_USER') ?: 'root',
                'pass' => getenv('DB_PASSWORD') ?: '',
                'name' => getenv('DB_NAME'),
            ];
        }

        $possible_paths = [
            dirname(__DIR__, 3) . '/wp-config.php',
            '/var/www/backstage.ekkairo.org/public/wp-config.php',
        ];

        $config = ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'backstage_ekk'];

        foreach ($possible_paths as $wp_config_path) {
            if (!file_exists($wp_config_path)) {
                continue;
            }
            $content = file_get_contents($wp_config_path);
            $keys = ['name' => 'DB_NAME', 'user' => 'DB_USER', 'pass' => 'DB_PASSWORD', 'host' => 'DB_HOST'];
            foreach ($keys as $config_key => $const_name) {
                if (preg_match("/define\(\s*['\"]" . $const_name . "['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $content, $m)) {
                    $config[$config_key] = $m[1];
                }
            }
            break;
        }

        return $config;
    }
}

if (!function_exists('sanitize_inline_styles_fse')) {
    function sanitize_inline_styles_fse($style_string) {
        return StyleSanitizer::sanitizeInlineStyles((string)$style_string);
    }
}

if (!function_exists('clean_image_tag')) {
    function clean_image_tag($img_html) {
        return StyleSanitizer::cleanImageTag((string)$img_html);
    }
}

if (!function_exists('eka_init_log_file')) {
    function eka_init_log_file($log_path) {
        StyleSanitizer::initLogFile((string)$log_path);
    }
}

if (!function_exists('eka_validate_blocks_ast')) {
    function eka_validate_blocks_ast($content) {
        return StyleSanitizer::validateBlocksAst((string)$content);
    }
}

if (!function_exists('eka_load_slider_scoping')) {
    /**
     * Loads slider scoping map from JSON index.
     *
     * @return array Array indexed by post_id
     */
    function eka_load_slider_scoping() {
        $scoping_file = dirname(__DIR__) . '/ai-work/scopings/detailed-sliders-inventory.json';
        if (!file_exists($scoping_file)) {
            $scoping_file = dirname(__DIR__) . '/ai-work/scopings/legacy-items-inventory.json';
        }
        $scoping_map = [];
        if (file_exists($scoping_file)) {
            $raw = json_decode(file_get_contents($scoping_file), true);
            if (is_array($raw)) {
                $usages = isset($raw['layerslider_page_usages']) ? $raw['layerslider_page_usages'] : $raw;
                foreach ($usages as $item) {
                    if (isset($item['page_id'])) {
                        $scoping_map[(int)$item['page_id']] = $item;
                    }
                }
            }
        }
        return $scoping_map;
    }
}

if (!function_exists('eka_resolve_post_images')) {
    /**
     * Resolves attachment IDs and image URLs for a post.
     *
     * @param int $post_id
     * @param array $scoping_map
     * @param mysqli|null $mysqli
     * @return array Array of ['id' => int, 'url' => string]
     */
    function eka_resolve_post_images($post_id, $scoping_map = [], $mysqli = null) {
        $post_id = (int)$post_id;
        $images = [];
        $seen_ids = [];

        if (isset($scoping_map[$post_id])) {
            $scoped = $scoping_map[$post_id];

            if (!empty($scoped['attached_media']) && is_array($scoped['attached_media'])) {
                foreach ($scoped['attached_media'] as $media) {
                    $id = isset($media['attachment_id']) ? (int)$media['attachment_id'] : 0;
                    $url = isset($media['url']) ? $media['url'] : '';
                    if ($id > 0 && !isset($seen_ids[$id])) {
                        $seen_ids[$id] = true;
                        $images[] = ['id' => $id, 'url' => $url];
                    }
                }
            }

            if (!empty($scoped['embedded_images']) && is_array($scoped['embedded_images'])) {
                foreach ($scoped['embedded_images'] as $media) {
                    $id = isset($media['db_attachment_id']) ? (int)$media['db_attachment_id'] : 0;
                    $url = isset($media['src_url']) ? $media['src_url'] : '';
                    if ($id > 0 && !isset($seen_ids[$id])) {
                        $seen_ids[$id] = true;
                        $images[] = ['id' => $id, 'url' => $url];
                    }
                }
            }

            if (!empty($scoped['gallery_image_ids']) && is_array($scoped['gallery_image_ids'])) {
                foreach ($scoped['gallery_image_ids'] as $gid) {
                    $id = (int)$gid;
                    if ($id > 0 && !isset($seen_ids[$id])) {
                        $seen_ids[$id] = true;
                        $images[] = ['id' => $id, 'url' => ''];
                    }
                }
            }
        }

        if (empty($images) && $mysqli instanceof mysqli) {
            $stmt = $mysqli->prepare("SELECT ID, guid FROM wp_posts WHERE post_parent = ? AND post_type = 'attachment' AND post_mime_type LIKE 'image/%'");
            if ($stmt) {
                $stmt->bind_param("i", $post_id);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    while ($row = $res->fetch_assoc()) {
                        $id = (int)$row['ID'];
                        $url = $row['guid'];
                        if ($id > 0 && !isset($seen_ids[$id])) {
                            $seen_ids[$id] = true;
                            $images[] = ['id' => $id, 'url' => $url];
                        }
                    }
                }
                $stmt->close();
            }
        }

        if (!empty($images) && $mysqli instanceof mysqli) {
            foreach ($images as &$img) {
                if ($img['id'] > 0 && empty($img['url'])) {
                    $stmt = $mysqli->prepare("SELECT guid FROM wp_posts WHERE ID = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $img['id']);
                        if ($stmt->execute()) {
                            $res = $stmt->get_result();
                            if ($row = $res->fetch_assoc()) {
                                $img['url'] = $row['guid'];
                            }
                        }
                        $stmt->close();
                    }
                }
            }
            unset($img);
        }

        return $images;
    }
}
