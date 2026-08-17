<?php
/**
 * bin/migration-content-engine.php
 * Modular 6-Step Gutenberg Content Migration Engine
 * Targets: backstage_eka DB via WP-CLI / MySQL
 */

require_once __DIR__ . '/migration-helpers.php';

use EkaAlexandria\Migration\Content\ContentTransformer;
use EkaAlexandria\Migration\Utils\Logger;

$GLOBALS['eka_log_file'] = dirname(__DIR__) . '/ai-work/logs/content-engine.log';

function eka_engine_log($msg, $level = 'INFO')
{
    static $logger = null;
    if ($logger === null) {
        $log_file = isset($GLOBALS['eka_log_file']) ? $GLOBALS['eka_log_file'] : dirname(__DIR__) . '/ai-work/logs/content-engine.log';
        $logger = new Logger($log_file, true);
    }
    $logger->log($msg, $level);
}

eka_engine_log("==========================================");
eka_engine_log("Starting Migration Content Engine: " . date('Y-m-d H:i:s'));
eka_engine_log("==========================================");

if (!defined('EKA_TEST_MODE') || !EKA_TEST_MODE) {
    $db_config = eka_get_db_config();
    $mysqli = new mysqli($db_config['host'], $db_config['user'], $db_config['pass'], $db_config['name']);
    if ($mysqli->connect_error) {
        eka_engine_log("Database connection failed: " . $mysqli->connect_error, "ERROR");
        die("Connection failed: " . $mysqli->connect_error . "\n");
    }
    $mysqli->set_charset("utf8mb4");
}

function parse_fraction_width($width_str)
{
    $transformer = new ContentTransformer();
    return $transformer->parseFractionWidth((string)$width_str);
}

function clean_html_inline_styles($html)
{
    $transformer = new ContentTransformer();
    return $transformer->cleanHtmlInlineStyles((string)$html);
}

function eka_process_front_page_content($content, $post_id)
{
    $transformer = new ContentTransformer();
    return $transformer->processFrontPageContent((string)$content, (int)$post_id);
}

