<?php

namespace EkaAlexandria\Migration\Tests\Navigation;

use PHPUnit\Framework\TestCase;
use EkaAlexandria\Migration\Navigation\MenuMigrator;

class MenuMigratorTest extends TestCase
{
    private MenuMigrator $migrator;

    protected function setUp(): void
    {
        $this->migrator = new MenuMigrator();
    }

    public function testBuildNavBlocksMarkupGeneratesNestedNavigationBlocks(): void
    {
        $items = [
            10 => [
                'ID' => 10,
                'title' => 'Home',
                'url' => 'https://example.com/home',
                'menu_item_parent' => 0,
                'type' => 'custom',
                'object_id' => 0,
                'classes' => ['home-class']
            ],
            20 => [
                'ID' => 20,
                'title' => 'About',
                'url' => 'https://example.com/about',
                'menu_item_parent' => 0,
                'type' => 'post_type',
                'object_id' => 42,
                'classes' => []
            ],
            21 => [
                'ID' => 21,
                'title' => 'Our Team',
                'url' => 'https://example.com/about/team',
                'menu_item_parent' => 20,
                'type' => 'post_type',
                'object_id' => 43,
                'classes' => []
            ]
        ];

        $markup = $this->migrator->buildNavBlocksMarkup($items, 0, 'el');

        $this->assertStringContainsString('wp:navigation-link', $markup);
        $this->assertStringContainsString('Home', $markup);
        $this->assertStringContainsString('About', $markup);
        $this->assertStringContainsString('wp:navigation-submenu', $markup);
        $this->assertStringContainsString('Our Team', $markup);
    }
}
