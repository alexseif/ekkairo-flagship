<?php
/**
 * bin/migration-content-engine.php
 * Modular 6-Step Gutenberg Content Migration Engine
 * Targets: backstage_ekk DB via WP-CLI / MySQL
 */

require_once __DIR__ . '/migration-helpers.php';

$base_dir = dirname(__DIR__);
$log_dir = $base_dir . '/ai-work/logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}

$GLOBALS['engine_log_file'] = $log_dir . '/content-engine.log';
$GLOBALS['unmapped_log_file'] = $log_dir . '/unmapped-shortcodes.log';

function engine_log($msg, $level = 'INFO')
{
    $log_file = $GLOBALS['engine_log_file'];
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] [{$level}] {$msg}\n";
    file_put_contents($log_file, $line, FILE_APPEND);
    echo $line;
}

function log_unmapped_shortcode($post_id, $post_title, $post_type, $shortcode_snippet)
{
    $log_file = $GLOBALS['unmapped_log_file'];
    $timestamp = date('Y-m-d H:i:s');
    $entry = sprintf("[%s] Post ID: %d | Type: %s | Title: '%s' | Unmapped Shortcode: %s\n", $timestamp, $post_id, $post_type, $post_title, $shortcode_snippet);
    file_put_contents($log_file, $entry, FILE_APPEND);
}

engine_log("==========================================");
engine_log("Starting EKK Flagship Content Migration Engine: " . date('Y-m-d H:i:s'));
engine_log("==========================================");

$db_config = eka_get_db_config();
$mysqli = new mysqli($db_config['host'], $db_config['user'], $db_config['pass'], $db_config['name']);
if ($mysqli->connect_error) {
    engine_log("Database connection failed: " . $mysqli->connect_error, "ERROR");
    die("Connection failed: " . $mysqli->connect_error . "\n");
}
$mysqli->set_charset("utf8mb4");

// Track detailed metrics for log report
$metrics = [
    'total_scanned'             => 0,
    'posts_count'               => 0,
    'pages_count'               => 0,
    'mfn_pages_scanned'         => 0,
    'mfn_pages_converted'       => 0,
    'shortcodes_found'          => [
        'layerslider'      => 0,
        'gallery_slideshow'=> 0,
        'contact_form_7'   => 0,
        'pdf_embedder'     => 0,
        'buttons'          => 0,
        'display_posts'    => 0,
        'map'              => 0,
        'embed_video'      => 0,
        'testimonials'     => 0,
        'wpbakery_vc'      => 0,
        'other'            => 0,
    ],
    'shortcodes_converted'      => 0,
    'unmapped_shortcodes_logged'=> 0,
    'successfully_converted'    => 0,
    'skipped_unchanged'         => 0,
    'failed_ast_validation'     => 0,
    'failed_post_ids'           => [],
];

