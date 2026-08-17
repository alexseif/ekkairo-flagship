<?php
/**
 * Unit Test Script for FSE Inline Style Sanitizer & Helper Utilities
 */

require_once __DIR__ . '/migration-helpers.php';

use EkaAlexandria\Migration\Content\ContentTransformer;
use EkaAlexandria\Migration\Utils\StyleSanitizer;

$failures = 0;

function assert_equals($expected, $actual, $test_name) {
    global $failures;
    if ($expected === $actual) {
        echo "[PASS] {$test_name}\n";
    } else {
        echo "[FAIL] {$test_name}\n  Expected: '{$expected}'\n  Actual:   '{$actual}'\n";
        $failures++;
    }
}

$transformer = new ContentTransformer();

// 1. Test sanitize_inline_styles_fse()
$test_cases = [
    [
        'input' => 'font-family: Arial, sans-serif; color: #333; width: 100%; background-color: red;',
        'expected' => 'width: 100%;',
        'name' => 'Strip font, color, background, retain width'
    ],
    [
        'input' => 'flex-basis: 50%; margin: 20px; padding: 10px; flex-grow: 1; flex-shrink: 0; flex-direction: row;',
        'expected' => 'flex-basis: 50%; flex-grow: 1; flex-shrink: 0; flex-direction: row;',
        'name' => 'Retain flex properties, strip margin/padding'
    ],
    [
        'input' => 'aspect-ratio: 16/9; object-fit: cover; text-align: center; vertical-align: middle; float: left;',
        'expected' => 'aspect-ratio: 16/9; object-fit: cover; text-align: center; vertical-align: middle;',
        'name' => 'Retain aspect-ratio, object-fit, text-align, vertical-align, strip float'
    ],
    [
        'input' => 'font-size: 18px; line-height: 1.5; clear: both;',
        'expected' => '',
        'name' => 'Strip all non-allowlisted properties'
    ],
    [
        'input' => '',
        'expected' => '',
        'name' => 'Empty style string handling'
    ]
];

echo "--- Running FSE Style Sanitizer Tests ---\n";
foreach ($test_cases as $case) {
    $result = sanitize_inline_styles_fse($case['input']);
    assert_equals($case['expected'], $result, $case['name']);
}

// 2. Test eka_init_log_file()
echo "\n--- Running Log File Initializer Tests ---\n";
$temp_log = sys_get_temp_dir() . '/test-migration-' . uniqid() . '.log';
file_put_contents($temp_log, "Initial log content\nLine 2\n");
eka_init_log_file($temp_log);
$after_init = file_get_contents($temp_log);
assert_equals('', $after_init, 'Log file truncated to zero bytes on init');
if (file_exists($temp_log)) {
    unlink($temp_log);
}

// 3. Test eka_validate_blocks_ast()
echo "\n--- Running AST Block Validation Tests ---\n";
$valid_block_markup = '<!-- wp:paragraph --><p>Hello World</p><!-- /wp:paragraph -->';
$invalid_block_markup = '<!-- wp:group --><div><p>Unclosed group';
assert_equals(true, eka_validate_blocks_ast($valid_block_markup), 'Valid block markup passes AST check');
assert_equals(false, eka_validate_blocks_ast($invalid_block_markup), 'Unclosed/unbalanced block markup fails AST check');

// 4. Test 1/1 Column Unwrapping in Shortcode Migration Engine
echo "\n--- Running Shortcode 1/1 Column Unwrapping Tests ---\n";
$input_single_col = '[vc_row][vc_column width="1/1"][vc_column_text]<p>Hello World</p>[/vc_column_text][/vc_column][/vc_row]';
$out_single_col = $transformer->transformWpbakeryAndCaption($input_single_col);
$expected_single_col = '<!-- wp:paragraph --><p>Hello World</p><!-- /wp:paragraph -->';
assert_equals($expected_single_col, trim($out_single_col), 'Single 1/1 column row unwraps directly to Gutenberg paragraph');

$input_subgrid = '[vc_row][vc_column width="1/1"][vc_posts_grid loop="size:10|post_type:page|by_id:12,14" grid_columns_count="2"][/vc_column][/vc_row]';
$out_step4a = $transformer->transformWpbakeryAndCaption($input_subgrid);
$out_step4c = $transformer->transformVcPostsGrid($out_step4a, 0);
assert_equals(false, strpos($out_step4c, 'wp:columns'), 'Sub-grid in 1/1 column row has no outer wp:columns block');
assert_equals(true, strpos($out_step4c, 'wp:query') !== false, 'Sub-grid produces wp:query block');

$input_multi_col = '[vc_row][vc_column width="1/2"][vc_column_text]<p>Col 1</p>[/vc_column_text][/vc_column][vc_column width="1/2"][vc_column_text]<p>Col 2</p>[/vc_column_text][/vc_column][/vc_row]';
$out_multi_col = $transformer->transformWpbakeryAndCaption($input_multi_col);
assert_equals(true, strpos($out_multi_col, 'wp:columns') !== false, 'Multi-column row retains wp:columns block wrapper');
assert_equals(true, strpos($out_multi_col, 'flex-basis:50%') !== false, 'Multi-column row retains calculated flex basis');

// 5. Test MFN Left Sidebar 30/70 Column Wrapping
echo "\n--- Running MFN Left Sidebar 30/70 Layout Tests ---\n";
$input_mfn_content = '<!-- wp:paragraph --><p>Main page content here</p><!-- /wp:paragraph -->';
$out_mfn_wrapped = $transformer->transformMfnLeftSidebarLayout($input_mfn_content, 99999, null, true);
assert_equals(true, strpos($out_mfn_wrapped, 'eka-has-sidebar-left') !== false, 'Wrapped layout contains eka-has-sidebar-left class');
assert_equals(true, strpos($out_mfn_wrapped, '"width":"30%"') !== false, 'Wrapped layout has 30% left column');
assert_equals(true, strpos($out_mfn_wrapped, '"width":"70%"') !== false, 'Wrapped layout has 70% right column containing main content');
assert_equals(true, eka_validate_blocks_ast($out_mfn_wrapped), 'Wrapped MFN 30/70 layout passes AST validation');

// 6. Test Image Tag Attribute Sanitization
echo "\n--- Running Image Tag Attribute Sanitization Tests ---\n";
$input_dirty_img = '<figure class="wp-block-image size-full alignright"><img src="https://example.com/test.jpg" alt="test" class="wp-image-8130 size-full alignright" width="700" height="302"/></figure>';
$out_clean_img = eka_sanitize_image_tags($input_dirty_img);
assert_equals(false, strpos($out_clean_img, 'class="wp-image-8130'), 'Strips wp-image-* class from img tag');
assert_equals(false, strpos($out_clean_img, 'width="700"'), 'Strips width attribute from img tag');
assert_equals(false, strpos($out_clean_img, 'height="302"'), 'Strips height attribute from img tag');
assert_equals(false, strpos($out_clean_img, 'size-full'), 'Strips size-full class from figure tag');
assert_equals(false, strpos($out_clean_img, 'alignright'), 'Strips alignright class from figure and img tag');
assert_equals(true, strpos($out_clean_img, '<figure class="wp-block-image"><img src="https://example.com/test.jpg" alt="test"/></figure>') !== false, 'Produces clean figure and img block markup');

if ($failures > 0) {
    echo "\nTEST SUITE FAILED with {$failures} failure(s).\n";
    exit(1);
} else {
    echo "\nALL TESTS PASSED CLEANLY.\n";
    exit(0);
}
