<?php

namespace EkaAlexandria\Migration\Navigation;

class MenuMigrator
{
    /**
     * Build block markup recursively from hierarchical menu items, automatically resolving Polylang translations if available.
     *
     * @param array $items Array of menu item objects or associative arrays
     * @param int $parentId
     * @param string $lang
     * @return string
     */
    public function buildNavBlocksMarkup(array $items, int $parentId = 0, string $lang = 'el'): string
    {
        $markup = '';
        foreach ($items as $item) {
            $itemParent = is_object($item) ? (int)$item->menu_item_parent : (int)($item['menu_item_parent'] ?? 0);
            if ($itemParent !== $parentId) {
                continue;
            }

            $dbId  = is_object($item) ? (int)($item->db_id ?? $item->ID ?? 0) : (int)($item['db_id'] ?? $item['ID'] ?? 0);
            $rawTitle = is_object($item) ? $item->title : ($item['title'] ?? '');
            $rawUrl = is_object($item) ? $item->url : ($item['url'] ?? '');
            $rawType = is_object($item) ? ($item->type ?? 'custom') : ($item['type'] ?? 'custom');
            $rawObject = is_object($item) ? ($item->object ?? 'custom') : ($item['object'] ?? 'custom');
            $rawObjectId = is_object($item) ? (int)($item->object_id ?? 0) : (int)($item['object_id'] ?? 0);

            $label = function_exists('esc_html') ? esc_html($rawTitle) : htmlspecialchars($rawTitle, ENT_QUOTES, 'UTF-8');
            $url   = function_exists('esc_url') ? esc_url($rawUrl) : $rawUrl;
            $kind  = ($rawType === 'custom') ? 'custom' : 'post-type';
            $type  = function_exists('esc_attr') ? esc_attr($rawObject) : $rawObject;
            $id    = $rawObjectId;

            // Resolve translation for post-type objects if $lang is different
            if ($kind === 'post-type' && $id > 0 && function_exists('pll_get_post')) {
                $transId = pll_get_post($id, $lang);
                if ($transId > 0 && $transId !== $id) {
                    if (function_exists('get_post')) {
                        $transPost = get_post($transId);
                        if ($transPost) {
                            $id    = $transId;
                            $label = esc_html($transPost->post_title);
                            $url   = esc_url(get_permalink($transId));
                        }
                    }
                }
            }

            // Check if item has children
            $hasChildren = false;
            foreach ($items as $child) {
                $childParent = is_object($child) ? (int)$child->menu_item_parent : (int)($child['menu_item_parent'] ?? 0);
                if ($childParent === $dbId && $dbId > 0) {
                    $hasChildren = true;
                    break;
                }
            }

            $attrsArr = [
                'label' => $label,
                'url'   => $url,
                'kind'  => $kind,
                'type'  => $type,
                'id'    => $id,
            ];
            $attrs = json_encode($attrsArr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($hasChildren) {
                $markup .= "<!-- wp:navigation-submenu {$attrs} -->\n";
                $markup .= $this->buildNavBlocksMarkup($items, $dbId, $lang);
                $markup .= "<!-- /wp:navigation-submenu -->\n\n";
            } else {
                $markup .= "<!-- wp:navigation-link {$attrs} /-->\n\n";
            }
        }

        return $markup;
    }
}