// ----------------------------------------------------------------------
// Phase 3A: Replace Sliders ([rev_slider], [layerslider])
// ----------------------------------------------------------------------
function step_3a_transform_sliders($content, $post_id)
{
    // Exception pages: Hero slider is handled natively by FSE templates. Strip shortcode & wrapper container completely.
    $exception_pages = [13236, 16894, 16892, 18, 16920, 16923];
    if (in_array((int) $post_id, $exception_pages, true)) {
        $content = preg_replace('/\[vc_row[^\]]*\]\s*(?:\[vc_column[^\]]*\])?\s*\[(?:rev_slider|rev_slider_vc|layerslider)[^\]]*\]\s*(?:\[\/vc_column\])?\s*\[\/vc_row\]/is', '', $content);
        $content = preg_replace('/\[(?:rev_slider|rev_slider_vc|layerslider)[^\]]*\]/i', '', $content);
        return $content;
    }

    if (strpos($content, 'wp:query') !== false || strpos($content, 'wp:gallery') !== false) {
        // Skip if already converted
    }

    $dynamic_pages = [8934, 17194, 17215, 17219];
    $query_loop_block = '<!-- wp:query {"queryId":1,"query":{"perPage":5,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false}} -->
<div class="wp-block-query">
<!-- wp:post-template -->
<!-- wp:post-title {"isLink":true} /-->
<!-- wp:post-excerpt {"moreText":"Read more"} /-->
<!-- wp:post-date /-->
<!-- /wp:post-template -->
</div>
<!-- /wp:query -->';

    if (in_array((int) $post_id, $dynamic_pages, true)) {
        if (preg_match('/\[(rev_slider|rev_slider_vc|layerslider)[^\]]*\]/i', $content)) {
            $content = preg_replace('/\[(rev_slider|rev_slider_vc)[^\]]*\]/i', $query_loop_block, $content);
            $content = preg_replace('/\[layerslider[^\]]*\]/i', $query_loop_block, $content);
            return $content;
        }
    }

    $gallery_groups = [
        ['ids' => [7821, 7822, 7823], 'pages' => [7820, 17129, 17133]],
        ['ids' => [7813, 7814, 7815], 'pages' => [7811, 17137, 17139]],
        ['ids' => [10329, 7667, 7668, 7669, 7670, 7671, 7672, 7673], 'pages' => [3442, 17023, 17027, 17155]],
        ['ids' => [7935, 7936, 7937, 7938, 7940, 7941, 7942], 'pages' => [7756, 17150]],
        ['ids' => [10328], 'pages' => [7390, 17018, 17020]],
    ];
    $static_galleries = [];
    foreach ($gallery_groups as $group) {
        foreach ($group['pages'] as $pid) {
            $static_galleries[$pid] = $group['ids'];
        }
    }

    if (isset($static_galleries[(int) $post_id])) {
        $media_ids = $static_galleries[(int) $post_id];
        $gallery_block = '<!-- wp:gallery {"linkTo":"none"} -->
<figure class="wp-block-gallery has-nested-images columns-default is-cropped">';
        foreach ($media_ids as $media_id) {
            $img_url = wp_get_attachment_url($media_id) ?: '';
            $gallery_block .= sprintf(
                '<!-- wp:image {"id":%d,"sizeSlug":"full","linkDestination":"none"} -->' . "\n" .
                '<figure class="wp-block-image size-full"><img src="%s" alt="" class="wp-image-%d"/></figure>' . "\n" .
                '<!-- /wp:image -->',
                $media_id,
                esc_url($img_url),
                $media_id
            );
        }
        $gallery_block .= '</figure>
<!-- /wp:gallery -->';

        if (preg_match('/\[(rev_slider|rev_slider_vc|layerslider)[^\]]*\]/i', $content)) {
            $content = preg_replace('/\[(rev_slider|rev_slider_vc)[^\]]*\]/i', $gallery_block, $content);
            $content = preg_replace('/\[layerslider[^\]]*\]/i', $gallery_block, $content);
            return $content;
        }
    }

    // Generic slider fallback
    $content = preg_replace_callback(
        '/\[(?:rev_slider|rev_slider_vc)\s+(?:(?:alias|title|id)=["\']([^"\']+)["\']|([a-zA-Z0-9_-]+))[^\]]*\]/i',
        function ($matches) {
            $alias = !empty($matches[1]) ? $matches[1] : (!empty($matches[2]) ? $matches[2] : 'default');
            $alias = htmlspecialchars($alias, ENT_QUOTES, 'UTF-8');
            return '<!-- wp:gallery {"className":"rev-slider-replaced"} --><figure class="wp-block-gallery has-nested-images columns-default is-cropped rev-slider-replaced"><!-- wp:paragraph --><p>Slider: ' . $alias . '</p><!-- /wp:paragraph --></figure><!-- /wp:gallery -->';
        },
        $content
    );

    $content = preg_replace_callback(
        '/\[layerslider\s+(?:(?:id|title)=["\']([^"\']+)["\']|([a-zA-Z0-9_-]+))[^\]]*\]/i',
        function ($matches) {
            $id = !empty($matches[1]) ? $matches[1] : (!empty($matches[2]) ? $matches[2] : 'default');
            $id = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
            return '<!-- wp:gallery {"className":"layerslider-replaced"} --><figure class="wp-block-gallery has-nested-images columns-default is-cropped layerslider-replaced"><!-- wp:paragraph --><p>LayerSlider ID: ' . $id . '</p><!-- /wp:paragraph --></figure><!-- /wp:gallery -->';
        },
        $content
    );

    return $content;
}

