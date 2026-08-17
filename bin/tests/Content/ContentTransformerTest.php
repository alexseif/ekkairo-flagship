<?php

namespace EkaAlexandria\Migration\Tests\Content;

use PHPUnit\Framework\TestCase;
use EkaAlexandria\Migration\Content\ContentTransformer;

class ContentTransformerTest extends TestCase
{
    private ContentTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new ContentTransformer();
    }

    public function testParseFractionWidth(): void
    {
        $this->assertEquals('100%', $this->transformer->parseFractionWidth(''));
        $this->assertEquals('50%', $this->transformer->parseFractionWidth('1/2'));
        $this->assertEquals('33.33%', $this->transformer->parseFractionWidth('1/3'));
        $this->assertEquals('75%', $this->transformer->parseFractionWidth('3/4'));
        $this->assertEquals('80%', $this->transformer->parseFractionWidth('80%'));
    }

    public function testCleanHtmlInlineStyles(): void
    {
        $input = '<div style="font-family: Arial; flex-basis: 50%; color: red;">Test</div>';
        $cleaned = $this->transformer->cleanHtmlInlineStyles($input);
        $this->assertStringContainsString('style="flex-basis: 50%;"', $cleaned);
        $this->assertStringNotContainsString('font-family', $cleaned);
        $this->assertStringNotContainsString('color', $cleaned);
    }

    public function testProcessFrontPageContentRemovesSlidersAndGrids(): void
    {
        $input = '[rev_slider alias="home"][vc_posts_grid loop="size:10"][vc_column_text]<p>Welcome to EKA</p>[/vc_column_text]';
        $processed = $this->transformer->processFrontPageContent($input, 13236);

        $this->assertStringNotContainsString('rev_slider', $processed);
        $this->assertStringNotContainsString('vc_posts_grid', $processed);
        $this->assertStringContainsString('<p>Welcome to EKA</p>', $processed);
    }

    public function testTransformTestimonialsConvertsShortcodesToQuoteBlocks(): void
    {
        $input = '[testimonial_set][testimonial client="Jane Doe"]Great service![/testimonial][/testimonial_set]';
        $transformed = $this->transformer->transformTestimonials($input);

        $this->assertStringContainsString('wp:quote', $transformed);
        $this->assertStringContainsString('Great service!', $transformed);
        $this->assertStringContainsString('<cite>Jane Doe</cite>', $transformed);
        $this->assertStringNotContainsString('[testimonial', $transformed);
    }

    public function testTransformWpbakeryAndCaptionUnwrapsSingleColumnRows(): void
    {
        $inputSingleCol = '[vc_row][vc_column width="1/1"][vc_column_text]<p>Hello World</p>[/vc_column_text][/vc_column][/vc_row]';
        $transformed = $this->transformer->transformWpbakeryAndCaption($inputSingleCol);

        $this->assertStringContainsString('<!-- wp:paragraph --><p>Hello World</p><!-- /wp:paragraph -->', trim($transformed));
        $this->assertStringNotContainsString('wp:columns', $transformed);
    }

    public function testTransformWpbakeryAndCaptionCreatesColumnsForMultiColumnRows(): void
    {
        $inputMultiCol = '[vc_row][vc_column width="1/2"][vc_column_text]<p>Col 1</p>[/vc_column_text][/vc_column][vc_column width="1/2"][vc_column_text]<p>Col 2</p>[/vc_column_text][/vc_column][/vc_row]';
        $transformed = $this->transformer->transformWpbakeryAndCaption($inputMultiCol);

        $this->assertStringContainsString('wp:columns', $transformed);
        $this->assertStringContainsString('flex-basis:50%', $transformed);
        $this->assertStringContainsString('Col 1', $transformed);
        $this->assertStringContainsString('Col 2', $transformed);
    }

    public function testTransformResidualShortcodesStripsOrTransformsUnprocessedShortcodes(): void
    {
        $input = '[vc_empty_space height="32px"][vc_custom_heading title="Title"][unknown_legacy_shortcode]';
        $transformed = $this->transformer->transformResidualShortcodes($input);

        $this->assertStringNotContainsString('[vc_empty_space', $transformed);
        $this->assertStringNotContainsString('[vc_custom_heading', $transformed);
    }

    public function testConvertHtmlElementsToBlocksWrapsClassicHtml(): void
    {
        $input = '<h2>Heading 2</h2><p>Paragraph text</p><ul><li>Item 1</li></ul>';
        $converted = $this->transformer->convertHtmlElementsToBlocks($input);

        $this->assertStringContainsString('<!-- wp:heading {"level":2} --><h2>Heading 2</h2><!-- /wp:heading -->', $converted);
        $this->assertStringContainsString('<!-- wp:paragraph --><p>Paragraph text</p><!-- /wp:paragraph -->', $converted);
        $this->assertStringContainsString('<!-- wp:list --><ul><li>Item 1</li></ul><!-- /wp:list -->', $converted);
    }

    public function testBuildGutenbergGalleryBlock(): void
    {
        $images = [
            ['id' => 101, 'url' => 'https://example.com/img1.jpg'],
            ['id' => 102, 'url' => 'https://example.com/img2.jpg']
        ];
        $galleryBlock = $this->transformer->buildGutenbergGalleryBlock($images, 'custom-gallery-class');

        $this->assertStringContainsString('wp:gallery', $galleryBlock);
        $this->assertStringContainsString('custom-gallery-class', $galleryBlock);
        $this->assertStringContainsString('"id":101', $galleryBlock);
        $this->assertStringContainsString('src="https://example.com/img1.jpg"', $galleryBlock);
    }

    public function testTransformMfnLeftSidebarLayout(): void
    {
        $input = '<!-- wp:paragraph --><p>Main page content here</p><!-- /wp:paragraph -->';
        $wrapped = $this->transformer->transformMfnLeftSidebarLayout($input, 99999, null, true);

        $this->assertStringContainsString('eka-has-sidebar-left', $wrapped);
        $this->assertStringContainsString('"width":"30%"', $wrapped);
        $this->assertStringContainsString('"width":"70%"', $wrapped);
        $this->assertStringContainsString('Main page content here', $wrapped);
    }

    public function testTransformVcSingleImageOptionOneWithTitleLinkAndAlignment(): void
    {
        $input = '[vc_single_image image="8616" border_color="blue" img_link_target="_blank" title="ΑΒΕΡΩΦΕΙΟ ΓΥΜΝΑΣΙΟ-ΛΥΚΕΙΟ ΑΛΕΞΑΝΔΡΕΙΑΣ" link="http://www.averofeion.org/" css_animation="appear" alignment="center"]';
        $transformed = $this->transformer->transformVcSingleImage($input);

        $this->assertStringContainsString('<!-- wp:heading {"level":3,"textAlign":"center"} -->', $transformed);
        $this->assertStringContainsString('<h3 class="wp-block-heading has-text-align-center">ΑΒΕΡΩΦΕΙΟ ΓΥΜΝΑΣΙΟ-ΛΥΚΕΙΟ ΑΛΕΞΑΝΔΡΕΙΑΣ</h3>', $transformed);
        $this->assertStringContainsString('<!-- wp:image {"id":8616,"sizeSlug":"full","linkDestination":"custom","align":"center"} -->', $transformed);
        $this->assertStringContainsString('<a href="http://www.averofeion.org/" target="_blank" rel="noreferrer noopener">', $transformed);
        $this->assertStringContainsString('class="wp-image-8616"', $transformed);
        $this->assertStringContainsString('alt="ΑΒΕΡΩΦΕΙΟ ΓΥΜΝΑΣΙΟ-ΛΥΚΕΙΟ ΑΛΕΞΑΝΔΡΕΙΑΣ"', $transformed);
    }

    public function testTransformVcSingleImageWithoutTitleOrLink(): void
    {
        $input = '[vc_single_image image="1234"]';
        $transformed = $this->transformer->transformVcSingleImage($input);

        $this->assertStringNotContainsString('wp:heading', $transformed);
        $this->assertStringContainsString('<!-- wp:image {"id":1234,"sizeSlug":"full"} -->', $transformed);
        $this->assertStringContainsString('<figure class="wp-block-image">', $transformed);
        $this->assertStringNotContainsString('<a href=', $transformed);
    }

    public function testTransformWpbakerySingleFullColumnRowWithColumnTextCreatesSingleParagraph(): void
    {
        $input = '[vc_row][vc_column width="1/1"][vc_column_text]Η Ελληνική Κοινότητα Αλεξανδρείας παρέχει στα Μέλη της μία σειρά από υπηρεσίες.[/vc_column_text][/vc_column][/vc_row]';
        $transformed = $this->transformer->transformWpbakeryAndCaption($input);

        $this->assertStringNotContainsString('wp:columns', $transformed);
        $this->assertStringNotContainsString('wp-block-column', $transformed);
        $this->assertStringContainsString('<!-- wp:paragraph -->', $transformed);
        $this->assertStringContainsString('<p>Η Ελληνική Κοινότητα Αλεξανδρείας παρέχει στα Μέλη της μία σειρά από υπηρεσίες.</p>', $transformed);
        $this->assertEquals(1, substr_count($transformed, '<!-- wp:paragraph -->'));
    }

    public function testTransformWpbakerySingleFullColumnRowWithoutColumnTextUnwrapsCleanly(): void
    {
        $input = '[vc_row][vc_column width="1/1"]<p>Simple paragraph text</p>[/vc_column][/vc_row]';
        $transformed = $this->transformer->transformWpbakeryAndCaption($input);

        $this->assertStringNotContainsString('wp:columns', $transformed);
        $this->assertStringNotContainsString('wp-block-column', $transformed);
        $this->assertStringContainsString('<!-- wp:paragraph -->', $transformed);
        $this->assertEquals(1, substr_count($transformed, '<!-- wp:paragraph -->'));
    }

    public function testTransformVcPostsGridSubpagesQuery(): void
    {
        $input = '[vc_posts_grid loop="size:10|order_by:menu_order|order:ASC|post_type:page|by_id:7399,7397,7395,7390,7387,3479,3467,3451,3442" grid_columns_count="2" grid_layout="image|link_post,title|link_post" grid_link_target="_self" grid_layout_mode="fitRows" grid_thumb_size="medium"]';
        $transformed = $this->transformer->transformVcPostsGrid($input, 14);

        $this->assertStringContainsString('wp:query', $transformed);
        $this->assertStringContainsString('"postType":"page"', $transformed);
        $this->assertStringContainsString('"parents":[14]', $transformed);
        $this->assertStringContainsString('"include":[7399,7397,7395,7390,7387,3479,3467,3451,3442]', $transformed);
        $this->assertStringContainsString('"columnCount":2', $transformed);
        $this->assertStringContainsString('wp:post-featured-image', $transformed);
        $this->assertStringContainsString('aspectRatio":"3/2"', $transformed);
        $this->assertStringContainsString('topLeft":"12px"', $transformed);
        $this->assertStringContainsString('wp:post-title', $transformed);
        $this->assertStringNotContainsString('wp:post-excerpt', $transformed);
    }

    public function testTransformVcPostsGridWithTitleTagsAndLayoutOrder(): void
    {
        $input = '[vc_posts_grid loop="size:10|order_by:date|order:DESC|post_type:post|tags:397" grid_columns_count="1" grid_layout="title|link_post,image|link_post" grid_link_target="_self" grid_layout_mode="fitRows" title="Παλιότερες Εκδηλώσεις"]';
        $transformed = $this->transformer->transformVcPostsGrid($input, 3444);

        $this->assertStringContainsString('<!-- wp:heading -->', $transformed);
        $this->assertStringContainsString('<h2 class="wp-block-heading">Παλιότερες Εκδηλώσεις</h2>', $transformed);
        $this->assertStringContainsString('"post_tag":[397]', $transformed);
        $this->assertStringContainsString('"columnCount":1', $transformed);
        $this->assertStringContainsString('wp:query-pagination', $transformed);

        // Check layout order: wp:post-title appears before wp:post-featured-image
        $titlePos = strpos($transformed, 'wp:post-title');
        $imgPos = strpos($transformed, 'wp:post-featured-image');
        $this->assertNotFalse($titlePos);
        $this->assertNotFalse($imgPos);
        $this->assertLessThan($imgPos, $titlePos);
    }

    public function testTransformWpbakeryStripsEmptyRowsAndColumns(): void
    {
        $input = '[vc_row][vc_column width="1/1"][/vc_column][/vc_row]';
        $transformed = $this->transformer->transformWpbakeryAndCaption($input);
        $this->assertEquals('', trim($transformed));
    }

    public function testTransformHrShortcodeDefaultWithHeight(): void
    {
        $input = '[hr height="30" style="default" line="default" themecolor="1"]';
        $transformed = $this->transformer->transformHrShortcode($input);

        $this->assertStringContainsString('<!-- wp:spacer {"height":"30px"} -->', $transformed);
        $this->assertStringContainsString('<div style="height:30px" aria-hidden="true" class="wp-block-spacer"></div>', $transformed);
        $this->assertStringContainsString('<!-- wp:separator -->', $transformed);
        $this->assertStringContainsString('<hr class="wp-block-separator has-alpha-channel-opacity"/>', $transformed);
    }

    public function testTransformHrShortcodeNoLine(): void
    {
        $input = '[hr height="50" line="no_line"]';
        $transformed = $this->transformer->transformHrShortcode($input);

        $this->assertStringContainsString('<!-- wp:spacer {"height":"50px"} -->', $transformed);
        $this->assertStringNotContainsString('wp:separator', $transformed);
    }

    public function testTransformHrShortcodeDotsStyle(): void
    {
        $input = '[hr style="dots"]';
        $transformed = $this->transformer->transformHrShortcode($input);

        $this->assertStringContainsString('<!-- wp:separator {"className":"is-style-dots"} -->', $transformed);
        $this->assertStringContainsString('<hr class="wp-block-separator has-alpha-channel-opacity is-style-dots"/>', $transformed);
    }

    public function testTransformCaptionShortcodeWithImageAndCaptionText(): void
    {
        $input = '[caption id="attachment_10109" align="aligncenter" width="435"]<img src="https://backstage.ekalexandria.org/wp-content/uploads/2015/07/Evaggelismos_top-1024x482.jpg" alt="Το εσωτερικό του ναού" class="size-large wp-image-10109" /> Το εσωτερικό του ναού[/caption]';
        $transformed = $this->transformer->transformCaptionShortcode($input);

        $this->assertStringNotContainsString('[caption', $transformed);
        $this->assertStringNotContainsString('[/caption]', $transformed);
        $this->assertStringContainsString('<!-- wp:image {"id":10109,"align":"center"} -->', $transformed);
        $this->assertStringContainsString('<figure class="wp-block-image aligncenter">', $transformed);
        $this->assertStringContainsString('<figcaption class="wp-element-caption">Το εσωτερικό του ναού</figcaption>', $transformed);
    }

    public function testTransformCaptionShortcodeWithLink(): void
    {
        $input = '[caption id="attachment_10110" align="alignright" width="352"]<a href="https://backstage.ekalexandria.org/wp-content/uploads/2015/07/Evaggelismos.jpg"><img src="https://backstage.ekalexandria.org/wp-content/uploads/2015/07/Evaggelismos.jpg" alt="Η πρόσοψη του ναού" class="wp-image-10110" /></a> Η πρόσοψη του ναού[/caption]';
        $transformed = $this->transformer->transformCaptionShortcode($input);

        $this->assertStringNotContainsString('[caption', $transformed);
        $this->assertStringContainsString('<!-- wp:image {"id":10110,"linkDestination":"custom","align":"right"} -->', $transformed);
        $this->assertStringContainsString('<a href="https://backstage.ekalexandria.org/wp-content/uploads/2015/07/Evaggelismos.jpg">', $transformed);
        $this->assertStringContainsString('<figcaption class="wp-element-caption">Η πρόσοψη του ναού</figcaption>', $transformed);
    }

    public function testTransformRevSliderWithDbUrlResolutionAndUnwrapping(): void
    {
        $images = [
            ['id' => 10328, 'url' => 'https://backstage.ekalexandria.org/wp-content/uploads/2015/06/ΚΟΙΝΟΤΙΚΟ-ΕΝΤΕΥΚΤΗΡΙΟ.png']
        ];
        $transformed = $this->transformer->buildGutenbergGalleryBlock($images, 'rev-slider-replaced', 'Slider');

        $this->assertStringContainsString('wp:gallery', $transformed);
        $this->assertStringContainsString('src="https://backstage.ekalexandria.org/wp-content/uploads/2015/06/ΚΟΙΝΟΤΙΚΟ-ΕΝΤΕΥΚΤΗΡΙΟ.png"', $transformed);
        $this->assertStringNotContainsString('src=""', $transformed);
    }
}


