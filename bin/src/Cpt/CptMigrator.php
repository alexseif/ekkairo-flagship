<?php

namespace EkaAlexandria\Migration\Cpt;

use EkaAlexandria\Migration\Utils\Logger;

class CptMigrator
{
    private array $months = [
        'Ιανουάριος' => '01', 'Ιανουαρίου' => '01',
        'Φεβρουάριος' => '02', 'Φεβρουαρίου' => '02',
        'Μάρτιος' => '03', 'Μαρτίου' => '03',
        'Απρίλιος' => '04', 'Απριλίου' => '04', 'ΑΠΡΛΙΟΣ' => '04',
        'Μάιος' => '05', 'Μαΐου' => '05',
        'Ιούνιος' => '06', 'Ιουνίου' => '06',
        'Ιούλιος' => '07', 'Ιουλίου' => '07',
        'Αύγουστος' => '08', 'Αυγούστου' => '08',
        'Σεπτέμβριος' => '09', 'Σεπτεμβρίου' => '09',
        'Οκτώβριος' => '10', 'Οκτωβρίου' => '10',
        'Νοέμβριος' => '11', 'Νοεμβρίου' => '11',
        'Δεκέμβριος' => '12', 'Δεκεμβρίου' => '12',
    ];

    private array $monthTitleCasing = [
        '01' => 'Ιανουάριος',
        '02' => 'Φεβρουάριος',
        '03' => 'Μάρτιος',
        '04' => 'Απρίλιος',
        '05' => 'Μάιος',
        '06' => 'Ιούνιος',
        '07' => 'Ιούλιος',
        '08' => 'Αύγουστος',
        '09' => 'Σεπτέμβριος',
        '10' => 'Οκτώβριος',
        '11' => 'Νοέμβριος',
        '12' => 'Δεκέμβριος',
    ];

    public function getGreekMonthName(string $monthNum): string
    {
        $num = str_pad($monthNum, 2, '0', STR_PAD_LEFT);
        return $this->monthTitleCasing[$num] ?? '';
    }

    public function parseGreekDateFromTitle(string $title): array
    {
        $year = '';
        $month = '';

        if (preg_match('/(20\d{2}|19\d{2})/', $title, $ym)) {
            $year = $ym[1];
        }

        foreach ($this->months as $mName => $mNum) {
            if (mb_stripos($title, $mName) !== false) {
                $month = $mNum;
                break;
            }
        }

        return [
            'year' => $year,
            'month' => $month,
        ];
    }
}