// ----------------------------------------------------------------------
// Phase 3B: Replace Testimonials ([testimonials])
// ----------------------------------------------------------------------
function step_3b_transform_testimonials($content)
{
    if (strpos($content, '[testimonials') === false) {
        return $content;
    }

    $board_query = '<!-- wp:query {"queryId":2,"query":{"perPage":50,"pages":0,"offset":0,"postType":"board_member","order":"asc","orderBy":"menu_order","author":"","search":"","exclude":[],"sticky":"","inherit":false}} -->
<div class="wp-block-query">
<!-- wp:post-template {"layout":{"type":"grid","columnCount":3},"className":"board-members-list"} -->
<!-- wp:group {"className":"board-member-card"} -->
<div class="wp-block-group board-member-card">
<!-- wp:post-featured-image {"isLink":false} /-->
<!-- wp:post-title {"level":3} /-->
<!-- wp:post-content /-->
</div>
<!-- /wp:group -->
<!-- /wp:post-template -->
</div>
<!-- /wp:query -->';

    $content = preg_replace('/<!-- wp:shortcode -->\s*\[testimonials[^\]]*\]\s*<!-- \/wp:shortcode -->/is', $board_query, $content);
    $content = preg_replace('/\[testimonials[^\]]*\]/is', $board_query, $content);

    return $content;
}

