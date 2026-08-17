<?php

namespace EkaAlexandria\Migration\Tests\Utils;

use PHPUnit\Framework\TestCase;
use EkaAlexandria\Migration\Utils\StyleSanitizer;

class StyleSanitizerTest extends TestCase
{
    public function testSanitizeInlineStylesFseRetainsAllowlistedPropertiesOnly(): void
    {
        $input = 'font-family: Arial, sans-serif; color: #333; width: 100%; background-color: red;';
        $expected = 'width: 100%;';
        $this->assertEquals($expected, StyleSanitizer::sanitizeInlineStyles($input));

        $flexInput = 'flex-basis: 50%; margin: 20px; padding: 10px; flex-grow: 1; flex-shrink: 0; flex-direction: row;';
        $flexExpected = 'flex-basis: 50%; flex-grow: 1; flex-shrink: 0; flex-direction: row;';
        $this->assertEquals($flexExpected, StyleSanitizer::sanitizeInlineStyles($flexInput));

        $aspectInput = 'aspect-ratio: 16/9; object-fit: cover; text-align: center; vertical-align: middle; float: left;';
        $aspectExpected = 'aspect-ratio: 16/9; object-fit: cover; text-align: center; vertical-align: middle;';
        $this->assertEquals($aspectExpected, StyleSanitizer::sanitizeInlineStyles($aspectInput));
    }

    public function testSanitizeInlineStylesReturnsEmptyStringForDisallowedOrEmptyStyles(): void
    {
        $input = 'font-size: 18px; line-height: 1.5; clear: both;';
        $this->assertEquals('', StyleSanitizer::sanitizeInlineStyles($input));
        $this->assertEquals('', StyleSanitizer::sanitizeInlineStyles(''));
    }

    public function testCleanImageTagStripsWidthHeightAndLegacyClasses(): void
    {
        $imgInput = '<img src="test.jpg" class="alignleft wp-image-1234 size-full custom-class" width="150" height="150" alt="Test"/>';
        $cleaned = StyleSanitizer::cleanImageTag($imgInput);

        $this->assertStringNotContainsString('width=', $cleaned);
        $this->assertStringNotContainsString('height=', $cleaned);
        $this->assertStringNotContainsString('wp-image-1234', $cleaned);
        $this->assertStringNotContainsString('size-full', $cleaned);
        $this->assertStringContainsString('custom-class', $cleaned);
        $this->assertStringContainsString('alignleft', $cleaned);
    }

    public function testSanitizeImageTagsStripsInlineWidthHeightAndLegacyFigureClasses(): void
    {
        $htmlInput = '<figure class="wp-block-image size-full aligncenter"><img src="pic.png" width="300" height="200" style="color: red; flex-grow: 1;"/></figure>';
        $sanitized = StyleSanitizer::sanitizeImageTags($htmlInput);

        $this->assertStringNotContainsString('size-full', $sanitized);
        $this->assertStringNotContainsString('aligncenter', $sanitized);
        $this->assertStringNotContainsString('width=', $sanitized);
        $this->assertStringNotContainsString('height=', $sanitized);
        $this->assertStringContainsString('wp-block-image', $sanitized);
    }

    public function testValidateBlocksAstValidatesBalancedBlockComments(): void
    {
        $validMarkup = '<!-- wp:paragraph --><p>Hello World</p><!-- /wp:paragraph -->';
        $invalidMarkup = '<!-- wp:group --><div><p>Unclosed group';

        $this->assertTrue(StyleSanitizer::validateBlocksAst($validMarkup));
        $this->assertFalse(StyleSanitizer::validateBlocksAst($invalidMarkup));
        $this->assertTrue(StyleSanitizer::validateBlocksAst(''));
    }

    public function testInitLogFileTruncatesOrCreateLogFile(): void
    {
        $tempLog = sys_get_temp_dir() . '/test-log-' . uniqid() . '.log';
        file_put_contents($tempLog, "Sample initial log entry\n");

        StyleSanitizer::initLogFile($tempLog);

        $this->assertFileExists($tempLog);
        $this->assertEquals('', file_get_contents($tempLog));

        if (file_exists($tempLog)) {
            unlink($tempLog);
        }
    }
}