// ----------------------------------------------------------------------
// Helper: Muffin Builder Item Transformer
// Converts MFN Builder item structures into Gutenberg block HTML
// ----------------------------------------------------------------------
function transform_mfn_builder_structure($mfn_structure, $is_homepage = false)
{
    if (!is_array($mfn_structure)) {
        return '';
    }

    $blocks_html = '';

    foreach ($mfn_structure as $section) {
        if (!is_array($section) || empty($section['wraps']) || !is_array($section['wraps'])) {
            continue;
        }

        foreach ($section['wraps'] as $wrap) {
            if (!is_array($wrap) || empty($wrap['items']) || !is_array($wrap['items'])) {
                continue;
            }

            foreach ($wrap['items'] as $item) {
                if (!is_array($item) || empty($item['type'])) {
                    continue;
                }

                $type = $item['type'];
                $fields = isset($item['fields']) ? $item['fields'] : [];

                switch ($type) {
                    case 'column':
                        $content = isset($fields['content']) ? trim($fields['content']) : '';
                        $title   = isset($fields['title']) ? trim($fields['title']) : '';
                        if (!empty($title)) {
                            $blocks_html .= sprintf("<!-- wp:heading {\"level\":3} -->\n<h3>%s</h3>\n<!-- /wp:heading -->\n", esc_html($title));
                        }
                        if (!empty($content)) {
                            $blocks_html .= $content . "\n";
                        }
                        break;

                    case 'fancy_heading':
                        $heading_title = isset($fields['title']) ? trim($fields['title']) : '';
                        $slogan        = isset($fields['slogan']) ? trim($fields['slogan']) : '';
                        if (!empty($heading_title)) {
                            $blocks_html .= sprintf("<!-- wp:heading {\"level\":2} -->\n<h2>%s</h2>\n<!-- /wp:heading -->\n", esc_html($heading_title));
                        }
                        if (!empty($slogan)) {
                            $blocks_html .= sprintf("<!-- wp:paragraph -->\n<p><em>%s</em></p>\n<!-- /wp:paragraph -->\n", esc_html($slogan));
                        }
                        break;

                    case 'contact_box':
                        $c_title     = isset($fields['title']) ? trim($fields['title']) : '';
                        $address     = isset($fields['address']) ? trim($fields['address']) : '';
                        $tel         = isset($fields['telephone']) ? trim($fields['telephone']) : '';
                        $tel2        = isset($fields['telephone_2']) ? trim($fields['telephone_2']) : '';
                        $fax         = isset($fields['fax']) ? trim($fields['fax']) : '';
                        $email       = isset($fields['email']) ? trim($fields['email']) : '';
                        $www         = isset($fields['www']) ? trim($fields['www']) : '';

                        $box_html = '<div class="wp-block-group contact-box-card">';
                        if ($c_title) $box_html .= '<h4>' . esc_html($c_title) . '</h4>';
                        if ($address) $box_html .= '<p>' . nl2br(esc_html($address)) . '</p>';
                        if ($tel) $box_html .= '<p>Τηλ: ' . esc_html($tel) . ($tel2 ? ' / ' . esc_html($tel2) : '') . '</p>';
                        if ($fax) $box_html .= '<p>Fax: ' . esc_html($fax) . '</p>';
                        if ($email) $box_html .= '<p>Email: <a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></p>';
                        if ($www) $box_html .= '<p>Website: <a href="http://' . esc_attr($www) . '" target="_blank">' . esc_html($www) . '</a></p>';
                        $box_html .= '</div>';

                        $blocks_html .= sprintf("<!-- wp:group -->\n%s\n<!-- /wp:group -->\n", $box_html);
                        break;

                    case 'slider_plugin':
                        if ($is_homepage) {
                            // Homepage slider bypassed as homepage is redesigned from scratch
                            engine_log("Homepage slider_plugin item bypassed per redesign rules.");
                        } else {
                            $layer_id = isset($fields['layer']) ? trim($fields['layer']) : '';
                            if ($layer_id) {
                                $blocks_html .= sprintf("[layerslider id=\"%s\"]\n", esc_attr($layer_id));
                            }
                        }
                        break;

                    case 'blog':
                    case 'blog_slider':
                        $cat = isset($fields['category']) ? trim($fields['category']) : '';
                        $count = isset($fields['count']) ? (int)$fields['count'] : 6;
                        $query_json = json_encode([
                            'queryId' => rand(10, 99),
                            'query'   => [
                                'perPage'    => $count,
                                'pages'      => 0,
                                'offset'     => 0,
                                'postType'   => 'post',
                                'order'      => 'desc',
                                'orderBy'    => 'date',
                                'author'     => '',
                                'search'     => '',
                                'exclude'    => [],
                                'sticky'     => '',
                                'inherit'    => false,
                                'taxQuery'   => $cat ? ['category' => [$cat]] : [],
                            ],
                        ]);
                        $blocks_html .= sprintf(
                            "<!-- wp:query %s -->\n<div class=\"wp-block-query\">\n<!-- wp:post-template -->\n<!-- wp:post-title {\"isLink\":true} /-->\n<!-- wp:post-excerpt /-->\n<!-- /wp:post-template -->\n</div>\n<!-- /wp:query -->\n",
                            $query_json
                        );
                        break;

                    default:
                        // Extract any raw content field inside item
                        if (!empty($fields['content'])) {
                            $blocks_html .= trim($fields['content']) . "\n";
                        }
                        break;
                }
            }
        }
    }

    return $blocks_html;
}