// ----------------------------------------------------------------------
// Phase 3C: Subpages Query Loop Shortcodes & vc_posts_grid
// ----------------------------------------------------------------------
function step_3c_transform_vc_posts_grid($content, $post_id = 0)
{
    $transformer = new ContentTransformer();
    $content = $transformer->transformVcPostsGrid((string)$content, (int)$post_id);

    // Convert standalone numeric shortcodes outside Gutenberg block comments into subpage query loops
    $tokens = preg_split('/(<!--\s+\/?wp:[^>]+-->)/s', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    $in_block = false;
    $output = '';

    foreach ($tokens as $token) {
        if (preg_match('/^<!--\s+wp:/s', $token)) {
            $in_block = true;
            $output .= $token;
        } elseif (preg_match('/^<!--\s+\/wp:/s', $token)) {
            $in_block = false;
            $output .= $token;
        } else {
            if (!$in_block) {
                $token = preg_replace_callback(
                    '/\[(\d+)\]/',
                    function ($m) {
                        $pid = (int) $m[1];
                        return '<!-- wp:query {"queryId":4,"query":{"perPage":1,"pages":0,"offset":0,"postType":"page","order":"asc","orderBy":"menu_order","author":"","search":"","exclude":[],"sticky":"","inherit":false,"include":[' . $pid . ']}} -->
<div class="wp-block-query">
<!-- wp:post-template -->
<!-- wp:post-featured-image {"isLink":true} /-->
<!-- wp:post-title {"isLink":true,"level":3} /-->
<!-- wp:post-excerpt /-->
<!-- /wp:post-template -->
</div>
<!-- /wp:query -->';
                    },
                    $token
                );
            }
            $output .= $token;
        }
    }

    return $output;
}

// ----------------------------------------------------------------------
// Phase 3G: Media Embeds & Plugin Shortcodes Migration
// ----------------------------------------------------------------------
function step_3g_transform_media_and_plugins($content)
{
    $transformer = new ContentTransformer();
    $content = $transformer->transformMediaAndPlugins((string)$content);

    // 1. [embed]url[/embed] -> core/embed
    $content = preg_replace_callback(
        '/\[embed[^\]]*\]\s*(https?:\/\/[^\s<]+)\s*\[\/embed\]/i',
        function ($matches) {
            $url = htmlspecialchars(trim($matches[1]), ENT_QUOTES, 'UTF-8');
            return '<!-- wp:embed {"url":"' . $url . '","type":"rich","providerNameSlug":"embed"} --><figure class="wp-block-embed"><div class="wp-block-embed__wrapper">' . $url . '</div></figure><!-- /wp:embed -->';
        },
        $content
    );

    // 2. [video src="url"] -> core/video
    $content = preg_replace_callback(
        '/\[video\s+[^\]]*?src=["\']([^"\']+)["\'][^\]]*\]/i',
        function ($matches) {
            $url = htmlspecialchars(trim($matches[1]), ENT_QUOTES, 'UTF-8');
            return '<!-- wp:video --><figure class="wp-block-video"><video controls src="' . $url . '"></video></figure><!-- /wp:video -->';
        },
        $content
    );

    // 3. [map lat="LAT" lng="LNG" ...] -> core/html iframe
    $content = preg_replace_callback(
        '/\[map\s+[^\]]*?lat=["\']([^"\']+)["\']\s+lng=["\']([^"\']+)["\'][^\]]*\]/i',
        function ($matches) {
            $lat = $matches[1];
            $lng = $matches[2];
            return '<!-- wp:html --><iframe src="https://maps.google.com/maps?q=' . $lat . ',' . $lng . '&amp;output=embed" width="100%" height="400" frameborder="0"></iframe><!-- /wp:html -->';
        },
        $content
    );

    // 4. [gview file="URL"] -> core/file
    $content = preg_replace_callback(
        '/\[gview\s+[^\]]*?file=["\']([^"\']+)["\'][^\]]*\]/i',
        function ($matches) {
            $url = htmlspecialchars(trim($matches[1]), ENT_QUOTES, 'UTF-8');
            return '<!-- wp:file {"href":"' . $url . '","displayPreview":true} --><div class="wp-block-file"><object class="wp-block-file__embed" data="' . $url . '" type="application/pdf" style="width:100%;height:600px"></object><a href="' . $url . '">Download Document</a></div><!-- /wp:file -->';
        },
        $content
    );

    // 5. [mc4wp_form] -> [eka_mailchimp_form]
    $content = str_replace('[mc4wp_form]', '[eka_mailchimp_form]', $content);

    // 6. [our_team_list] -> remove completely
    $content = preg_replace('/\[our_team_list[^\]]*\]/i', '', $content);

    return $content;
}

// ----------------------------------------------------------------------
// Phase 3D: Structural WPBakery & Caption Shortcodes
// ----------------------------------------------------------------------
function step_3d_transform_wpbakery_and_caption($content, $mysqli = null)
{
    $transformer = new ContentTransformer();
    return $transformer->transformWpbakeryAndCaption((string)$content, $mysqli);
}

// ----------------------------------------------------------------------
// Phase 3E: Residual Shortcode Clean-Up & Block Comment Isolation
// ----------------------------------------------------------------------
function step_3e_transform_residual_shortcodes($content)
{
    $content = preg_replace('/\[\/?vc_[^\]]*\]/', '', $content);
    $content = preg_replace('/\[\/?mfn_[^\]]*\]/', '', $content);

    $ignored_tags = ['wp', 'caption', 'vc_row', 'vc_column', 'vc_column_text', 'vc_single_image', 'vc_raw_html', 'our_team', 'rev_slider', 'rev_slider_vc', 'layerslider', 'testimonials', 'vc_posts_grid', 'eka_mailchimp_form', 'polylang_langswitcher', 'metadata', 'sigma', 'greek'];

    // Split content by Gutenberg HTML comments to prevent JSON attributes inside <!-- wp:... --> comments from being transformed
    $tokens = preg_split('/(<!--\s+\/?wp:[^>]+-->)/s', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    $in_block = false;
    $output = '';

    foreach ($tokens as $token) {
        if (preg_match('/^<!--\s+wp:/s', $token)) {
            $in_block = true;
            $output .= $token;
        } elseif (preg_match('/^<!--\s+\/wp:/s', $token)) {
            $in_block = false;
            $output .= $token;
        } else {
            if ($in_block) {
                $output .= $token;
            } else {
                // Split by HTML tags to avoid modifying attributes like href="...search_coll[metadata]=1..."
                $parts = preg_split('/(<[^>]+>)/s', $token, -1, PREG_SPLIT_DELIM_CAPTURE);
                foreach ($parts as $part) {
                    if (preg_match('/^<[^>]+>$/s', $part)) {
                        // Inside an HTML tag - keep 100% untouched
                        $output .= $part;
                    } else {
                        // Text outside HTML tags
                        $output .= preg_replace_callback(
                            '/\[([a-zA-Z0-9_-]+)([^\]]*)\]/s',
                            function ($m) use ($ignored_tags) {
                                $tag = strtolower($m[1]);
                                if (in_array($tag, $ignored_tags, true)) {
                                    return $m[0];
                                }
                                return '<!-- wp:html -->' . $m[0] . '<!-- /wp:html -->';
                            },
                            $part
                        );
                    }
                }
            }
        }
    }

    return $output;
}

// ----------------------------------------------------------------------
// Phase 3F: Classic HTML AST Block Conversion & Inline CSS Allowlist
// ----------------------------------------------------------------------
function convert_html_elements_to_blocks($html)
{
    $rules = [
        '/<h([1-6])(\s+[^>]*)?>(.*?)<\/h\1>/is' => function ($m) {
            $level = (int) $m[1];
            $tag_html = clean_html_inline_styles("<h{$level}" . ($m[2] ?? '') . ">{$m[3]}</h{$level}>");
            return "<!-- wp:heading {\"level\":{$level}} -->{$tag_html}<!-- /wp:heading -->";
        },
        '/<ul(\s+[^>]*)?>(.*?)<\/ul>/is' => function ($m) {
            $tag_html = clean_html_inline_styles("<ul" . ($m[1] ?? '') . ">{$m[2]}</ul>");
            return "<!-- wp:list -->{$tag_html}<!-- /wp:list -->";
        },
        '/<ol(\s+[^>]*)?>(.*?)<\/ol>/is' => function ($m) {
            $tag_html = clean_html_inline_styles("<ol" . ($m[1] ?? '') . ">{$m[2]}</ol>");
            return "<!-- wp:list {\"ordered\":true} -->{$tag_html}<!-- /wp:list -->";
        },
        '/<table(\s+[^>]*)?>(.*?)<\/table>/is' => function ($m) {
            $tag_html = clean_html_inline_styles("<table" . ($m[1] ?? '') . ">{$m[2]}</table>");
            return "<!-- wp:table --><figure class=\"wp-block-table\">{$tag_html}</figure><!-- /wp:table -->";
        },
        '/<blockquote(\s+[^>]*)?>(.*?)<\/blockquote>/is' => function ($m) {
            $tag_html = clean_html_inline_styles("<blockquote class=\"wp-block-quote\"" . ($m[1] ?? '') . ">{$m[2]}</blockquote>");
            return "<!-- wp:quote -->{$tag_html}<!-- /wp:quote -->";
        },
        '/<img(\s+[^>]*)?\/?>/is' => function ($m) {
            $raw_img = "<img" . ($m[1] ?? '') . " />";
            $clean_img = clean_image_tag($raw_img);
            $img_id = 0;
            if (preg_match('/wp-image-(\d+)/i', $m[1] ?? '', $id_match)) {
                $img_id = (int)$id_match[1];
            }
            $json_attr = $img_id > 0 ? " {\"id\":{$img_id}}" : "";
            return "<!-- wp:image{$json_attr} --><figure class=\"wp-block-image\">{$clean_img}</figure><!-- /wp:image -->";
        },
        '/<p(\s+[^>]*)?>(.*?)<\/p>/is' => function ($m) {
            $inner = trim($m[2]);
            if (empty($inner) || $inner === '&nbsp;') {
                return '';
            }
            return "<!-- wp:paragraph -->\n<p>{$inner}</p>\n<!-- /wp:paragraph -->";
        },
    ];

    foreach ($rules as $pattern => $callback) {
        $html = preg_replace_callback($pattern, $callback, $html);
    }

    return $html;
}

function step_3f_process_classic_html($content)
{
    if (empty(trim($content))) {
        return $content;
    }

    $tokens = preg_split('/(<!--\s+\/?wp:[^>]+-->)/s', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    $in_block = false;
    $output = '';

    foreach ($tokens as $token) {
        if (preg_match('/^<!--\s+wp:/s', $token)) {
            $in_block = true;
            $output .= $token;
        } elseif (preg_match('/^<!--\s+\/wp:/s', $token)) {
            $in_block = false;
            $output .= $token;
        } else {
            if ($in_block) {
                $output .= $token;
            } else {
                if (function_exists('wpautop')) {
                    $token = wpautop($token);
                }
                $token = preg_replace('/<p>\s*&nbsp;\s*<\/p>/i', '', $token);
                $converted = convert_html_elements_to_blocks($token);
                $output .= $converted;
            }
        }
    }

    return $output;
}

// ----------------------------------------------------------------------
// Main Transformation Pipeline Execution
// ----------------------------------------------------------------------

$sql = "SELECT ID, post_title, post_content FROM wp_posts WHERE post_type IN ('page', 'post', 'testimonial', 'board_member', 'alx_tachydromos') AND post_status IN ('publish', 'draft', 'private', 'pending', 'future')";
$res = $mysqli->query($sql);

if (!$res) {
    eka_engine_log("Query failed: " . $mysqli->error, "ERROR");
    exit(1);
}

$total_scanned = $res->num_rows;
eka_engine_log("Scanning {$total_scanned} posts across 6-step transformation pipeline...");

$converted_count = 0;
$skipped_count = 0;
$failed_ast_count = 0;
$failed_post_ids = [];

while ($row = $res->fetch_assoc()) {
    $id = (int) $row['ID'];
    $original_content = $row['post_content'];

    $front_page_ids = [13236, 16894, 16892];
    if (in_array($id, $front_page_ids, true)) {
        // Front page content extraction (remove all shortcodes, extract text, sanitize and convert to Gutenberg)
        $content = eka_process_front_page_content($original_content, $id);
    } else {
        // Optimized Sequence: 3A -> 3B -> 3C -> 3D -> 3G -> 3F -> 3E
        $content = step_3a_transform_sliders($original_content, $id);
        $content = step_3b_transform_testimonials($content);
        $content = step_3c_transform_vc_posts_grid($content, $id);
        $content = step_3d_transform_wpbakery_and_caption($content, $mysqli);
        $content = step_3g_transform_media_and_plugins($content);
        $content = step_3f_process_classic_html($content);
        $content = step_3e_transform_residual_shortcodes($content);
    }

    if ($content === $original_content) {
        $skipped_count++;
        continue;
    }

    // AST Validation
    if (!eka_validate_blocks_ast($content)) {
        eka_engine_log("AST Validation failed for post ID {$id} ('{$row['post_title']}'). Skipping update.", "WARNING");
        $failed_ast_count++;
        $failed_post_ids[] = $id;
        continue;
    }

    $stmt = $mysqli->prepare("UPDATE wp_posts SET post_content = ? WHERE ID = ?");
    if ($stmt) {
        $stmt->bind_param("si", $content, $id);
        if ($stmt->execute()) {
            $converted_count++;
        } else {
            eka_engine_log("Failed to update Post ID {$id}: " . $stmt->error, "ERROR");
            $failed_ast_count++;
            $failed_post_ids[] = $id;
        }
        $stmt->close();
    }
}

// ----------------------------------------------------------------------
// Metrics Summary
// ----------------------------------------------------------------------
eka_engine_log("==========================================");
eka_engine_log(" MIGRATION SUMMARY: Shortcode & Block Remediation");
eka_engine_log("==========================================");
eka_engine_log(" Total Posts Scanned   : {$total_scanned}");
eka_engine_log(" Successfully Converted: {$converted_count}");
eka_engine_log(" Skipped / Unchanged   : {$skipped_count}");
eka_engine_log(" Failed AST Validation : {$failed_ast_count}");
if (!empty($failed_post_ids)) {
    eka_engine_log(" Failed Post IDs       : [" . implode(', ', $failed_post_ids) . "]");
} else {
    eka_engine_log(" Failed Post IDs       : []");
}
eka_engine_log("==========================================");

$mysqli->close();
eka_engine_log("Content engine pipeline execution completed successfully.");
