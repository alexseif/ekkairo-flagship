<?php

namespace EkaAlexandria\Migration\Content;

use EkaAlexandria\Migration\Utils\StyleSanitizer;

class ContentTransformer
{
    /**
     * Parses fractional width strings (e.g. "1/2", "1/3", "3/4") to percentages ("50%", "33.33%").
     */
    public function parseFractionWidth(string $widthStr): string
    {
        $widthStr = trim($widthStr);
        if (empty($widthStr)) {
            return '100%';
        }
        if (strpos($widthStr, '/') !== false) {
            $parts = explode('/', $widthStr);
            $num = (float)$parts[0];
            $den = (float)$parts[1];
            if ($den > 0) {
                $pct = round(($num / $den) * 100, 2);
                return $pct . '%';
            }
        }
        if (is_numeric(rtrim($widthStr, '%'))) {
            return rtrim($widthStr, '%') . '%';
        }
        return '100%';
    }

    /**
     * Filters all inline CSS in HTML fragment using FSE allowlist.
     */
    public function cleanHtmlInlineStyles(string $html): string
    {
        return preg_replace_callback(
            '/\s+style=["\']([^"\']*)["\']/i',
            function ($matches) {
                $rawStyle = $matches[1];
                $cleanStyle = StyleSanitizer::sanitizeInlineStyles($rawStyle);
                if (empty($cleanStyle)) {
                    return '';
                }
                return ' style="' . htmlspecialchars($cleanStyle, ENT_QUOTES, 'UTF-8') . '"';
            },
            $html
        );
    }

    /**
     * Specialized Front Page Content Extractor (IDs: 13236, 16894, 16892)
     */
    public function processFrontPageContent(string $content, int $postId): string
    {
        // 1. Remove slider shortcodes ([layerslider ...], [rev_slider ...], [rev_slider_vc ...])
        $content = preg_replace('/\[(?:rev_slider|rev_slider_vc|layerslider)[^\]]*\]/i', '', $content);

        // 2. Remove vc_posts_grid shortcodes
        $content = preg_replace('/\[vc_posts_grid[^\]]*\]/i', '', $content);

        // 3. Extract text from inside [vc_column_text]...[/vc_column_text] if present
        if (preg_match('/\[vc_column_text[^\]]*\](.*?)\[\/vc_column_text\]/is', $content, $matches)) {
            $content = $matches[1];
        }

        return $content;
    }

    /**
     * Constructs Gutenberg wp:gallery block with nested wp:image blocks.
     */
    public function buildGutenbergGalleryBlock(array $images, string $extraClass = 'rev-slider-replaced', string $fallbackTitle = 'Slider', $mysqli = null): string
    {
        $validImages = [];
        foreach ($images as $img) {
            if (!empty($img['id']) || !empty($img['url'])) {
                $validImages[] = $img;
            }
        }

        if (empty($validImages)) {
            return '<!-- wp:gallery {"className":"' . $extraClass . '"} --><figure class="wp-block-gallery has-nested-images columns-default is-cropped ' . $extraClass . '"><!-- wp:paragraph --><p>' . htmlspecialchars($fallbackTitle, ENT_QUOTES, 'UTF-8') . '</p><!-- /wp:paragraph --></figure><!-- /wp:gallery -->';
        }

        $imageIds = [];
        $innerBlocksHtml = '';

        foreach ($validImages as $img) {
            $id = isset($img['id']) ? (int)$img['id'] : 0;
            $rawUrl = isset($img['url']) ? trim($img['url']) : '';

            if ($id > 0 && empty($rawUrl) && $mysqli instanceof \mysqli) {
                $stmt = $mysqli->prepare("SELECT guid FROM wp_posts WHERE ID = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $res = $stmt->get_result();
                        if ($row = $res->fetch_assoc()) {
                            $rawUrl = $row['guid'];
                        }
                    }
                    $stmt->close();
                }
            }

            $url = htmlspecialchars($rawUrl, ENT_QUOTES, 'UTF-8');
            if ($id > 0) {
                $imageIds[] = $id;
            }

            $idAttrJson = $id > 0 ? '"id":' . $id . ',' : '';

            $innerBlocksHtml .= '<!-- wp:image {' . $idAttrJson . '"sizeSlug":"full","linkDestination":"none"} -->';
            $innerBlocksHtml .= '<figure class="wp-block-image"><img src="' . $url . '" alt=""/></figure>';
            $innerBlocksHtml .= '<!-- /wp:image -->';
        }

        $galleryAttrs = [
            'columns' => 1,
            'ids' => $imageIds,
            'linkTo' => 'none',
            'sizeSlug' => 'full',
            'className' => $extraClass,
        ];
        $attrsJson = json_encode($galleryAttrs, JSON_UNESCAPED_SLASHES);