// ----------------------------------------------------------------------
// Phase 3A: Transform Sliders ([layerslider], [gallery])
// ----------------------------------------------------------------------
function step_3a_transform_sliders($content, $post_id, &$metrics, $db_sliders = [])
{
    static $transformer = null;
    if ($transformer === null) {
        $transformer = new \EkaAlexandria\Migration\Content\ContentTransformer();
    }

    // Scan for LayerSliders
    if (preg_match_all('/\[layerslider[^\]]*\]/i', $content, $matches)) {
        $metrics['shortcodes_found']['layerslider'] += count($matches[0]);
        $content = preg_replace_callback(
            '/\[layerslider\s+(?:(?:id|title)=["\']([^"\']+)["\']|([0-9]+))[^\]]*\]/i',
            function ($m) use (&$metrics, $db_sliders, $transformer) {
                $slider_id = !empty($m[1]) ? $m[1] : (!empty($m[2]) ? $m[2] : '0');
                $metrics['shortcodes_converted']++;

                $images = [];
                if (!empty($db_sliders)) {
                    foreach ($db_sliders as $slider) {
                        if ((int)$slider['slider_id'] === (int)$slider_id || (isset($slider['name']) && strtolower($slider['name']) === strtolower($slider_id))) {
                            foreach ($slider['slides'] as $slide) {
                                if (!empty($slide['background_image_url'])) {
                                    $images[] = [
                                        'id'  => (int)($slide['background_att_id'] ?? 0),
                                        'url' => $slide['background_image_url']
                                    ];
                                }
                                if (!empty($slide['sublayer_images'])) {
                                    foreach ($slide['sublayer_images'] as $sub_img) {
                                        if (!empty($sub_img['src_url'])) {
                                            $images[] = [
                                                'id'  => (int)($sub_img['db_attachment_id'] ?? 0),
                                                'url' => $sub_img['src_url']
                                            ];
                                        }
                                    }
                                }
                            }
                            break;
                        }
                    }
                }

                return $transformer->buildGutenbergGalleryBlock($images, 'layerslider-replaced ekk-carousel', "LayerSlider ID: {$slider_id}");
            },
            $content
        );
    }

    // Scan for Static Gallery Sliders
    if (preg_match_all('/\[gallery[^\]]*type=["\']slideshow["\'][^\]]*\]/i', $content, $gmatches)) {
        $metrics['shortcodes_found']['gallery_slideshow'] += count($gmatches[0]);
        $content = preg_replace_callback(
            '/\[gallery\s+[^\]]*ids=["\']?([0-9,]+)["\']?[^\]]*\]/i',
            function ($gm) use (&$metrics, $transformer) {
                $ids = array_map('intval', explode(',', $gm[1]));
                $metrics['shortcodes_converted']++;
                $images = array_map(function ($id) { return ['id' => $id, 'url' => '']; }, $ids);
                return $transformer->buildGutenbergGalleryBlock($images, 'static-slideshow-converted ekk-carousel', 'Static Gallery Slideshow');
            },
            $content
        );
    }

    return $content;
}

