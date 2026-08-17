<?php

namespace EkaAlexandria\Migration\Utils;

class StyleSanitizer
{
    /**
     * Filters inline CSS against a strict FSE Property Allowlist.
     * Retains layout, grid, alignment, and sizing properties.
     * Discards legacy font, color, margin, and padding properties.
     *
     * @param string $styleString
     * @return string Filtered CSS style string.
     */
    public static function sanitizeInlineStyles(string $styleString): string
    {
        if (empty(trim($styleString))) {
            return '';
        }

        $allowlist = [
            'flex-basis', 'flex-grow', 'flex-shrink', 'flex-direction',
            'grid-template-columns', 'width', 'height', 'min-height',
            'max-width', 'aspect-ratio', 'object-fit', 'vertical-align', 'text-align'
        ];

        $declarations = array_filter(array_map('trim', explode(';', $styleString)));
        $retained = [];

        foreach ($declarations as $decl) {
            $parts = array_map('trim', explode(':', $decl, 2));
            if (count($parts) === 2 && in_array(strtolower($parts[0]), $allowlist, true)) {
                $retained[] = strtolower($parts[0]) . ": {$parts[1]}";
            }
        }

        return empty($retained) ? '' : implode('; ', $retained) . ';';
    }

    /**
     * Cleans an <img> HTML tag string:
     * - Removes legacy class names (wp-image-XXX, size-XXX)
     * - Removes width and height attributes (width="150", height="150")
     *
     * @param string $imgHtml
     * @return string Cleaned <img> HTML tag string.
     */
    public static function cleanImageTag(string $imgHtml): string
    {
        if (empty(trim($imgHtml))) {
            return $imgHtml;
        }

        // Remove width="150" or width='150'
        $imgHtml = preg_replace('/\s+width=["\']?\d+%?["\']?/i', '', $imgHtml);

        // Remove height="150" or height='150'
        $imgHtml = preg_replace('/\s+height=["\']?\d+%?["\']?/i', '', $imgHtml);

        // Clean class attribute: remove wp-image-XXX and size-XXX classes
        $imgHtml = preg_replace_callback('/\s+class=["\']([^"\']*)["\']/i', function ($m) {
            $classes = array_filter(explode(' ', $m[1]), function ($cls) {
                $cls = trim($cls);
                if (empty($cls)) return false;
                if (preg_match('/^wp-image-\d+$/i', $cls)) return false;
                if (preg_match('/^size-[a-z0-9_-]+$/i', $cls)) return false;
                return true;
            });
            if (empty($classes)) {
                return '';
            }
            return ' class="' . implode(' ', $classes) . '"';
        }, $imgHtml);

        // Clean up formatting
        $imgHtml = preg_replace('/\s+/', ' ', $imgHtml);
        $imgHtml = str_replace(' >', '/>', $imgHtml);
        $imgHtml = str_replace(' />', '/>', $imgHtml);

        return $imgHtml;
    }

    /**
     * Sanitizes image tags and figure blocks by stripping legacy class names
     * (e.g. wp-image-*, size-full, size-large, alignright, alignleft) and explicit width/height attributes.
     *
     * @param string $html
     * @return string
     */
    public static function sanitizeImageTags(string $html): string
    {
        if (empty($html) || strpos($html, '<img') === false) {
            return $html;
        }

        // Clean <img ... /> tags: strip width, height, class attributes completely
        $html = preg_replace_callback('/<img\s+[^>]*>/i', function ($m) {
            $img = $m[0];
            $img = preg_replace('/\s+(width|height)=(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $img);
            $img = preg_replace('/\s+class=(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $img);
            return $img;
        }, $html);

        // Clean <figure class="...">: strip size-full, size-large, alignright, alignleft, etc.
        $html = preg_replace_callback('/<figure\s+class=["\']([^"\']*)["\']>/i', function ($m) {
            $classes = array_filter(explode(' ', $m[1]));
            $disallowed = ['size-full', 'size-large', 'size-medium', 'alignright', 'alignleft', 'aligncenter'];
            $cleanClasses = array_values(array_diff($classes, $disallowed));
            if (empty($cleanClasses)) {
                $cleanClasses = ['wp-block-image'];
            }
            return '<figure class="' . implode(' ', $cleanClasses) . '">';
        }, $html);

        return $html;
    }

    /**
     * Validates block content structure using WordPress parse_blocks() if available,
     * or a fallback AST balanced block check.
     *
     * @param string $content
     * @return bool True if valid, false if invalid or malformed AST.
     */
    public static function validateBlocksAst(string $content): bool
    {
        if (empty(trim($content))) {
            return true;
        }

        if (function_exists('parse_blocks')) {
            $blocks = parse_blocks($content);
            if (empty($blocks)) {
                return false;
            }
            return true;
        }

        // Lightweight AST balanced block validation
        $stack = [];
        preg_match_all('/<!--\s+(?<type>\/)?wp:(?<name>[a-z0-9\/-]+)(?:\s+(?<attrs>\{.*?\}))?\s+(?<selfclosing>\/)?-->/s', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $isClose = !empty($match['type']);
            $blockName = $match['name'];
            $isSelfClosing = isset($match['selfclosing']) && !empty($match['selfclosing']);

            if ($isSelfClosing) {
                continue;
            }

            if (!$isClose) {
                // Opening block tag
                $stack[] = $blockName;
            } else {
                // Closing block tag
                if (empty($stack)) {
                    return false; // Unexpected close tag
                }
                $last = array_pop($stack);
                if ($last !== $blockName) {
                    return false; // Mismatched block closing tag
                }
            }
        }

        return empty($stack);
    }

    /**
     * Initializes and truncates log file on script startup.
     *
     * @param string $logPath
     * @return void
     */
    public static function initLogFile(string $logPath): void
    {
        $dir = dirname($logPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($logPath, '');
    }
}
