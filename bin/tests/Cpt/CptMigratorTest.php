<?php

namespace EkaAlexandria\Migration\Tests\Cpt;

use PHPUnit\Framework\TestCase;
use EkaAlexandria\Migration\Cpt\CptMigrator;

class CptMigratorTest extends TestCase
{
    private CptMigrator $cptMigrator;

    protected function setUp(): void
    {
        $this->cptMigrator = new CptMigrator();
    }

    public function testParseGreekDateFromTitleExtractsYearAndMonth(): void
    {
        $parsed1 = $this->cptMigrator->parseGreekDateFromTitle('Αλεξανδρινός Ταχυδρόμος - Ιανουάριος 2021');
        $this->assertEquals('2021', $parsed1['year']);
        $this->assertEquals('01', $parsed1['month']);

        $parsed2 = $this->cptMigrator->parseGreekDateFromTitle('Τεύχος Δεκεμβρίου 2019');
        $this->assertEquals('2019', $parsed2['year']);
        $this->assertEquals('12', $parsed2['month']);
    }

    public function testGetGreekMonthNameReturnsTitleCaseMonth(): void
    {
        $this->assertEquals('Ιανουάριος', $this->cptMigrator->getGreekMonthName('01'));
        $this->assertEquals('Δεκέμβριος', $this->cptMigrator->getGreekMonthName('12'));
    }
}