// ----------------------------------------------------------------------
// Phase 3B: Media & Plugin Shortcodes Migration
// ----------------------------------------------------------------------
function step_3b_transform_media_and_plugins($content, &$metrics)
{
    // 1. PDF Embedder ([pdf-embedder url="..."]) - Preserved for native plugin handling
    if (preg_match_all('/\[pdf-embedder[^\]]*\]/i', $content, $pdf_matches)) {
        $metrics['shortcodes_found']['pdf_embedder'] += count($pdf_matches[0]);
    }

    // 2. BeTheme Button Shortcodes ([button title="..." link="..."]) -> core/buttons
    if (preg_match_all('/\[button[^\]]*\]/i', $content, $btn_matches)) {
        $metrics['shortcodes_found']['buttons'] += count($btn_matches[0]);
        $content = preg_replace_callback(
            '/\[button\s+[^\]]*\]/i',
            function ($m) use (&$metrics) {
                $btn_tag = $m[0];
                $title = 'Download';
                $link  = '#';
                if (preg_match('/title=["\']([^"\']*)["\']/i', $btn_tag, $tm)) {
                    if (!empty(trim($tm[1]))) $title = trim($tm[1]);
                }
                if (preg_match('/link=["\']([^"\']*)["\']/i', $btn_tag, $lm)) {
                    if (!empty(trim($lm[1]))) $link = trim($lm[1]);
                }
                $metrics['shortcodes_converted']++;
                return sprintf(
                    '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="%s" target="_blank">%s</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
                    esc_url($link), esc_html($title)
                );
            },
            $content
        );
    }

    // 3. Contact Form 7
    if (preg_match_all('/\[contact-form-7[^\]]*\]/i', $content, $matches)) {
        $metrics['shortcodes_found']['contact_form_7'] += count($matches[0]);
        $content = preg_replace_callback(
            '/\[contact-form-7\s+([^\]]+)\]/i',
            function ($m) use (&$metrics) {
                $metrics['shortcodes_converted']++;
                return '<!-- wp:shortcode -->[contact-form-7 ' . $m[1] . ']<!-- /wp:shortcode -->';
            },
            $content
        );
    }

    // 4. Contact Box shortcode ([contact_box ...])
    $content = preg_replace_callback(
        '/\[contact_box\s+([^\]]+)\]/i',
        function ($m) use (&$metrics) {
            $tag = $m[1];
            $title   = preg_match('/title=["\']([^"\']*)["\']/i', $tag, $tm) ? $tm[1] : '';
            $address = preg_match('/address=["\']([^"\']*)["\']/i', $tag, $am) ? $am[1] : '';
            $tel     = preg_match('/telephone=["\']([^"\']*)["\']/i', $tag, $t1m) ? $t1m[1] : '';
            $email   = preg_match('/email=["\']([^"\']*)["\']/i', $tag, $em) ? $em[1] : '';

            $metrics['shortcodes_converted']++;
            $box_html = '<div class="wp-block-group contact-box-card">';
            if ($title) $box_html .= '<h4>' . esc_html($title) . '</h4>';
            if ($address) $box_html .= '<p>' . nl2br(esc_html($address)) . '</p>';
            if ($tel) $box_html .= '<p>Τηλ: ' . esc_html($tel) . '</p>';
            if ($email) $box_html .= '<p>Email: <a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></p>';
            $box_html .= '</div>';
            return '<!-- wp:group -->' . $box_html . '<!-- /wp:group -->';
        },
        $content
    );

    // 5. Display Posts Shortcode
    if (preg_match_all('/\[display-posts[^\]]*\]/i', $content, $matches)) {
        $metrics['shortcodes_found']['display_posts'] += count($matches[0]);
        $content = preg_replace_callback(
            '/\[display-posts[^\]]*\]/i',
            function ($m) use (&$metrics) {
                $metrics['shortcodes_converted']++;
                return '<!-- wp:query {"queryId":3,"query":{"perPage":4,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false}} -->
<div class="wp-block-query">
<!-- wp:post-template -->
<!-- wp:post-title {"isLink":true} /-->
<!-- wp:post-date /-->
<!-- /wp:post-template -->
</div>
<!-- /wp:query -->';
            },
            $content
        );
    }

    // 6. Map Shortcode
    if (preg_match_all('/\[map[^\]]*\]/i', $content, $matches)) {
        $metrics['shortcodes_found']['map'] += count($matches[0]);
        $content = preg_replace_callback(
            '/\[map\s+[^\]]*?lat=["\']([^"\']+)["\']\s+lng=["\']([^"\']+)["\'][^\]]*\]/i',
            function ($m) use (&$metrics) {
                $lat = esc_attr($m[1]);
                $lng = esc_attr($m[2]);
                $metrics['shortcodes_converted']++;
                return '<!-- wp:html --><iframe src="https://maps.google.com/maps?q=' . $lat . ',' . $lng . '&amp;output=embed" width="100%" height="400" frameborder="0"></iframe><!-- /wp:html -->';
            },
            $content
        );
    }

    // 7. Embed & Video
    if (preg_match_all('/\[(?:embed|video)[^\]]*\]/i', $content, $matches)) {
        $metrics['shortcodes_found']['embed_video'] += count($matches[0]);
        $content = preg_replace_callback(
            '/\[embed[^\]]*\]\s*(https?:\/\/[^\s<]+)\s*\[\/embed\]/i',
            function ($m) use (&$metrics) {
                $url = esc_url(trim($m[1]));
                $metrics['shortcodes_converted']++;
                return '<!-- wp:embed {"url":"' . $url . '","type":"rich","providerNameSlug":"embed"} --><figure class="wp-block-embed"><div class="wp-block-embed__wrapper">' . $url . '</div></figure><!-- /wp:embed -->';
            },
            $content
        );
    }

    return $content;
}