        $html = '<!-- wp:gallery ' . $attrsJson . ' -->';
        $html .= '<figure class="wp-block-gallery has-nested-images columns-1 is-cropped ' . $extraClass . '">';
        $html .= $innerBlocksHtml;
        $html .= '</figure>';
        $html .= '<!-- /wp:gallery -->';

        return $html;
    }

    /**
     * Step 3A: Transform Revolution / Layer Sliders to FSE Gallery Blocks
     */
    public function transformSliders(string $content, int $postId, array $scopingMap = [], $mysqli = null): string
    {
        // Strip outer <p> wrappers around slider shortcodes
        $content = preg_replace('/<p[^>]*>\s*(\[(?:rev_slider|rev_slider_vc|layerslider)\s+[^\]]+\])\s*<\/p>/is', '$1', $content);

        return preg_replace_callback('/\[(?:rev_slider|rev_slider_vc|layerslider)\s+([^\]]*)\]/i', function ($m) use ($postId, $scopingMap, $mysqli) {
            $attrStr = $m[1];
            $alias = '';
            $title = 'Slider';

            if (preg_match('/(?:alias|title|id)=["\']([^"\']+)["\']/i', $attrStr, $am)) {
                $alias = $am[1];
                $title = 'Slider (' . $alias . ')';
            } elseif (preg_match('/^\s*([a-zA-Z0-9_-]+)/', $attrStr, $am)) {
                $alias = $am[1];
                $title = 'Slider (' . $alias . ')';
            }

            $images = eka_resolve_slider_images_by_alias($alias, $postId, $scopingMap, $mysqli);
            return $this->buildGutenbergGalleryBlock($images, 'rev-slider-replaced', $title, $mysqli);
        }, $content);
    }

    /**
     * Step 3B: Transform Testimonials to Gutenberg Quote blocks
     */
    public function transformTestimonials(string $content): string
    {
        // Un-nest [testimonial_set]
        $content = preg_replace('/\[\/?testimonial_set[^\]]*\]/i', '', $content);

        // Convert [testimonial client="..."]Quote text[/testimonial]
        $content = preg_replace_callback('/\[testimonial(?:\s+[^\]]*)?\](.*?)\[\/testimonial\]/is', function ($m) {
            $inner = trim($m[1]);
            $client = '';
            if (preg_match('/client=["\']([^"\']+)["\']/i', $m[0], $cm)) {
                $client = trim($cm[1]);
            }
            $citeHtml = !empty($client) ? "<cite>{$client}</cite>" : "";
            return "<!-- wp:quote --><blockquote class=\"wp-block-quote\"><p>{$inner}</p>{$citeHtml}</blockquote><!-- /wp:quote -->";
        }, $content);

        return $content;
    }

    /**
     * Step 3C: Transform [vc_posts_grid] to Gutenberg wp:query blocks
     */
    public function transformVcPostsGrid(string $content, int $postId = 0): string
    {
        return preg_replace_callback('/\[vc_posts_grid\s+([^\]]*)\]/i', function ($m) use ($postId) {
            $attrStr = $m[1];
            $gridColumns = 2;
            if (preg_match('/grid_columns_count=["\']?(\d+)["\']?/i', $attrStr, $cm)) {
                $gridColumns = max(1, (int)$cm[1]);
            }

            $title = '';
            if (preg_match('/title=["\']([^"\']+)["\']/i', $attrStr, $tm)) {
                $title = trim($tm[1]);
            }

            $postType = 'post';
            if (preg_match('/post_type:page/i', $attrStr) || preg_match('/post_type=["\']?page["\']?/i', $attrStr)) {
                $postType = 'page';
            } elseif (preg_match('/post_type:([a-zA-Z0-9_-]+)/i', $attrStr, $ptm)) {
                $postType = strtolower($ptm[1]);
            }

            $order = 'desc';
            if (preg_match('/order:ASC/i', $attrStr) || preg_match('/order=["\']?asc["\']?/i', $attrStr)) {
                $order = 'asc';
            }

            $orderBy = 'date';
            if (preg_match('/order_by:menu_order/i', $attrStr) || preg_match('/order_by=["\']?menu_order["\']?/i', $attrStr)) {
                $orderBy = 'menu_order';
            }

            $perPage = 50;
            if (preg_match('/size:(\d+)/i', $attrStr, $sm)) {
                $perPage = max(1, (int)$sm[1]);
            }

            $includeIds = [];
            if (preg_match('/by_id:([0-9,]+)/i', $attrStr, $idm)) {
                $includeIds = array_map('intval', explode(',', $idm[1]));
            }

            $tagIds = [];
            if (preg_match('/tags:([0-9,]+)/i', $attrStr, $tagm)) {
                $tagIds = array_map('intval', explode(',', $tagm[1]));
            }

            $catIds = [];
            if (preg_match('/categories:([0-9,]+)/i', $attrStr, $catm)) {
                $catIds = array_map('intval', explode(',', $catm[1]));
            }

            $parents = ($postType === 'page' && $postId > 0) ? [$postId] : [];

            $queryObj = [
                'perPage' => !empty($includeIds) ? max($perPage, count($includeIds)) : $perPage,
                'pages' => 0,
                'offset' => 0,
                'postType' => $postType,
                'order' => $order,
                'orderBy' => $orderBy,
                'author' => '',
                'search' => '',
                'exclude' => [],
                'sticky' => '',
                'inherit' => false,
            ];

            if (!empty($parents)) {
                $queryObj['parents'] = $parents;
            }
            if (!empty($includeIds)) {
                $queryObj['include'] = $includeIds;
            }
            if (!empty($tagIds)) {
                $queryObj['taxQuery'] = ['include' => ['post_tag' => $tagIds]];
            } elseif (!empty($catIds)) {
                $queryObj['taxQuery'] = ['include' => ['category' => $catIds]];
            }

            $queryAttrs = [
                'queryId' => rand(100, 999),
                'query' => $queryObj,
            ];
            $queryJson = json_encode($queryAttrs, JSON_UNESCAPED_SLASHES);

            $postTemplateAttrs = [
                'layout' => [
                    'type' => ($postType === 'page' || $gridColumns > 1) ? 'grid' : 'default',
                    'columnCount' => $gridColumns,
                ],
            ];
            $postTemplateJson = json_encode($postTemplateAttrs, JSON_UNESCAPED_SLASHES);

            $headingBlock = '';
            if (!empty($title)) {
                $cleanTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
                $headingBlock = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">{$cleanTitle}</h2>\n<!-- /wp:heading -->\n\n";
            }

            $titleFirst = false;
            if (preg_match('/grid_layout=["\']?title/i', $attrStr)) {
                $titleFirst = true;
            }

            $paginationBlock = "<!-- wp:query-pagination {\"paginationArrow\":\"chevron\",\"layout\":{\"type\":\"flex\",\"justifyContent\":\"space-between\"}} -->\n<!-- wp:query-pagination-previous /-->\n<!-- wp:query-pagination-numbers /-->\n<!-- wp:query-pagination-next /-->\n<!-- /wp:query-pagination -->";

            if ($postType === 'page') {
                $imgAttrs = [
                    'isLink' => true,
                    'aspectRatio' => '3/2',
                    'style' => [
                        'border' => [
                            'radius' => [
                                'topLeft' => '12px',
                                'topRight' => '12px',
                                'bottomLeft' => '12px',
                                'bottomRight' => '12px',
                            ],
                        ],
                    ],
                ];
                $imgJson = json_encode($imgAttrs, JSON_UNESCAPED_SLASHES);

                $titleAttrs = [
                    'isLink' => true,
                    'level' => 3,
                ];
                $titleJson = json_encode($titleAttrs, JSON_UNESCAPED_SLASHES);

                $block = $headingBlock;
                $block .= "<!-- wp:query {$queryJson} -->\n";
                $block .= "<div class=\"wp-block-query\">\n";
                $block .= "<!-- wp:post-template {$postTemplateJson} -->\n";
                $block .= "<!-- wp:post-featured-image {$imgJson} /-->\n";
                $block .= "<!-- wp:post-title {$titleJson} /-->\n";
                $block .= "<!-- /wp:post-template -->\n";
                $block .= "</div>\n";
                $block .= "<!-- /wp:query -->";
            } elseif ($titleFirst) {
                $imgAttrs = [
                    'isLink' => true,
                    'width' => '25vw',
                    'sizeSlug' => 'full',
                ];
                $imgJson = json_encode($imgAttrs, JSON_UNESCAPED_SLASHES);

                $block = $headingBlock;
                $block .= "<!-- wp:query {$queryJson} -->\n";
                $block .= "<div class=\"wp-block-query\">\n";
                $block .= "<!-- wp:post-template {$postTemplateJson} -->\n";
                $block .= "<!-- wp:post-title {\"isLink\":true} /-->\n\n";
                $block .= "<!-- wp:post-featured-image {$imgJson} /-->\n";
                $block .= "<!-- /wp:post-template -->\n\n";
                $block .= $paginationBlock . "\n";
                $block .= "</div>\n";
                $block .= "<!-- /wp:query -->";
            } else {
                $block = $headingBlock;
                $block .= "<!-- wp:query {$queryJson} -->\n";
                $block .= "<div class=\"wp-block-query\">\n";
                $block .= "  <!-- wp:post-template {$postTemplateJson} -->\n";
                $block .= "    <!-- wp:post-title {\"isLink\":true} /-->\n";
                $block .= "    <!-- wp:post-featured-image /-->\n";
                $block .= "    <!-- wp:post-excerpt /-->\n";
                $block .= "  <!-- /wp:post-template -->\n";
                $block .= "</div>\n";
                $block .= "<!-- /wp:query -->";
            }

            return $block;
        }, $content);
    }

    /**
     * Step 3G: Transform Media & Plugin Shortcodes ([gallery], [caption], [pdf-embedder], etc.)
     */
    public function transformMediaAndPlugins(string $content): string
    {
        // 1. [pdf-embedder url="..."] -> wp:file block
        $content = preg_replace_callback('/\[pdf-embedder\s+url=["\']([^"\']+)["\'][^\]]*\]/i', function ($m) {
            $url = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            $filename = basename(parse_url($url, PHP_URL_PATH));
            return '<!-- wp:file {"href":"' . $url . '"} --><div class="wp-block-file"><a href="' . $url . '">' . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') . '</a><a href="' . $url . '" class="wp-block-file__button wp-element-button" download>Download</a></div><!-- /wp:file -->';
        }, $content);

        // 2. [gallery ids="1,2,3"] -> wp:gallery block
        $content = preg_replace_callback('/\[gallery\s+ids=["\']([^"\']+)["\'][^\]]*\]/i', function ($m) {
            $ids = array_map('intval', explode(',', $m[1]));
            $images = [];
            foreach ($ids as $id) {
                if ($id > 0) {
                    $images[] = ['id' => $id, 'url' => ''];
                }
            }
            return $this->buildGutenbergGalleryBlock($images, 'wp-block-gallery-converted');
        }, $content);

        // 3. [wp_caption] or [caption] -> figure/figcaption wrapper
        $content = $this->transformCaptionShortcode($content);

        // 4. [hr] -> wp:spacer / wp:separator block markup
        $content = $this->transformHrShortcode($content);

        // 5. [map ...] -> iframe, remove [/map]
        $content = preg_replace_callback('/\[map\s+[^\]]*?lat=["\']([^"\']+)["\']\s+lng=["\']([^"\']+)["\'][^\]]*\]/i', function ($m) {
            $lat = $m[1];
            $lng = $m[2];
            return '<!-- wp:html --><iframe src="https://maps.google.com/maps?q=' . $lat . ',' . $lng . '&amp;output=embed" width="100%" height="400" frameborder="0"></iframe><!-- /wp:html -->';
        }, $content);
        $content = preg_replace('/\[\/map\]/i', '', $content);

        return $content;
    }

    /**
     * Transform standard WordPress [caption] shortcodes into Gutenberg wp:image blocks.
     */
    public function transformCaptionShortcode(string $content): string
    {
        return preg_replace_callback('/\[(?:wp_)?caption\s+([^\]]*)\](.*?)\[\/(?:wp_)?caption\]/is', function ($m) {
            $attrStr = $m[1];
            $inner = trim($m[2]);

            $imgId = 0;
            if (preg_match('/id=["\']?attachment_(\d+)["\']?/i', $attrStr, $idm)) {
                $imgId = (int)$idm[1];
            }

            $align = '';
            if (preg_match('/align=["\']?(align[a-z]+|[a-z]+)["\']?/i', $attrStr, $am)) {
                $alignVal = strtolower($am[1]);
                if (strpos($alignVal, 'center') !== false) {
                    $align = 'center';
                } elseif (strpos($alignVal, 'left') !== false) {
                    $align = 'left';
                } elseif (strpos($alignVal, 'right') !== false) {
                    $align = 'right';
                }
            }

            $linkUrl = '';
            $imgTag = '';
            $captionText = '';

            // Check if inner content has a link <a><img .../></a>
            if (preg_match('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>\s*(<img\s+[^>]*\/?>)\s*<\/a>\s*(.*)/is', $inner, $linkm)) {
                $linkUrl = $linkm[1];
                $imgTag = $linkm[2];
                $captionText = trim(strip_tags($linkm[3]));
            } elseif (preg_match('/(<img\s+[^>]*\/?>)\s*(.*)/is', $inner, $imgm)) {
                $imgTag = $imgm[1];
                $captionText = trim(strip_tags($imgm[2]));
            } else {
                $imgTag = StyleSanitizer::cleanImageTag($inner);
            }

            if ($imgId <= 0 && preg_match('/wp-image-(\d+)/i', $imgTag, $idm2)) {
                $imgId = (int)$idm2[1];
            }

            $cleanImg = StyleSanitizer::cleanImageTag($imgTag);

            $figClasses = ['wp-block-image'];
            if ($align === 'center') {
                $figClasses[] = 'aligncenter';
            } elseif ($align === 'left') {
                $figClasses[] = 'alignleft';
            } elseif ($align === 'right') {
                $figClasses[] = 'alignright';
            }

            $blockAttrs = [];
            if ($imgId > 0) {
                $blockAttrs['id'] = $imgId;
            }
            if (!empty($linkUrl)) {
                $blockAttrs['linkDestination'] = 'custom';
            }
            if (!empty($align)) {
                $blockAttrs['align'] = $align;
            }

            $jsonAttr = !empty($blockAttrs) ? ' ' . json_encode($blockAttrs, JSON_UNESCAPED_SLASHES) : '';

            $imgInner = $cleanImg;
            if (!empty($linkUrl)) {
                $linkEsc = htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8');
                $imgInner = '<a href="' . $linkEsc . '">' . $cleanImg . '</a>';
            }

            $figcaption = '';
            if (!empty($captionText)) {
                $figcaption = '<figcaption class="wp-element-caption">' . htmlspecialchars($captionText, ENT_QUOTES, 'UTF-8') . '</figcaption>';
            }

            return '<!-- wp:image' . $jsonAttr . ' --><figure class="' . implode(' ', $figClasses) . '">' . $imgInner . $figcaption . '</figure><!-- /wp:image -->';
        }, $content);
    }

    /**
     * Transforms [vc_single_image] shortcodes into Gutenberg wp:heading (Option 1) and wp:image blocks.
     */
    public function transformVcSingleImage(string $content, $mysqli = null): string
    {
        return preg_replace_callback('/\[vc_single_image\s+([^\]]*)\]/i', function ($m) use ($mysqli) {
            $attrStr = $m[1];
            $attrs = [];
            if (preg_match_all('/(\w+)=["\']([^"\']*)["\']/i', $attrStr, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $attrs[strtolower($match[1])] = html_entity_decode($match[2], ENT_QUOTES, 'UTF-8');
                }
            }

            $imgId = isset($attrs['image']) ? (int)$attrs['image'] : 0;
            $title = isset($attrs['title']) ? trim($attrs['title']) : '';
            $link = isset($attrs['link']) ? trim($attrs['link']) : '';
            $target = isset($attrs['img_link_target']) ? trim($attrs['img_link_target']) : '';
            $align = isset($attrs['alignment']) ? trim($attrs['alignment']) : (isset($attrs['align']) ? trim($attrs['align']) : '');

            // Resolve image URL from DB if possible
            $imgUrl = '';
            if ($imgId > 0 && $mysqli instanceof \mysqli) {
                $stmt = $mysqli->prepare("SELECT guid FROM wp_posts WHERE ID = ? AND post_type = 'attachment'");
                if (!$stmt) {
                    $stmt = $mysqli->prepare("SELECT guid FROM wp_posts WHERE ID = ?");
                }
                if ($stmt) {
                    $stmt->bind_param("i", $imgId);
                    if ($stmt->execute()) {
                        $res = $stmt->get_result();
                        if ($row = $res->fetch_assoc()) {
                            $imgUrl = $row['guid'];
                        }
                    }
                    $stmt->close();
                }
            }

            // Heading block (Option 1: title above image)
            $headingBlock = '';
            if (!empty($title)) {
                $cleanTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
                if ($align === 'center') {
                    $headingBlock = '<!-- wp:heading {"level":3,"textAlign":"center"} --><h3 class="wp-block-heading has-text-align-center">' . $cleanTitle . '</h3><!-- /wp:heading -->';
                } else {
                    $headingBlock = '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . $cleanTitle . '</h3><!-- /wp:heading -->';
                }
            }

            // Image block
            $figClasses = ['wp-block-image'];
            if ($align === 'center') {
                $figClasses[] = 'aligncenter';
            } elseif ($align === 'left') {
                $figClasses[] = 'alignleft';
            } elseif ($align === 'right') {
                $figClasses[] = 'alignright';
            }

            $imgAlt = !empty($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : '';
            $imgClassAttr = $imgId > 0 ? ' class="wp-image-' . $imgId . '"' : '';

            $imgTag = '<img src="' . htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . $imgAlt . '"' . $imgClassAttr . '/>';

            if (!empty($link)) {
                $linkEsc = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
                $targetAttr = ($target === '_blank') ? ' target="_blank" rel="noreferrer noopener"' : '';
                $imgInner = '<a href="' . $linkEsc . '"' . $targetAttr . '>' . $imgTag . '</a>';
            } else {
                $imgInner = $imgTag;
            }

            $blockAttrs = [];
            if ($imgId > 0) {
                $blockAttrs['id'] = $imgId;
            }
            $blockAttrs['sizeSlug'] = 'full';
            if (!empty($link)) {
                $blockAttrs['linkDestination'] = 'custom';
            }
            if (!empty($align)) {
                $blockAttrs['align'] = $align;
            }

            $jsonAttrs = !empty($blockAttrs) ? ' ' . json_encode($blockAttrs, JSON_UNESCAPED_SLASHES) : '';
            $imageBlock = '<!-- wp:image' . $jsonAttrs . ' --><figure class="' . implode(' ', $figClasses) . '">' . $imgInner . '</figure><!-- /wp:image -->';

            if (!empty($headingBlock)) {
                return $headingBlock . "\n" . $imageBlock;
            }

            return $imageBlock;
        }, $content);
    }

    /**
     * Step 3D: Transform WPBakery layout shortcodes ([vc_row], [vc_column], [vc_column_text], etc.)
     */
    public function transformWpbakeryAndCaption(string $content, $mysqli = null): string
    {
        // 0. Convert [vc_single_image]
        $content = $this->transformVcSingleImage($content, $mysqli);

        // 1. Convert [vc_column_text]...[/vc_column_text]
        $content = preg_replace_callback('/\[vc_column_text[^\]]*\](.*?)\[\/vc_column_text\]/is', function ($m) {
            $inner = trim($m[1]);
            return $this->convertHtmlElementsToBlocks($inner);
        }, $content);

        // 2. Handle [vc_row] and [vc_column]
        $content = preg_replace_callback('/\[vc_row[^\]]*\](.*?)\[\/vc_row\]/is', function ($m) {
            $rowInner = trim($m[1]);

            preg_match_all('/\[vc_column\s*([^\]]*)\](.*?)\[\/vc_column\]/is', $rowInner, $cols, PREG_SET_ORDER);

            if (empty($cols)) {
                return $rowInner;
            }

            // Single 1/1 column row unwraps directly without outer wp:columns block wrapper
            if (count($cols) === 1) {
                $colAttrs = $cols[0][1];
                $colContent = trim($cols[0][2]);
                $widthStr = '100%';
                if (preg_match('/width=["\']([^"\']+)["\']/i', $colAttrs, $wm)) {
                    $widthStr = $this->parseFractionWidth($wm[1]);
                }
                if ($widthStr === '100%') {
                    $trimmedCol = trim($colContent);
                    if (preg_match('/^<!--\s*wp:/i', $trimmedCol)) {
                        return $trimmedCol;
                    }
                    return $this->convertHtmlElementsToBlocks($trimmedCol);
                }
            }

            $columnsHtml = '';
            foreach ($cols as $col) {
                $colAttrs = $col[1];
                $colContent = trim($col[2]);

                $widthPct = '100%';
                if (preg_match('/width=["\']([^"\']+)["\']/i', $colAttrs, $wm)) {
                    $widthPct = $this->parseFractionWidth($wm[1]);
                }

                $columnsHtml .= '<!-- wp:column {"width":"' . $widthPct . '"} -->';
                $columnsHtml .= '<div class="wp-block-column" style="flex-basis:' . $widthPct . ';">';
                $columnsHtml .= $colContent;
                $columnsHtml .= '</div>';
                $columnsHtml .= '<!-- /wp:column -->';
            }

            return '<!-- wp:columns --><div class="wp-block-columns">' . $columnsHtml . '</div><!-- /wp:columns -->';
        }, $content);

        return $content;
    }

    /**
     * Step 3E: Transform Residual Legacy Shortcodes ([vc_empty_space], [vc_custom_heading], [vc_separator], etc.)
     */
    public function transformResidualShortcodes(string $content): string
    {
        // 1. [vc_empty_space height="32px"] -> wp:spacer
        $content = preg_replace_callback('/\[vc_empty_space\s*([^\]]*)\]/i', function ($m) {
            $height = '32px';
            if (preg_match('/height=["\']?(\d+px|\d+rem|\d+)["\']?/i', $m[1], $hm)) {
                $height = is_numeric($hm[1]) ? $hm[1] . 'px' : $hm[1];
            }
            return '<!-- wp:spacer {"height":"' . $height . '"} --><div style="height:' . $height . '" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';
        }, $content);

        // 2. [vc_custom_heading text="..." font_container="tag:h2|..."] -> wp:heading
        $content = preg_replace_callback('/\[vc_custom_heading\s*([^\]]*)\]/i', function ($m) {
            $attrStr = $m[1];
            $text = '';
            $tag = 'h2';

            if (preg_match('/text=["\']([^"\']+)["\']/i', $attrStr, $tm)) {
                $text = $tm[1];
            }
            if (preg_match('/font_container=["\']([^"\']+)["\']/i', $attrStr, $fcm)) {
                if (preg_match('/tag:(h[1-6])/i', $fcm[1], $tagm)) {
                    $tag = strtolower($tagm[1]);
                }
            }

            $level = (int)substr($tag, 1);
            return '<!-- wp:heading {"level":' . $level . '} -->' . "<{$tag}>" . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . "</{$tag}>" . '<!-- /wp:heading -->';
        }, $content);

        // 3. [hr ...] shortcodes -> wp:spacer / wp:separator
        $content = $this->transformHrShortcode($content);

        // 4. [vc_separator ...] or [vc_text_separator ...] -> wp:separator
        $content = preg_replace('/\[vc_(?:text_)?separator[^\]]*\]/i', '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->', $content);

        // 5. Strip leftover unhandled shortcodes
        $content = preg_replace('/\[\/?vc_[a-z0-9_-]+[^\]]*\]/i', '', $content);
        $content = preg_replace('/\[[a-z0-9_-]+_shortcode[^\]]*\]/i', '', $content);

        return $content;
    }

    /**
     * Transform BeTheme / generic [hr ...] shortcodes into Gutenberg-friendly wp:spacer / wp:separator block markup.
     */
    public function transformHrShortcode(string $content): string
    {
        return preg_replace_callback('/\[hr\s*([^\]]*)\]/i', function ($m) {
            $attrStr = isset($m[1]) ? $m[1] : '';

            $height = 0;
            if (preg_match('/height=["\']?(\d+)["\']?/i', $attrStr, $hm)) {
                $height = (int)$hm[1];
            }

            $line = 'default';
            if (preg_match('/line=["\']?([^"\']+)["\']/i', $attrStr, $lm)) {
                $line = strtolower(trim($lm[1]));
            }

            $style = 'default';
            if (preg_match('/style=["\']?([^"\']+)["\']/i', $attrStr, $sm)) {
                $style = strtolower(trim($sm[1]));
            }

            // If line is 'no_line' or style is 'margin' -> output pure wp:spacer
            if ($line === 'no_line' || $style === 'margin') {
                $h = $height > 0 ? $height : 30;
                return "<!-- wp:spacer {\"height\":\"{$h}px\"} -->\n<div style=\"height:{$h}px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer -->";
            }

            // If style is 'dots'
            if ($style === 'dots') {
                return "<!-- wp:separator {\"className\":\"is-style-dots\"} -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity is-style-dots\"/>\n<!-- /wp:separator -->";
            }

            // Default: wp:separator (with spacer if height > 0)
            $sep = "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->";
            if ($height > 0) {
                $spacer = "<!-- wp:spacer {\"height\":\"{$height}px\"} -->\n<div style=\"height:{$height}px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer -->";
                return $spacer . "\n" . $sep;
            }

            return $sep;
        }, $content);
    }

    /**
     * Converts classic HTML block elements into Gutenberg block markup.
     */
    public function convertHtmlElementsToBlocks(string $html): string
    {
        // 1. Headings: <h1-6>
        $html = preg_replace_callback(
            '/<h([1-6])(\s+[^>]*)?>(.*?)<\/h\1>/is',
            function ($matches) {
                $level = (int)$matches[1];
                $attrs = isset($matches[2]) ? $matches[2] : '';
                $inner = $matches[3];
                $tagHtml = $this->cleanHtmlInlineStyles("<h{$level}{$attrs}>{$inner}</h{$level}>");
                return "<!-- wp:heading {\"level\":{$level}} -->{$tagHtml}<!-- /wp:heading -->";
            },
            $html
        );

        // 2. Unordered lists: <ul>
        $html = preg_replace_callback(
            '/<ul(\s+[^>]*)?>(.*?)<\/ul>/is',
            function ($matches) {
                $attrs = isset($matches[1]) ? $matches[1] : '';
                $inner = $matches[2];
                $tagHtml = $this->cleanHtmlInlineStyles("<ul{$attrs}>{$inner}</ul>");
                return "<!-- wp:list -->{$tagHtml}<!-- /wp:list -->";
            },
            $html
        );

        // 3. Ordered lists: <ol>
        $html = preg_replace_callback(
            '/<ol(\s+[^>]*)?>(.*?)<\/ol>/is',
            function ($matches) {
                $attrs = isset($matches[1]) ? $matches[1] : '';
                $inner = $matches[2];
                $tagHtml = $this->cleanHtmlInlineStyles("<ol{$attrs}>{$inner}</ol>");
                return "<!-- wp:list {\"ordered\":true} -->{$tagHtml}<!-- /wp:list -->";
            },
            $html
        );

        // 4. Tables: <table>
        $html = preg_replace_callback(
            '/<table(\s+[^>]*)?>(.*?)<\/table>/is',
            function ($matches) {
                $attrs = isset($matches[1]) ? $matches[1] : '';
                $inner = $matches[2];
                $tagHtml = $this->cleanHtmlInlineStyles("<table{$attrs}>{$inner}</table>");
                return "<!-- wp:table --><figure class=\"wp-block-table\">{$tagHtml}</figure><!-- /wp:table -->";
            },
            $html
        );

        // 5. Blockquotes: <blockquote>
        $html = preg_replace_callback(
            '/<blockquote(\s+[^>]*)?>(.*?)<\/blockquote>/is',
            function ($matches) {
                $attrs = isset($matches[1]) ? $matches[1] : '';
                $inner = $matches[2];
                $tagHtml = $this->cleanHtmlInlineStyles("<blockquote class=\"wp-block-quote\"{$attrs}>{$inner}</blockquote>");
                return "<!-- wp:quote -->{$tagHtml}<!-- /wp:quote -->";
            },
            $html
        );

        // 6. Images: <img>
        $html = preg_replace_callback(
            '/<img(\s+[^>]*)?\/?>/is',
            function ($matches) {
                $rawImg = "<img" . (isset($matches[1]) ? $matches[1] : '') . " />";
                $cleanImg = StyleSanitizer::cleanImageTag($rawImg);
                $imgId = 0;
                if (preg_match('/wp-image-(\d+)/i', isset($matches[1]) ? $matches[1] : '', $idMatch)) {
                    $imgId = (int)$idMatch[1];
                }
                $jsonAttr = $imgId > 0 ? " {\"id\":{$imgId}}" : "";
                return "<!-- wp:image{$jsonAttr} --><figure class=\"wp-block-image\">{$cleanImg}</figure><!-- /wp:image -->";
            },
            $html
        );

        // 7. Paragraphs: <p>
        $html = preg_replace_callback(
            '/<p(\s+[^>]*)?>(.*?)<\/p>/is',
            function ($matches) {
                $attrs = isset($matches[1]) ? $matches[1] : '';
                $inner = $matches[2];
                $tagHtml = $this->cleanHtmlInlineStyles("<p{$attrs}>{$inner}</p>");
                return "<!-- wp:paragraph -->{$tagHtml}<!-- /wp:paragraph -->";
            },
            $html
        );

        // 8. Bare un-wrapped text
        $trimmed = trim($html);
        if (!empty($trimmed) && !preg_match('/^<!--\s*wp:/i', $trimmed) && !preg_match('/<(?:p|h[1-6]|ul|ol|table|blockquote|figure|div)/i', $trimmed)) {
            $html = "<!-- wp:paragraph --><p>{$trimmed}</p><!-- /wp:paragraph -->";
        }

        return $html;
    }

    /**
     * Step 3F: Process Classic HTML into Blocks (Idempotent chunk processing)
     */
    public function processClassicHtml(string $content): string
    {
        if (empty(trim($content))) {
            return $content;
        }

        // Split content by existing Gutenberg block annotations to isolate bare HTML segments
        $tokens = preg_split('/(<!--\s+\/?wp:[^>]+-->)/s', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        $output = '';

        foreach ($tokens as $token) {
            // If segment is an existing block comment tag, leave unchanged
            if (preg_match('/^<!--\s+\/?wp:[^>]+-->$/s', $token)) {
                $output .= $token;
            } else {
                // Segment is unannotated classic HTML or text, run block conversion
                $output .= $this->convertHtmlElementsToBlocks($token);
            }
        }

        return $output;
    }

    /**
     * MFN Left Sidebar 30/70 Layout Wrapper Transformation
     */
    public function transformMfnLeftSidebarLayout(string $content, int $postId = 0, $mysqli = null, bool $forceEnable = false): string
    {
        if (empty(trim($content)) || strpos($content, 'eka-has-sidebar-left') !== false) {
            return $content;
        }

        $hasLeftSidebar = $forceEnable;

        if (!$hasLeftSidebar && $mysqli instanceof \mysqli && $postId > 0) {
            $stmt = $mysqli->prepare("SELECT meta_value FROM wp_postmeta WHERE post_id = ? AND meta_key = 'mfn-post-layout'");
            if ($stmt) {
                $stmt->bind_param("i", $postId);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    if ($row = $res->fetch_assoc()) {
                        if ($row['meta_value'] === 'left-sidebar') {
                            $hasLeftSidebar = true;
                        }
                    }
                }
                $stmt->close();
            }
        }

        if (!$hasLeftSidebar) {
            return $content;
        }

        $sidebarBlock = '<!-- wp:group {"className":"eka-sidebar-wrapper"} --><div class="wp-block-group eka-sidebar-wrapper"><!-- wp:template-part {"slug":"sidebar","theme":"ekalexandria-flagship"} /--></div><!-- /wp:group -->';

        $wrapper = '<!-- wp:columns {"className":"eka-has-sidebar-left"} -->';
        $wrapper .= '<div class="wp-block-columns eka-has-sidebar-left">';
        $wrapper .= '<!-- wp:column {"width":"30%"} --><div class="wp-block-column" style="flex-basis:30%;">' . $sidebarBlock . '</div><!-- /wp:column -->';
        $wrapper .= '<!-- wp:column {"width":"70%"} --><div class="wp-block-column" style="flex-basis:70%;">' . $content . '</div><!-- /wp:column -->';
        $wrapper .= '</div><!-- /wp:columns -->';

        return $wrapper;
    }
}
