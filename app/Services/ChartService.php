<?php
// app/Services/Dashboard/ChartService.php

namespace App\Services\Dashboard;

class ChartService
{
    private static array $MONTHS = [
        1=>'Jan',2=>'Fév',3=>'Mar',4=>'Avr',5=>'Mai',6=>'Jun',
        7=>'Jul',8=>'Aoû',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Déc',
    ];

    /** Area / Line — données mensuelles [month => value] */
    public static function monthlyArea(array $data, int $fill = 12): array
    {
        $series = $categories = [];
        for ($m = 1; $m <= $fill; $m++) {
            $categories[] = self::$MONTHS[$m];
            $series[]     = (float) ($data[$m] ?? 0);
        }
        return compact('series', 'categories');
    }

    /** Donut — ['label' => value] */
    public static function donut(array $data, array $colors = []): array
    {
        return [
            'series' => array_values($data),
            'labels' => array_keys($data),
            'colors' => $colors ?: ['#1E2D5A','#E8A838','#22c55e','#E05C3A','#8B5CF6','#06B6D4'],
        ];
    }

    /** Grouped bar — présences [['date'=>'Lun','present'=>N,'absent'=>N]] */
    public static function attendanceBar(array $days): array
    {
        return [
            'categories' => array_column($days, 'date'),
            'series'     => [
                ['name' => 'Présents', 'data' => array_column($days, 'present')],
                ['name' => 'Absents',  'data' => array_column($days, 'absent')],
            ],
        ];
    }

    /** Horizontal bar — top débiteurs */
    public static function horizontalBar(array $items): array
    {
        $sorted = collect($items)->sortByDesc('balance')->take(8)->values();
        return [
            'categories' => $sorted->pluck('name')->toArray(),
            'series'     => [['name' => 'Solde dû (DJF)', 'data' => $sorted->pluck('balance')->map(fn($v) => round($v))->toArray()]],
        ];
    }

    /** Méthodes de paiement → donut avec labels traduits */
    public static function paymentMethods(array $byMethod): array
    {
        $labels = [
            'cash'      => 'Espèces',
            'd_money'   => 'D-Money',
            'cac_pay'   => 'CAC Pay',
            'cheque'    => 'Chèque',
            'virement'  => 'Virement',
        ];
        $data = [];
        foreach ($byMethod as $method => $amount) {
            $label        = $labels[$method] ?? ucfirst($method);
            $data[$label] = (float) $amount;
        }
        return self::donut($data, ['#1E2D5A','#E8A838','#22c55e','#E05C3A','#8B5CF6']);
    }
}