// ----------------------------------------------------------------------
// Phase 3C: WPBakery / Residual Shortcode Clean-Up & Unmapped Audit
// ----------------------------------------------------------------------
function step_3c_transform_residual_and_audit($content, $post_id, $post_title, $post_type, &$metrics)
{
    // Clean legacy column/builder shortcode wrappers
    $content = preg_replace('/\[\/?vc_[^\]]*\]/', '', $content);
    $content = preg_replace('/\[\/?mfn_[^\]]*\]/', '', $content);
    $content = preg_replace('/\[\/?(?:one_half|one_third|two_third|one_fourth|three_fourth|tabs|tab)[^\]]*\]/', '', $content);

    // Known allowed shortcodes wrapped in block comments
    $known_tags = ['wp', 'caption', 'contact-form-7', 'pdf-embedder', 'pdf-embedder-vc', 'gallery', 'image', 'heading', 'paragraph', 'quote', 'list', 'table', 'html', 'embed', 'video', 'file', 'query', 'buttons', 'button', 'if', 'endif'];

    // Scan for unmapped shortcodes outside Gutenberg block comments
    $tokens = preg_split('/(<!--\s+\/?wp:[^>]+-->)/s', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    $in_block = false;

    foreach ($tokens as $token) {
        if (preg_match('/^<!--\s+wp:/s', $token)) {
            $in_block = true;
        } elseif (preg_match('/^<!--\s+\/wp:/s', $token)) {
            $in_block = false;
        } else {
            if (!$in_block) {
                if (preg_match_all('/\[([a-zA-Z0-9_-]+)([^\]]*)\]/s', $token, $m_matches, PREG_SET_ORDER)) {
                    foreach ($m_matches as $mm) {
                        $tag = strtolower($mm[1]);
                        if (!in_array($tag, $known_tags, true)) {
                            log_unmapped_shortcode($post_id, $post_title, $post_type, $mm[0]);
                            $metrics['unmapped_shortcodes_logged']++;
                        }
                    }
                }
            }
        }
    }

    return $content;
}

// ----------------------------------------------------------------------
// Phase 3D: Classic HTML to Gutenberg Block Conversion
// ----------------------------------------------------------------------
function convert_html_elements_to_blocks($html)
{
    $rules = [
        '/<h([1-6])(\s+[^>]*)?>(.*?)<\/h\1>/is' => function ($m) {
            $level = (int) $m[1];
            $tag_html = "<h{$level}>" . strip_tags($m[3], '<a><strong><em><span>') . "</h{$level}>";
            return "<!-- wp:heading {\"level\":{$level}} -->{$tag_html}<!-- /wp:heading -->";
        },
        '/<ul(\s+[^>]*)?>(.*?)<\/ul>/is' => function ($m) {
            return "<!-- wp:list --><ul>{$m[2]}</ul><!-- /wp:list -->";
        },
        '/<ol(\s+[^>]*)?>(.*?)<\/ol>/is' => function ($m) {
            return "<!-- wp:list {\"ordered\":true} --><ol>{$m[2]}</ol><!-- /wp:list -->";
        },
        '/<table(\s+[^>]*)?>(.*?)<\/table>/is' => function ($m) {
            return "<!-- wp:table --><figure class=\"wp-block-table\"><table>{$m[2]}</table></figure><!-- /wp:table -->";
        },
        '/<blockquote(\s+[^>]*)?>(.*?)<\/blockquote>/is' => function ($m) {
            return "<!-- wp:quote --><blockquote class=\"wp-block-quote\">{$m[2]}</blockquote><!-- /wp:quote -->";
        },
        '/<img(\s+[^>]*)?\/?>/is' => function ($m) {
            $img_id = 0;
            if (preg_match('/wp-image-(\d+)/i', $m[1] ?? '', $id_match)) {
                $img_id = (int)$id_match[1];
            }
            $src = '';
            if (preg_match('/src=["\']([^"\']+)["\']/i', $m[1] ?? '', $src_match)) {
                $src = esc_url($src_match[1]);
            }
            $json_attr = $img_id > 0 ? " {\"id\":{$img_id}}" : "";
            return "<!-- wp:image{$json_attr} --><figure class=\"wp-block-image\"><img src=\"{$src}\" alt=\"\" class=\"wp-image-{$img_id}\"/></figure><!-- /wp:image -->";
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

function convert_html_to_blocks($content)
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

// Load LayerSlider database inventory for slide image resolution
$slider_scoping_file = $base_dir . '/ai-work/scopings/detailed-sliders-inventory.json';
$db_sliders = [];
if (file_exists($slider_scoping_file)) {
    $inventory_data = json_decode(file_get_contents($slider_scoping_file), true);
    $db_sliders = $inventory_data['all_database_layersliders'] ?? [];
}

// ----------------------------------------------------------------------
// Main Migration Loop
// ----------------------------------------------------------------------
$sql = "SELECT ID, post_title, post_type, post_content FROM wp_posts WHERE post_type IN ('page', 'post') AND post_status IN ('publish', 'draft', 'private', 'pending', 'future')";
$res = $mysqli->query($sql);

if (!$res) {
    engine_log("Database query failed: " . $mysqli->error, "ERROR");
    exit(1);
}

$front_page_id = (int)get_option('page_on_front');
$transformer = new \EkaAlexandria\Migration\Content\ContentTransformer();

while ($row = $res->fetch_assoc()) {
    $id         = (int)$row['ID'];
    $title      = $row['post_title'];
    $type       = $row['post_type'];
    $orig_body  = $row['post_content'];
    $is_front   = ($id === $front_page_id);

    if ($type === 'page') $metrics['pages_count']++;
    if ($type === 'post') $metrics['posts_count']++;

    // 1. Check for Muffin Builder postmeta ('mfn-page-items')
    $stmt_mfn = $mysqli->prepare("SELECT meta_value FROM wp_postmeta WHERE post_id = ? AND meta_key = 'mfn-page-items'");
    $mfn_raw = '';
    if ($stmt_mfn) {
        $stmt_mfn->bind_param("i", $id);
        if ($stmt_mfn->execute()) {
            $res_mfn = $stmt_mfn->get_result();
            if ($r_mfn = $res_mfn->fetch_assoc()) {
                $mfn_raw = $r_mfn['meta_value'];
            }
        }
        $stmt_mfn->close();
    }

    $content = $orig_body;

    if (!empty($mfn_raw)) {
        $metrics['mfn_pages_scanned']++;
        $mfn_html = $transformer->transformMfnBuilder($mfn_raw, $is_front);
        if (!empty(trim($mfn_html))) {
            $content = $mfn_html;
            $metrics['mfn_pages_converted']++;
        }
    }

    // 2. Step 3A: Sliders (LayerSliders & RevSliders)
    $content = $transformer->transformLayerSliders($content, $id, $db_sliders);
    $content = $transformer->transformRevSliders($content, $id);

    // 3. Step 3B: Media & Plugin Shortcodes
    $content = step_3b_transform_media_and_plugins($content, $metrics);

    // 4. Step 3C: Residual Clean-Up & Unmapped Audit
    $content = step_3c_transform_residual_and_audit($content, $id, $title, $type, $metrics);

    // 5. Step 3D: Convert Classic HTML to Gutenberg Blocks
    $content = convert_html_to_blocks($content);

    // Skip if content unchanged
    if (trim($content) === trim($orig_body)) {
        $metrics['skipped_unchanged']++;
        continue;
    }

    // AST Validation
    if (!eka_validate_blocks_ast($content)) {
        engine_log("AST Validation failed for Post ID {$id} ('{$title}'). Skipping update.", "WARNING");
        $metrics['failed_ast_validation']++;
        $metrics['failed_post_ids'][] = $id;
        continue;
    }

    // Update wp_posts
    $stmt_upd = $mysqli->prepare("UPDATE wp_posts SET post_content = ? WHERE ID = ?");
    if ($stmt_upd) {
        $stmt_upd->bind_param("si", $content, $id);
        if ($stmt_upd->execute()) {
            $metrics['successfully_converted']++;
        } else {
            engine_log("Failed to update Post ID {$id}: " . $stmt_upd->error, "ERROR");
            $metrics['failed_ast_validation']++;
            $metrics['failed_post_ids'][] = $id;
        }
        $stmt_upd->close();
    }
}

// ----------------------------------------------------------------------
// Detailed Migration Metrics Report Log
// ----------------------------------------------------------------------
engine_log("==========================================");
engine_log(" DETAILED MIGRATION METRICS REPORT");
engine_log("==========================================");
engine_log(" Total Items Scanned       : " . $metrics['total_scanned']);
engine_log("   - Pages Scanned         : " . $metrics['pages_count']);
engine_log("   - Posts Scanned         : " . $metrics['posts_count']);
engine_log(" MFN Builder Pages Scanned : " . $metrics['mfn_pages_scanned']);
engine_log(" MFN Builder Pages Converted: " . $metrics['mfn_pages_converted']);
engine_log(" Shortcodes Found by Type:");
engine_log("   - LayerSliders          : " . $metrics['shortcodes_found']['layerslider']);
engine_log("   - Static Gallery Sliders: " . $metrics['shortcodes_found']['gallery_slideshow']);
engine_log("   - PDF Embedders         : " . $metrics['shortcodes_found']['pdf_embedder']);
engine_log("   - Button Shortcodes     : " . $metrics['shortcodes_found']['buttons']);
engine_log("   - Contact Form 7        : " . $metrics['shortcodes_found']['contact_form_7']);
engine_log("   - Display Posts         : " . $metrics['shortcodes_found']['display_posts']);
engine_log("   - Google Maps           : " . $metrics['shortcodes_found']['map']);
engine_log("   - Embeds & Video        : " . $metrics['shortcodes_found']['embed_video']);
engine_log(" Total Shortcodes Converted : " . $metrics['shortcodes_converted']);
engine_log(" Unmapped Shortcodes Logged: " . $metrics['unmapped_shortcodes_logged']);
engine_log(" Successfully Converted    : " . $metrics['successfully_converted']);
engine_log(" Skipped / Unchanged       : " . $metrics['skipped_unchanged']);
engine_log(" Failed AST Validation     : " . $metrics['failed_ast_validation']);
if (!empty($metrics['failed_post_ids'])) {
    engine_log(" Failed Post IDs           : [" . implode(', ', $metrics['failed_post_ids']) . "]");
} else {
    engine_log(" Failed Post IDs           : []");
}
engine_log("==========================================");

$mysqli->close();
engine_log("Stage 02 Content Engine Execution Completed Successfully.");
