<?php
/**
 * Test script to verify LayerSlider image resolution and Gutenberg Gallery Block conversion
 */

require_once __DIR__ . '/migration-helpers.php';

$scoping_file = dirname(__DIR__) . '/ai-work/scopings/detailed-sliders-inventory.json';
if (!file_exists($scoping_file)) {
    die("Scoping file detailed-sliders-inventory.json not found!\n");
}

$inventory = json_decode(file_get_contents($scoping_file), true);
$db_sliders = $inventory['all_database_layersliders'] ?? [];

echo "Found " . count($db_sliders) . " LayerSliders in database inventory.\n\n";

function resolve_layerslider_images($slider_id, $db_sliders) {
    $images = [];
    foreach ($db_sliders as $slider) {
        if ((int)$slider['slider_id'] === (int)$slider_id) {
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
    return $images;
}

$transformer = new \EkaAlexandria\Migration\Content\ContentTransformer();

foreach ($db_sliders as $slider) {
    $s_id = $slider['slider_id'];
    $s_name = $slider['name'];
    $images = resolve_layerslider_images($s_id, $db_sliders);

    echo "LayerSlider ID {$s_id} ('{$s_name}'): " . count($images) . " images resolved.\n";

    $block_html = $transformer->buildGutenbergGalleryBlock($images, 'layerslider-replaced ekk-carousel', "LayerSlider ID: {$s_id}");
    $valid = eka_validate_blocks_ast($block_html);
    echo "  AST Validation: " . ($valid ? "PASS" : "FAIL") . "\n";
    if (count($images) > 0) {
        echo "  First Image: " . ($images[0]['url'] ?? '') . "\n";
    }
    echo "\n";
}
